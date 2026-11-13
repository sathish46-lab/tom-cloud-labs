import datetime
import json
import os
import re
import subprocess
import sys
import urllib.parse
import uuid

from src.router import Command

# labsctl service — learner service engine operations.
#
# labsctl service <slug> --email=EMAIL <action> [options]
#
# Slugs:    mysql, mariadb, mongodb, postgresql, redis, rabbitmq
# Actions:  ping, createuser, deleteuser, listuser
#           createdb, deletedb, listdb        (mysql, mariadb, mongodb, postgresql)
#           getcollation                      (mysql, mariadb)
#           getencoding                       (postgresql)
#           createvhost, deletevhost, listvhost (rabbitmq)
#
# Options:  --email=EMAIL --username=NAME --password=PASS
#           --database=NAME --charset=NAME --collation=NAME --vhost=NAME
#
# Always prints a single JSON line:
#   {"status": "success"|"error", "code": N, "message": "...", "data": ...}
# Exit codes: 0 success, 1 engine/operation error, 2 usage error.

IN_CONTAINER = os.path.exists('/.dockerenv')
DOCKER_CONTAINER = 'Dev_lab'

IDENT_RE = re.compile(r'^[a-zA-Z_][a-zA-Z0-9_]*$')
VHOST_RE = re.compile(r'^[a-zA-Z0-9_.\-]+$')
COLLATION_RE = re.compile(r'^[a-zA-Z0-9_]+$')
EMAIL_FLAG_RE = re.compile(r'^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$')
HOST_RE = re.compile(r'^[a-zA-Z0-9._:\-]{1,64}$')

AUDIT_LOG_PATH = '/var/log/labs/labsctl_service_audit.log'
MUTATION_ACTIONS = frozenset({
    'createuser', 'deleteuser', 'createdb', 'deletedb', 'createvhost', 'deletevhost',
})

# ServiceStatusCodes parity with labs/htdocs/src/lib/services/ServiceStatusCodes.class.php
CODE_SUCCESS = 200
CODE_VALIDATION = 400
CODE_USER_NOT_FOUND = 404
CODE_USER_ALREADY_EXISTS = 409
CODE_DB_ALREADY_EXISTS = 410
CODE_USERNAME_INVALID = 411
CODE_PASSWORD_MISSING = 412
CODE_DB_NAME_MISSING = 420
CODE_DB_NAME_INVALID = 421
CODE_OPERATION_FAILED = 500

SYSTEM_MYSQL_DBS = {'information_schema', 'mysql', 'performance_schema', 'sys'}
SYSTEM_MONGO_DBS = {'admin', 'local', 'config', 'tom_labs_db'}
SYSTEM_PG_DBS = {'postgres'}
PROTECTED_MONGO_DBS = {'admin', 'local', 'config', 'tom_labs_db'}


class Fail(Exception):
    def __init__(self, code, message):
        super().__init__(message)
        self.code = code
        self.message = message


def _emit(status, code, message, data=None):
    print(json.dumps({'status': status, 'code': code, 'message': message, 'data': data}))
    sys.exit(0 if status == 'success' else 1)


def _ok(message='Success', data=None):
    _emit('success', CODE_SUCCESS, message, data)


def _err(code, message):
    _emit('error', code, message)


def _audit_request(email, slug, action, args):
    """Append a mutation audit line (best effort — never blocks the op)."""
    target = (
        args.flag('username') or args.flag('dbname') or args.flag('database')
        or args.flag('vhost') or '-'
    )
    stamp = datetime.datetime.now(datetime.timezone.utc).strftime('%Y-%m-%dT%H:%M:%SZ')
    line = '{} email={} service={} action={} target={}\n'.format(
        stamp, email or '-', slug, action, target
    )
    # The log is shared by root (CLI) and www-data (Apache) — create it
    # world-writable so both can append, and relax perms on legacy files.
    umask = os.umask(0)
    try:
        fd = os.open(AUDIT_LOG_PATH, os.O_WRONLY | os.O_CREAT | os.O_APPEND, 0o666)
        try:
            os.write(fd, line.encode())
        finally:
            os.close(fd)
    except OSError:
        try:
            os.chmod(AUDIT_LOG_PATH, 0o666)
            with open(AUDIT_LOG_PATH, 'a') as fh:
                fh.write(line)
        except OSError:
            pass
    finally:
        os.umask(umask)


_env_cache = None


def _load_env():
    global _env_cache
    if _env_cache is None:
        here = os.path.realpath(__file__)
        lcp_root = os.path.dirname(os.path.dirname(os.path.dirname(here)))  # labs-control-panel/
        candidates = [
            os.path.join(os.path.dirname(lcp_root), 'env.json'),
            '/var/www/env.json',
        ]
        data = {}
        for path in candidates:
            try:
                with open(path, 'r') as fh:
                    data = json.load(fh)
                break
            except Exception:
                continue
        _env_cache = data
    return _env_cache


def _cfg(key, default=''):
    return _load_env().get(key) or default


def _svc(slug, key, default=None):
    """Per-slug connection settings from the dedicated credentials file.

    Reads /opt/labs-control-panel/service-credentials.json (chmod 600, git-
    ignored) so secrets stay out of env.json. Falls back to env.json
    service_endpoints (legacy) and then to `default` (legacy 127.0.0.1
    all-in-one behaviour) when absent.
    """
    candidates = [
        '/opt/labs-control-panel/service-credentials.json',
        '/var/www/service-credentials.json',
    ]
    for path in candidates:
        try:
            with open(path, 'r') as fh:
                data = json.load(fh)
            break
        except Exception:
            continue
    else:
        data = {}
    if not isinstance(data, dict):
        data = {}
    endpoint = data.get(slug)
    if not isinstance(endpoint, dict):
        # Legacy fallback: service_endpoints inside env.json
        endpoints = _load_env().get('service_endpoints')
        endpoint = endpoints.get(slug) if isinstance(endpoints, dict) else None
    if not isinstance(endpoint, dict):
        return default
    value = endpoint.get(key)
    return default if value is None else value


def _run(cmd, env_extra=None, timeout=30):
    """Run a command. On the host, wrap with docker exec into the Dev_lab container.

    Returns (returncode, stdout, stderr).
    """
    env = dict(os.environ)
    if env_extra:
        env.update(env_extra)
    run_cmd = list(cmd)
    if not IN_CONTAINER:
        docker_cmd = ['docker', 'exec']
        for key in (env_extra or {}):
            docker_cmd += ['-e', '{}={}'.format(key, env_extra[key])]
        docker_cmd.append(DOCKER_CONTAINER)
        run_cmd = docker_cmd + run_cmd
    try:
        proc = subprocess.run(run_cmd, env=env, capture_output=True, text=True, timeout=timeout)
    except FileNotFoundError:
        raise Fail(CODE_OPERATION_FAILED, 'Command not available: {}'.format(cmd[0]))
    except subprocess.TimeoutExpired:
        raise Fail(CODE_OPERATION_FAILED, 'Engine command timed out after {}s'.format(timeout))
    return proc.returncode, proc.stdout or '', proc.stderr or ''


def _require_ident(name, what='identifier', max_len=64):
    if not name:
        raise Fail(CODE_DB_NAME_MISSING, '{} is required.'.format(what))
    if not IDENT_RE.match(name) or len(name) > max_len:
        raise Fail(CODE_DB_NAME_INVALID, 'Invalid {}: only letters, digits and underscores.'.format(what))
    return name


def _require_vhost(name):
    if not name:
        raise Fail(CODE_DB_NAME_MISSING, 'Vhost name is required.')
    if not VHOST_RE.match(name) or len(name) > 64:
        raise Fail(CODE_DB_NAME_INVALID, 'Invalid vhost name: letters, digits, underscores, dots and hyphens only.')
    return name


def _require_password(password):
    if not password:
        raise Fail(CODE_PASSWORD_MISSING, 'Password is required.')
    return password


def _sql_quote(value):
    return value.replace('\\', '\\\\').replace("'", "''")


def _pg_dollar_quote(value):
    tag = 'labs' + uuid.uuid4().hex
    return '${}${}${}$'.format(tag, value, tag)


# ── MySQL / MariaDB ──────────────────────────────────────────────────────────

def _mysql_exec(slug, sql, fetch=False):
    password = _svc(slug, 'password', _cfg('mysql_root_pass'))
    if password is None or password == '':
        password = _cfg('mysql_root_pass')
    if not password:
        raise Fail(CODE_OPERATION_FAILED, 'MySQL root password missing in env.json')
    user = _svc(slug, 'user', _cfg('mysql_root_user', 'root'))
    host = _svc(slug, 'host', '127.0.0.1')
    port = _svc(slug, 'port', MYSQL_PORT.get(slug, 3306))
    env = {'MYSQL_PWD': password}
    cmd = ['mysql', '-h', str(host), '-P', str(port), '-u', user, '-N', '-B', '-e', sql]
    rc, out, err = _run(cmd, env)
    if fetch:
        return rc, out.strip(), (out + err).strip()
    return rc, out.strip(), (out + err).strip()


def _mysql_ensure_ok(rc, err, context):
    if rc != 0:
        raise Fail(CODE_OPERATION_FAILED, '{}: {}'.format(context, err[:300]))


def _valid_hosts(hosts_flag):
    """Parse and validate a comma-separated MySQL host whitelist."""
    if not hosts_flag:
        return []
    if not isinstance(hosts_flag, str):
        raise Fail(CODE_VALIDATION, 'Invalid --hosts value.')
    hosts = []
    for part in hosts_flag.split(','):
        host = part.strip()
        if not host or not HOST_RE.match(host):
            raise Fail(CODE_VALIDATION, 'Invalid host pattern in --hosts: {}'.format(host[:64]))
        hosts.append(host)
    return list(dict.fromkeys(hosts))


def mysql_create_user(slug, username, password, hosts=None):
    _require_ident(username, 'username')
    _require_password(password)
    host_list = _valid_hosts(hosts) or ['%']
    for host in host_list:
        rc, out, err = _mysql_exec(
            slug,
            "SELECT 1 FROM mysql.user WHERE user = '{}' AND host = '{}'".format(username, host),
            fetch=True,
        )
        if out == '1':
            raise Fail(
                CODE_USER_ALREADY_EXISTS,
                "User {}@'{}' already exists.".format(username, host),
            )
    for host in host_list:
        sql = "CREATE USER '{}'@'{}' IDENTIFIED BY '{}'; FLUSH PRIVILEGES".format(
            username, host, _sql_quote(password)
        )
        rc, out, err = _mysql_exec(slug, sql)
        _mysql_ensure_ok(rc, err, 'Failed to create MySQL user {}@{}'.format(username, host))
        # Per-user namespace: the user may create/manage their own databases
        # from Adminer/CLI (names starting with <username>_). Databases are
        # still created per-user via the dashboard (quota-tracked) with exact
        # names. The _ is escaped (\_) so it is not a GRANT wildcard.
        grant_sql = "GRANT ALL PRIVILEGES ON `{u}\\_%`.* TO '{u}'@'{h}'; FLUSH PRIVILEGES".format(
            u=username, h=host
        )
        rc, out, err = _mysql_exec(slug, grant_sql)
        _mysql_ensure_ok(rc, err, 'Failed to grant namespace to {}@{}'.format(username, host))
    return "User {} created for {}.".format(username, ', '.join(host_list))


def mysql_delete_user(slug, username, hosts=None):
    _require_ident(username, 'username')
    host_list = _valid_hosts(hosts) or ['%']
    for host in host_list:
        rc, out, err = _mysql_exec(
            slug,
            "DROP USER IF EXISTS '{}'@'{}'; FLUSH PRIVILEGES".format(username, host),
        )
        _mysql_ensure_ok(rc, err, "Failed to drop MySQL user {}@'{}'".format(username, host))
    return 'User {} dropped.'.format(username)


def mysql_list_users(slug):
    rc, out, err = _mysql_exec(
        slug,
        "SELECT user, host FROM mysql.user WHERE host IN ('%') OR host LIKE '10.20.%' ORDER BY user, host",
        fetch=True,
    )
    _mysql_ensure_ok(rc, err, 'Failed to list MySQL users')
    return [line for line in out.splitlines() if line]


def mysql_create_database(slug, dbname, owner, collation, charset='', hosts=None):
    _require_ident(dbname, 'database name')
    _require_ident(owner, 'owner username')
    if not COLLATION_RE.match(collation or ''):
        raise Fail(CODE_DB_NAME_INVALID, 'Invalid collation.')
    if charset and not COLLATION_RE.match(charset):
        raise Fail(CODE_DB_NAME_INVALID, 'Invalid charset.')
    rc, out, err = _mysql_exec(
        slug,
        "SELECT host FROM mysql.user WHERE user = '{}'".format(owner),
        fetch=True,
    )
    _mysql_ensure_ok(rc, err, 'Failed to query owner user {}'.format(owner))
    owner_hosts = [line.strip() for line in out.splitlines() if line.strip()]
    provided = _valid_hosts(hosts)
    if provided:
        host_list = [h for h in provided if h in owner_hosts]
    else:
        host_list = owner_hosts
    if not host_list:
        raise Fail(
            CODE_USER_NOT_FOUND,
            "Owner user {} does not exist for {}.".format(
                owner, ', '.join("'{}'@'{}'".format(owner, h) for h in (provided or ['%']))
            ),
        )
    rc, out, err = _mysql_exec(
        slug,
        "SELECT 1 FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '{}'".format(dbname),
        fetch=True,
    )
    if out == '1':
        # Idempotent re-run (e.g. after a user was recreated on redeploy):
        # refresh the owner's grants instead of failing.
        grants = '; '.join(
            "GRANT ALL PRIVILEGES ON `{}`.* TO '{}'@'{}'".format(dbname, owner, host)
            for host in host_list
        )
        rc, out, err = _mysql_exec(slug, grants + '; FLUSH PRIVILEGES')
        _mysql_ensure_ok(rc, err, 'Failed to grant {} to {}'.format(dbname, owner))
        return 'Database {} already exists; grants refreshed for {}.'.format(dbname, owner)
    sql_create = "CREATE DATABASE `{}`".format(dbname)
    if charset:
        sql_create += " CHARACTER SET `{}`".format(charset)
    grants = '; '.join(
        "GRANT ALL PRIVILEGES ON `{}`.* TO '{}'@'{}'".format(dbname, owner, host)
        for host in host_list
    )
    sql = "{} COLLATE `{}`; {}; FLUSH PRIVILEGES".format(sql_create, collation, grants)
    rc, out, err = _mysql_exec(slug, sql)
    _mysql_ensure_ok(rc, err, 'Failed to create database {}'.format(dbname))
    return 'Database {} created and granted to {}.'.format(dbname, owner)


def mysql_delete_database(slug, dbname):
    _require_ident(dbname, 'database name')
    rc, out, err = _mysql_exec(slug, 'DROP DATABASE IF EXISTS `{}`'.format(dbname))
    _mysql_ensure_ok(rc, err, 'Failed to drop database {}'.format(dbname))
    return 'Database {} dropped.'.format(dbname)


def mysql_list_databases(slug):
    rc, out, err = _mysql_exec(slug, 'SHOW DATABASES', fetch=True)
    _mysql_ensure_ok(rc, err, 'Failed to list databases')
    return [line for line in out.splitlines() if line and line.lower() not in SYSTEM_MYSQL_DBS]


def mysql_get_collations(slug):
    rc, out, err = _mysql_exec(slug, 'SHOW COLLATION', fetch=True)
    _mysql_ensure_ok(rc, err, 'Failed to list collations')
    collations = {}
    for line in out.splitlines():
        parts = line.split('\t')
        # SHOW COLLATION columns: Collation, Charset, … → group by charset
        # (same shape as the old MySqlDriver/MariaDbDriver getCollations()).
        if len(parts) >= 2 and parts[1]:
            collations.setdefault(parts[1], []).append(parts[0])
    if not collations:
        raise Fail(CODE_OPERATION_FAILED, 'No collations returned by server.')
    return collations


# ── MongoDB ──────────────────────────────────────────────────────────────────

def _mongo_eval(js):
    host = _svc('mongodb', 'host', '127.0.0.1')
    port = _svc('mongodb', 'port', 27017)
    user = _svc('mongodb', 'user', _cfg('mongo_user', 'admin'))
    password = _svc('mongodb', 'password', _cfg('mongo_pass'))
    if not password:
        raise Fail(CODE_OPERATION_FAILED, 'mongo_pass missing in env.json')
    uri = 'mongodb://{}:{}@{}:{}/?authSource=admin'.format(user, password, host, port)
    env = {'MLAB_URI': uri}
    wrapped = 'print(JSON.stringify((function () {{\n{}\n}})()));'.format(js)
    rc, out, err = _run(['mongosh', '--quiet', '--eval', wrapped], env)
    lines = [line.strip() for line in out.splitlines() if line.strip()]
    envelope = None
    for line in reversed(lines):
        try:
            candidate = json.loads(line)
            if isinstance(candidate, dict) and 'code' in candidate:
                envelope = candidate
                break
        except Exception:
            continue
    if envelope is None:
        raise Fail(CODE_OPERATION_FAILED, 'mongosh failed: {}'.format((out + err).strip()[:300] or 'no output'))
    return envelope


def _mongo_require(result):
    code = int(result.get('code', CODE_OPERATION_FAILED))
    if code < 200 or code >= 300:
        raise Fail(code, result.get('message') or 'Operation failed.')
    return result.get('data')


def mongo_create_user(username, password):
    _require_ident(username, 'username')
    _require_password(password)
    # Created in the admin db (authSource=admin) with NO roles. Access is
    # granted only when the user creates a database (mongo_create_database
    # grants dbOwner on that database). Never grant any-database roles to
    # learner users.
    js = """
const admin = new Mongo(process.env.MLAB_URI).getDB('admin');
const U = {username};
const P = {password};
try {{
    const info = admin.runCommand({{usersInfo: {{user: U, db: 'admin'}}}});
    if (info.users && info.users.length > 0) {{
        return {{code: 409, message: 'User ' + U + ' already exists.'}};
    }}
    admin.runCommand({{
        createUser: U,
        pwd: P,
        roles: []
    }});
    return {{code: 200, message: 'User ' + U + ' created.'}};
}} catch (e) {{
    return {{code: 500, message: 'Failed to create user: ' + e.message}};
}}
""".format(username=json.dumps(username), password=json.dumps(password))
    _mongo_require(_mongo_eval(js))
    return 'User {} created.'.format(username)


def mongo_delete_user(username):
    _require_ident(username, 'username')
    js = """
const admin = new Mongo(process.env.MLAB_URI).getDB('admin');
const U = {username};
try {{
    admin.runCommand({{dropUser: U}});
    return {{code: 200, message: 'User ' + U + ' dropped.'}};
}} catch (e) {{
    return {{code: 404, message: 'Failed to drop user ' + U + ': ' + e.message}};
}}
""".format(username=json.dumps(username))
    _mongo_require(_mongo_eval(js))
    return 'User {} dropped.'.format(username)


def mongo_list_users():
    js = """
const admin = new Mongo(process.env.MLAB_URI).getDB('admin');
const info = admin.runCommand({usersInfo: 1});
const names = (info.users || []).map(function (u) { return u.user; }).sort();
return {code: 200, message: 'ok', data: names};
"""
    return _mongo_require(_mongo_eval(js))


def mongo_create_database(dbname, owner):
    _require_ident(dbname, 'database name')
    _require_ident(owner, 'owner username')
    js = """
const admin = new Mongo(process.env.MLAB_URI).getDB('admin');
const DB = {dbname};
const OWNER = {owner};
try {{
    const target = new Mongo(process.env.MLAB_URI).getDB(DB);
    const cols = target.getCollectionInfos();
    if (cols.length > 0) {{
        admin.runCommand({{grantRolesToUser: OWNER, roles: [{{role: 'dbOwner', db: DB}}]}});
        return {{code: 200, message: 'Database ' + DB + ' already exists; access refreshed for ' + OWNER + '.'}};
    }}
    target.createCollection('_init');
    target.createCollection('welcome');
    target.welcome.insertOne({{message: 'Welcome to TomCloud Lab MongoDB', owner: OWNER, created_at: new Date().toISOString()}});
    admin.runCommand({{grantRolesToUser: OWNER, roles: [{{role: 'dbOwner', db: DB}}]}});
    return {{code: 200, message: 'Database ' + DB + ' created.'}};
}} catch (e) {{
    return {{code: 500, message: 'Failed to create database ' + DB + ': ' + e.message}};
}}
""".format(dbname=json.dumps(dbname), owner=json.dumps(owner))
    _mongo_require(_mongo_eval(js))
    return 'Database {} created.'.format(dbname)


def mongo_delete_database(dbname):
    _require_ident(dbname, 'database name')
    if dbname in PROTECTED_MONGO_DBS:
        raise Fail(CODE_VALIDATION, 'Database {} is protected and cannot be dropped.'.format(dbname))
    js = """
const client = new Mongo(process.env.MLAB_URI);
const DB = {dbname};
try {{
    client.getDB(DB).dropDatabase();
    return {{code: 200, message: 'Database ' + DB + ' dropped.'}};
}} catch (e) {{
    return {{code: 500, message: 'Failed to drop database ' + DB + ': ' + e.message}};
}}
""".format(dbname=json.dumps(dbname))
    _mongo_require(_mongo_eval(js))
    return 'Database {} dropped.'.format(dbname)


def mongo_list_databases():
    js = """
const client = new Mongo(process.env.MLAB_URI);
const res = client.getDB('admin').runCommand({{listDatabases: 1, nameOnly: true}});
const skip = {skip};
const names = (res.databases || [])
    .map(function (d) {{ return d.name; }})
    .filter(function (n) {{ return skip.indexOf(n) === -1; }})
    .sort();
return {{code: 200, message: 'ok', data: names}};
""".format(skip=json.dumps(sorted(SYSTEM_MONGO_DBS)))
    return _mongo_require(_mongo_eval(js))


# ── PostgreSQL ───────────────────────────────────────────────────────────────

def _psql(sql, fetch=False):
    host = _svc('postgresql', 'host', '127.0.0.1')
    port = _svc('postgresql', 'port', 5432)
    admin = _svc('postgresql', 'user', _cfg('postgres_admin_user', 'tomlabs_admin'))
    env = {'PGPASSWORD': _svc('postgresql', 'password', _cfg('mysql_root_pass'))}
    if not env['PGPASSWORD']:
        raise Fail(CODE_OPERATION_FAILED, 'PostgreSQL admin password missing in env.json')
    cmd = ['psql', '-h', str(host), '-p', str(port), '-U', admin, '-d', 'postgres', '-v', 'ON_ERROR_STOP=1']
    if fetch:
        cmd += ['-tA']
    cmd += ['-c', sql]
    rc, out, err = _run(cmd, env)
    return rc, out.strip(), (out + err).strip()


def _psql_ensure_ok(rc, err, context):
    if rc != 0:
        raise Fail(CODE_OPERATION_FAILED, '{}: {}'.format(context, err[:300]))


def pg_create_user(username, password):
    _require_ident(username, 'username', max_len=63)
    _require_password(password)
    rc, out, err = _psql("SELECT 1 FROM pg_roles WHERE rolname = '{}'".format(username), fetch=True)
    _psql_ensure_ok(rc, err, 'Failed to query pg_roles')
    if out == '1':
        raise Fail(CODE_USER_ALREADY_EXISTS, 'User {} already exists.'.format(username))
    rc, out, err = _psql(
        'CREATE ROLE "{}" WITH LOGIN PASSWORD {}'.format(username, _pg_dollar_quote(password))
    )
    _psql_ensure_ok(rc, err, 'Failed to create PostgreSQL user {}'.format(username))
    return 'User {} created.'.format(username)


def pg_delete_user(username):
    _require_ident(username, 'username', max_len=63)
    # Mirrors PostgreSqlDriver: revoke first; if the role is missing this fails
    # and the drop never runs.
    rc, out, err = _psql(
        'REVOKE ALL PRIVILEGES ON ALL TABLES IN SCHEMA public FROM "{}"'.format(username)
    )
    _psql_ensure_ok(rc, err, 'Failed to revoke privileges for {}'.format(username))
    rc, out, err = _psql('DROP ROLE IF EXISTS "{}"'.format(username))
    _psql_ensure_ok(rc, err, 'Failed to drop PostgreSQL user {}'.format(username))
    return 'User {} dropped.'.format(username)


def pg_list_users():
    rc, out, err = _psql("SELECT rolname FROM pg_roles WHERE rolcanlogin = true ORDER BY rolname", fetch=True)
    _psql_ensure_ok(rc, err, 'Failed to list PostgreSQL users')
    return [line for line in out.splitlines() if line]


def pg_create_database(dbname, owner):
    _require_ident(dbname, 'database name', max_len=63)
    _require_ident(owner, 'owner username', max_len=63)
    rc, out, err = _psql(
        "SELECT 1 FROM pg_database WHERE datname = '{}'".format(dbname), fetch=True
    )
    _psql_ensure_ok(rc, err, 'Failed to query pg_database')
    if out == '1':
        raise Fail(CODE_DB_ALREADY_EXISTS, 'Database {} already exists.'.format(dbname))
    rc, out, err = _psql(
        "SELECT 1 FROM pg_roles WHERE rolname = '{}'".format(owner), fetch=True
    )
    _psql_ensure_ok(rc, err, 'Failed to query pg_roles')
    if out != '1':
        raise Fail(CODE_USER_NOT_FOUND, 'Owner role {} does not exist.'.format(owner))
    rc, out, err = _psql('CREATE DATABASE "{}" OWNER "{}"'.format(dbname, owner))
    _psql_ensure_ok(rc, err, 'Failed to create database {}'.format(dbname))
    return 'Database {} created and owned by {}.'.format(dbname, owner)


def pg_delete_database(dbname):
    _require_ident(dbname, 'database name', max_len=63)
    rc, out, err = _psql(
        "SELECT pg_terminate_backend(pid) FROM pg_stat_activity "
        "WHERE datname = '{}' AND pid <> pg_backend_pid()".format(dbname),
        fetch=True,
    )
    _psql_ensure_ok(rc, err, 'Failed to terminate connections to {}'.format(dbname))
    rc, out, err = _psql('DROP DATABASE IF EXISTS "{}"'.format(dbname))
    _psql_ensure_ok(rc, err, 'Failed to drop database {}'.format(dbname))
    return 'Database {} dropped.'.format(dbname)


def pg_list_databases():
    rc, out, err = _psql(
        'SELECT datname FROM pg_database WHERE datistemplate = false ORDER BY datname', fetch=True
    )
    _psql_ensure_ok(rc, err, 'Failed to list databases')
    return [line for line in out.splitlines() if line and line not in SYSTEM_PG_DBS]


def pg_get_encodings():
    rc, out, err = _psql(
        'SELECT pg_encoding_to_char(conforencoding) FROM pg_conversion '
        'GROUP BY conforencoding ORDER BY 1',
        fetch=True,
    )
    _psql_ensure_ok(rc, err, 'Failed to list encodings')
    encodings = [line for line in out.splitlines() if line]
    if not encodings:
        encodings = ['UTF8']
    return encodings


# ── Redis ────────────────────────────────────────────────────────────────────

def _redis(*argv):
    host = _svc('redis', 'host', '127.0.0.1')
    port = _svc('redis', 'port', 6379)
    password = _svc('redis', 'password', _cfg('redis_admin_pass'))
    env = {'REDISCLI_AUTH': password} if password else {}
    cmd = ['redis-cli', '-h', str(host), '-p', str(port), '--no-auth-warning'] + list(argv)
    rc, out, err = _run(cmd, env)
    return rc, out.strip(), (out + err).strip()


def redis_create_user(username, password):
    _require_ident(username, 'username')
    _require_password(password)
    rc, out, err = _redis('ACL', 'LIST')
    if rc != 0:
        raise Fail(CODE_OPERATION_FAILED, 'ACL LIST failed: {}'.format(err[:300]))
    for line in out.splitlines():
        if 'user {} '.format(username) in line:
            raise Fail(CODE_USER_ALREADY_EXISTS, 'User {} already exists.'.format(username))
    rc, out, err = _redis('ACL', 'SETUSER', username, 'on', '>{}'.format(password), '~*', '+@all')
    if rc != 0 or out.startswith('ERR'):
        raise Fail(CODE_OPERATION_FAILED, 'Failed to create user {}: {}'.format(username, out[:300]))
    rc, out, err = _redis('ACL', 'SAVE')
    if rc != 0 or out.startswith('ERR'):
        pass  # Redis has no ACL file configured; user lives in memory (consistent with no persistence)
    return 'User {} created.'.format(username)


def redis_delete_user(username):
    _require_ident(username, 'username')
    if username == 'default':
        raise Fail(CODE_VALIDATION, 'Cannot delete the default Redis user.')
    rc, out, err = _redis('ACL', 'DELUSER', username)
    if rc != 0 or out.startswith('ERR'):
        raise Fail(CODE_OPERATION_FAILED, 'Failed to delete user {}: {}'.format(username, out[:300]))
    rc, out, err = _redis('ACL', 'SAVE')
    if rc != 0 or out.startswith('ERR'):
        pass  # Redis has no ACL file configured; deletion lives in memory (consistent with no persistence)
    return 'User {} dropped.'.format(username)


def redis_list_users():
    rc, out, err = _redis('ACL', 'LIST')
    if rc != 0:
        raise Fail(CODE_OPERATION_FAILED, 'ACL LIST failed: {}'.format(err[:300]))
    names = []
    for line in out.splitlines():
        parts = line.split()
        if len(parts) >= 2 and parts[0] == 'user':
            names.append(parts[1])
    return sorted(names)


# ── RabbitMQ ─────────────────────────────────────────────────────────────────

def _rmq_api(method, path, body=None):
    """Call the RabbitMQ management HTTP API (separate rabbitmq container).

    Credentials come from service-credentials.json (slug rabbitmq: host,
    port, user, password). Returns (status, parsed_json_or_text).
    """
    import urllib.request
    import urllib.error
    import base64
    host = _svc('rabbitmq', 'host', 'rabbitmq.tomweb.in')
    port = _svc('rabbitmq', 'port', 15672)
    user = _svc('rabbitmq', 'user', 'admin')
    password = _svc('rabbitmq', 'password', _cfg('rabbit_pass', 'guest'))
    url = 'http://{}:{}/api/{}'.format(host, port, path.lstrip('/'))
    data = json.dumps(body).encode() if body is not None else None
    req = urllib.request.Request(url, data=data, method=method)
    req.add_header('Authorization', 'Basic ' + base64.b64encode(
        '{}:{}'.format(user, password).encode()).decode())
    req.add_header('Content-Type', 'application/json')
    try:
        with urllib.request.urlopen(req, timeout=15) as resp:
            payload = resp.read().decode('utf-8', 'replace')
            status = resp.status
    except urllib.error.HTTPError as exc:
        payload = exc.read().decode('utf-8', 'replace')
        status = exc.code
    except Exception as exc:
        raise Fail(CODE_OPERATION_FAILED, 'RabbitMQ API unreachable: {}'.format(str(exc)[:200]))
    try:
        parsed = json.loads(payload) if payload else None
    except Exception:
        parsed = payload
    return status, parsed


def rabbit_create_user(username, password):
    _require_ident(username, 'username')
    _require_password(password)
    status, users = _rmq_api('GET', 'users')
    if status != 200 or not isinstance(users, list):
        raise Fail(CODE_OPERATION_FAILED, 'list_users failed.')
    if any(u.get('name') == username for u in users):
        raise Fail(CODE_USER_ALREADY_EXISTS, 'User {} already exists.'.format(username))
    status, body = _rmq_api('PUT', 'users/{}'.format(username), {
        'password': password, 'tags': ['management']})
    if status not in (201, 204):
        raise Fail(CODE_OPERATION_FAILED, 'Failed to create user {}: {}'.format(
            username, str(body)[:200]))
    return 'User {} created.'.format(username)


def rabbit_delete_user(username):
    _require_ident(username, 'username')
    if username in ('admin', 'guest'):
        raise Fail(CODE_VALIDATION, 'Cannot delete protected RabbitMQ user: {}'.format(username))
    status, body = _rmq_api('DELETE', 'users/{}'.format(username))
    if status not in (204, 200):
        raise Fail(CODE_OPERATION_FAILED, 'Failed to delete user {}: {}'.format(
            username, str(body)[:200]))
    return 'User {} dropped.'.format(username)


def rabbit_list_users():
    status, users = _rmq_api('GET', 'users')
    if status != 200 or not isinstance(users, list):
        raise Fail(CODE_OPERATION_FAILED, 'list_users failed.')
    return sorted(u.get('name', '') for u in users)


def rabbit_create_vhost(vhost, owner):
    _require_vhost(vhost)
    _require_ident(owner, 'owner username')
    status, body = _rmq_api('PUT', 'vhosts/{}'.format(urllib.parse.quote(vhost, safe='')))
    if status not in (201, 204, 409):
        raise Fail(CODE_OPERATION_FAILED, 'Failed to create vhost {}: {}'.format(
            vhost, str(body)[:200]))
    status, body = _rmq_api(
        'PUT', 'permissions/{}/{}'.format(urllib.parse.quote(vhost, safe=''), owner),
        {'configure': '.*', 'write': '.*', 'read': '.*'})
    if status not in (201, 204):
        raise Fail(CODE_OPERATION_FAILED, 'Failed to grant {}: {}'.format(owner, str(body)[:200]))
    return 'Vhost {} ready for {}.'.format(vhost, owner)


def rabbit_delete_vhost(vhost):
    _require_vhost(vhost)
    if vhost == '/':
        raise Fail(CODE_VALIDATION, 'Cannot delete the default RabbitMQ vhost.')
    status, body = _rmq_api('DELETE', 'vhosts/{}'.format(urllib.parse.quote(vhost, safe='')))
    if status not in (204, 200):
        raise Fail(CODE_OPERATION_FAILED, 'Failed to delete vhost {}: {}'.format(
            vhost, str(body)[:200]))
    return 'Vhost {} dropped.'.format(vhost)


def rabbit_list_vhosts():
    status, vhosts = _rmq_api('GET', 'vhosts')
    if status != 200 or not isinstance(vhosts, list):
        raise Fail(CODE_OPERATION_FAILED, 'list_vhosts failed.')
    return sorted(v.get('name', '') for v in vhosts)


# ── Command ──────────────────────────────────────────────────────────────────

SLUGS = ('mysql', 'mariadb', 'mongodb', 'postgresql', 'redis', 'rabbitmq', 'memcached')

MYSQL_PORT = {'mysql': 3306, 'mariadb': 3307}

MEMCACHED_HOSTS = {'memcached': 'memcached.tomweb.in'}

COMMON_ACTIONS = ('ping', 'createuser', 'deleteuser', 'listuser')

ACTIONS_BY_SLUG = {
    'mysql': COMMON_ACTIONS + ('createdb', 'deletedb', 'listdb', 'getcollation'),
    'mariadb': COMMON_ACTIONS + ('createdb', 'deletedb', 'listdb', 'getcollation'),
    'mongodb': COMMON_ACTIONS + ('createdb', 'deletedb', 'listdb'),
    'postgresql': COMMON_ACTIONS + ('createdb', 'deletedb', 'listdb', 'getencoding'),
    'redis': COMMON_ACTIONS,
    'memcached': ('ping',),
    'rabbitmq': COMMON_ACTIONS + ('createvhost', 'deletevhost', 'listvhost'),
}


class ServiceCmd(Command):
    """labsctl service — learner service engine operations."""

    name = 'service'
    description = 'Learner service engine operations (users, databases, vhosts)'
    usage = 'labsctl service <slug> <action> [options]'

    def __init__(self, router=None):
        super().__init__()

    def handle(self, args):
        if args.help or not args.get(0):
            self._usage_error(None)
        slug = args.get(0)
        action = args.get(1)

        if slug not in SLUGS:
            if slug == self.name:
                # Router quirk: Args([]) re-parses sys.argv, so a bare
                # `labsctl service` arrives with the group name as get(0).
                self._usage_error(None)
            _err(CODE_VALIDATION, 'Unknown service: {}. Slugs: {}'.format(slug, ', '.join(SLUGS)))
        if not action:
            self._usage_error('Action missing for service {}.'.format(slug))
        if action not in ACTIONS_BY_SLUG[slug]:
            _err(
                CODE_VALIDATION,
                'Action {} is not supported for {}. Supported: {}'.format(
                    action, slug, ', '.join(ACTIONS_BY_SLUG[slug])
                ),
            )

        # --email is interpolated into PHP-side command strings and used for
        # audit trails — validate its shape whenever present.
        email = args.flag('email')
        if isinstance(email, str):
            email = email.strip()
            if email and not EMAIL_FLAG_RE.match(email):
                _err(CODE_VALIDATION, 'Invalid --email value.')
        elif email is not None:
            _err(CODE_VALIDATION, 'Invalid --email value.')
        else:
            email = ''

        if action in MUTATION_ACTIONS:
            _audit_request(email, slug, action, args)

        try:
            self._dispatch(slug, action, args)
        except Fail as fail:
            _err(fail.code, fail.message)
        except Exception as unexpected:  # noqa: BLE001 — must always emit JSON
            _err(CODE_OPERATION_FAILED, 'labsctl service failed: {}'.format(unexpected))

    def _usage_error(self, message):
        text = [
            'Usage: labsctl service <slug> <action> [options]',
            '',
            'Slugs:    ' + ', '.join(SLUGS),
            'Actions:  ' + ', '.join(sorted({a for acts in ACTIONS_BY_SLUG.values() for a in acts})),
            'Options:  --email=EMAIL --username=NAME --password=PASS',
            '          --database=NAME (alias: --dbname) --charset=NAME --collation=NAME --vhost=NAME',
            '          --hosts=H1,H2 (mysql/mariadb: client-host whitelist)',
        ]
        if message:
            text.insert(0, 'Error: ' + message)
        print('\n'.join(text), file=sys.stderr)
        sys.exit(2)

    def _dispatch(self, slug, action, args):
        username = args.flag('username')
        password = args.flag('password')
        dbname = args.flag('dbname') or args.flag('database')
        collation = args.flag('collation')
        vhost = args.flag('vhost')
        hosts = args.flag('hosts')

        if action == 'ping':
            self._ping(slug)
            return

        if action == 'createuser':
            if slug in MYSQL_PORT:
                message = mysql_create_user(slug, username, password, hosts)
            elif slug == 'mongodb':
                message = mongo_create_user(username, password)
            elif slug == 'postgresql':
                message = pg_create_user(username, password)
            elif slug == 'redis':
                message = redis_create_user(username, password)
            else:
                message = rabbit_create_user(username, password)
            _ok(message)
            return

        if action == 'deleteuser':
            if slug in MYSQL_PORT:
                message = mysql_delete_user(slug, username, hosts)
            elif slug == 'mongodb':
                message = mongo_delete_user(username)
            elif slug == 'postgresql':
                message = pg_delete_user(username)
            elif slug == 'redis':
                message = redis_delete_user(username)
            else:
                message = rabbit_delete_user(username)
            _ok(message)
            return

        if action == 'listuser':
            if slug in MYSQL_PORT:
                users = mysql_list_users(slug)
            elif slug == 'mongodb':
                users = mongo_list_users()
            elif slug == 'postgresql':
                users = pg_list_users()
            elif slug == 'redis':
                users = redis_list_users()
            else:
                users = rabbit_list_users()
            _ok('Users retrieved.', users)
            return

        if action == 'createdb':
            default_collation = {
                'mysql': 'utf8mb4_0900_ai_ci',
                'mariadb': 'utf8mb4_general_ci',
                'postgresql': 'UTF8',
            }.get(slug, '')
            charset = args.flag('charset') or ''
            if charset and (not isinstance(charset, str) or not COLLATION_RE.match(charset)):
                raise Fail(CODE_DB_NAME_INVALID, 'Invalid charset.')
            if slug == 'mongodb':
                message = mongo_create_database(dbname, username)
            elif slug == 'postgresql':
                message = pg_create_database(dbname, username)
            else:
                message = mysql_create_database(
                    slug, dbname, username,
                    collation or default_collation, charset, hosts,
                )
            _ok(message)
            return

        if action == 'deletedb':
            if slug == 'mongodb':
                message = mongo_delete_database(dbname)
            elif slug == 'postgresql':
                message = pg_delete_database(dbname)
            else:
                message = mysql_delete_database(slug, dbname)
            _ok(message)
            return

        if action == 'listdb':
            if slug == 'mongodb':
                databases = mongo_list_databases()
            elif slug == 'postgresql':
                databases = pg_list_databases()
            else:
                databases = mysql_list_databases(slug)
            _ok('Databases retrieved.', databases)
            return

        if action == 'getcollation':
            _ok('Collations retrieved.', mysql_get_collations(slug))
            return

        if action == 'getencoding':
            _ok('Encodings retrieved.', pg_get_encodings())
            return

        if action == 'createvhost':
            _ok(rabbit_create_vhost(vhost, username))
            return

        if action == 'deletevhost':
            _ok(rabbit_delete_vhost(vhost))
            return

        if action == 'listvhost':
            _ok('Vhosts retrieved.', rabbit_list_vhosts())
            return

        self._usage_error('Unhandled action {}.'.format(action))

    def _ping(self, slug):
        if slug in MYSQL_PORT:
            rc, out, err = _mysql_exec(slug, 'SELECT 1')
            _mysql_ensure_ok(rc, (err or out), 'Ping failed')
        elif slug == 'mongodb':
            result = _mongo_eval(
                "return {code: 200, message: 'ok', data: (new Mongo(process.env.MLAB_URI)"
                ".getDB('admin').runCommand({ping: 1})).ok};"
            )
            _mongo_require(result)
        elif slug == 'postgresql':
            rc, out, err = _psql('SELECT 1')
            _psql_ensure_ok(rc, (err or out), 'Ping failed')
        elif slug == 'redis':
            rc, out, _ = _redis('PING')
            if rc != 0 or 'PONG' not in out:
                raise Fail(CODE_OPERATION_FAILED, 'Redis ping failed: {}'.format(out[:200]))
        elif slug == 'memcached':
            import socket
            host = MEMCACHED_HOSTS.get(slug, 'memcached.tomweb.in')
            try:
                with socket.create_connection((host, 11211), timeout=5) as _sock:
                    _sock.sendall(b'version\r\n')
                    if b'VERSION' not in _sock.recv(128):
                        raise Fail(CODE_OPERATION_FAILED, 'Memcached ping failed: unexpected response')
            except OSError as _e:
                raise Fail(CODE_OPERATION_FAILED, 'Memcached ping failed: {}'.format(_e))
        else:
            status, _ = _rmq_api('GET', 'overview')
            if status != 200:
                raise Fail(CODE_OPERATION_FAILED, 'RabbitMQ ping failed: HTTP {}'.format(status))
        _ok('{} is reachable.'.format(slug))
