import datetime
import json
import os
import re
import subprocess
import sys
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

def _mysql_exec(port, sql, fetch=False):
    env = {'MYSQL_PWD': _cfg('mysql_root_pass')}
    if not env['MYSQL_PWD']:
        raise Fail(CODE_OPERATION_FAILED, 'mysql_root_pass missing in env.json')
    user = _cfg('mysql_root_user', 'root')
    cmd = ['mysql', '-h', '127.0.0.1', '-P', str(port), '-u', user, '-N', '-B', '-e', sql]
    rc, out, err = _run(cmd, env)
    if fetch:
        return rc, out.strip(), (out + err).strip()
    return rc, out.strip(), (out + err).strip()


def _mysql_ensure_ok(rc, err, context):
    if rc != 0:
        raise Fail(CODE_OPERATION_FAILED, '{}: {}'.format(context, err[:300]))


def mysql_create_user(port, username, password):
    _require_ident(username, 'username')
    _require_password(password)
    rc, out, err = _mysql_exec(port, "SELECT 1 FROM mysql.user WHERE user = '{}' AND host = '%'".format(username), fetch=True)
    if out == '1':
        raise Fail(CODE_USER_ALREADY_EXISTS, "User {}@'%' already exists.".format(username))
    sql = "CREATE USER '{}'@'%' IDENTIFIED BY '{}'; FLUSH PRIVILEGES".format(username, _sql_quote(password))
    rc, out, err = _mysql_exec(port, sql)
    _mysql_ensure_ok(rc, err, 'Failed to create MySQL user {}'.format(username))
    return 'User {} created.'.format(username)


def mysql_delete_user(port, username):
    _require_ident(username, 'username')
    rc, out, err = _mysql_exec(port, "DROP USER IF EXISTS '{}'@'%'; FLUSH PRIVILEGES".format(username))
    _mysql_ensure_ok(rc, err, 'Failed to drop MySQL user {}'.format(username))
    return 'User {} dropped.'.format(username)


def mysql_list_users(port):
    rc, out, err = _mysql_exec(port, "SELECT user FROM mysql.user WHERE host = '%' ORDER BY user", fetch=True)
    _mysql_ensure_ok(rc, err, 'Failed to list MySQL users')
    return [line for line in out.splitlines() if line]


def mysql_create_database(port, dbname, owner, collation, charset=''):
    _require_ident(dbname, 'database name')
    _require_ident(owner, 'owner username')
    if not COLLATION_RE.match(collation or ''):
        raise Fail(CODE_DB_NAME_INVALID, 'Invalid collation.')
    if charset and not COLLATION_RE.match(charset):
        raise Fail(CODE_DB_NAME_INVALID, 'Invalid charset.')
    rc, out, err = _mysql_exec(
        port,
        "SELECT 1 FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '{}'".format(dbname),
        fetch=True,
    )
    if out == '1':
        raise Fail(CODE_DB_ALREADY_EXISTS, 'Database {} already exists.'.format(dbname))
    rc, out, err = _mysql_exec(
        port,
        "SELECT 1 FROM mysql.user WHERE user = '{}' AND host = '%'".format(owner),
        fetch=True,
    )
    if out != '1':
        raise Fail(CODE_USER_NOT_FOUND, "Owner user {}@'%' does not exist.".format(owner))
    sql_create = "CREATE DATABASE `{}`".format(dbname)
    if charset:
        sql_create += " CHARACTER SET `{}`".format(charset)
    sql = (
        "{} COLLATE `{}`; "
        "GRANT ALL PRIVILEGES ON `{}`.* TO '{}'@'%'; "
        "FLUSH PRIVILEGES"
    ).format(sql_create, collation, dbname, owner)
    rc, out, err = _mysql_exec(port, sql)
    _mysql_ensure_ok(rc, err, 'Failed to create database {}'.format(dbname))
    return 'Database {} created and granted to {}.'.format(dbname, owner)


def mysql_delete_database(port, dbname):
    _require_ident(dbname, 'database name')
    rc, out, err = _mysql_exec(port, 'DROP DATABASE IF EXISTS `{}`'.format(dbname))
    _mysql_ensure_ok(rc, err, 'Failed to drop database {}'.format(dbname))
    return 'Database {} dropped.'.format(dbname)


def mysql_list_databases(port):
    rc, out, err = _mysql_exec(port, 'SHOW DATABASES', fetch=True)
    _mysql_ensure_ok(rc, err, 'Failed to list databases')
    return [line for line in out.splitlines() if line and line.lower() not in SYSTEM_MYSQL_DBS]


def mysql_get_collations(port):
    rc, out, err = _mysql_exec(port, 'SHOW COLLATION', fetch=True)
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
    user = _cfg('mongo_user', 'admin')
    password = _cfg('mongo_pass')
    if not password:
        raise Fail(CODE_OPERATION_FAILED, 'mongo_pass missing in env.json')
    uri = 'mongodb://{}:{}@127.0.0.1:27017/?authSource=admin'.format(user, password)
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
        roles: [
            {{role: 'readWriteAnyDatabase', db: 'admin'}},
            {{role: 'dbAdminAnyDatabase', db: 'admin'}}
        ]
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
    const cols = target.listCollections().toArray();
    if (cols.length > 0) {{
        return {{code: 410, message: 'Database ' + DB + ' already exists.'}};
    }}
    target.createCollection('_init');
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
    env = {'PGPASSWORD': _cfg('mysql_root_pass')}
    if not env['PGPASSWORD']:
        raise Fail(CODE_OPERATION_FAILED, 'mysql_root_pass missing in env.json')
    admin = _cfg('postgres_admin_user', 'tomlabs_admin')
    cmd = ['psql', '-h', '127.0.0.1', '-p', '5432', '-U', admin, '-d', 'postgres', '-v', 'ON_ERROR_STOP=1']
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
    password = _cfg('redis_admin_pass')
    if not password:
        raise Fail(CODE_OPERATION_FAILED, 'redis_admin_pass missing in env.json')
    env = {'REDISCLI_AUTH': password}
    cmd = ['redis-cli', '-h', '127.0.0.1', '-p', '6379', '--no-auth-warning'] + list(argv)
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
        raise Fail(CODE_OPERATION_FAILED, 'Failed to persist ACL: {}'.format(out[:300]))
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
        raise Fail(CODE_OPERATION_FAILED, 'Failed to persist ACL: {}'.format(out[:300]))
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

def _rabbit(*argv):
    # rabbitmqctl only accepts root or the rabbitmq OS user, and the Erlang
    # cookie must be owner-only — escalate via the scoped NOPASSWD sudo rule
    # (same as RabbitMqDriver).
    cmd = ['sudo', '-n', '-u', 'rabbitmq', '-H', 'rabbitmqctl'] + list(argv)
    rc, out, err = _run(cmd)
    return rc, out.strip(), (out + err).strip()


def _rabbit_json(stdout):
    # rabbitmqctl may print warnings before the payload; find the JSON.
    for raw in (stdout,):
        stripped = raw.strip()
        for opener in ('[', '{'):
            idx = stripped.find(opener)
            if idx >= 0:
                try:
                    return json.loads(stripped[idx:])
                except Exception:
                    continue
    return None


def rabbit_create_user(username, password):
    _require_ident(username, 'username')
    _require_password(password)
    rc, out, err = _rabbit('list_users', '--formatter', 'json')
    if rc != 0:
        raise Fail(CODE_OPERATION_FAILED, 'list_users failed: {}'.format(err[:300]))
    users = _rabbit_json(out)
    if isinstance(users, list):
        for u in users:
            if (u.get('user') if isinstance(u, dict) else u) == username:
                raise Fail(CODE_USER_ALREADY_EXISTS, 'User {} already exists.'.format(username))
    rc, out, err = _rabbit('add_user', username, password)
    if rc != 0:
        raise Fail(CODE_OPERATION_FAILED, 'Failed to create user {}: {}'.format(username, err[:300]))
    rc, out, err = _rabbit('set_user_tags', username, 'management')
    if rc != 0:
        raise Fail(CODE_OPERATION_FAILED, 'Failed to set tags for {}: {}'.format(username, err[:300]))
    return 'User {} created.'.format(username)


def rabbit_delete_user(username):
    _require_ident(username, 'username')
    if username in ('admin', 'guest'):
        raise Fail(CODE_VALIDATION, 'Cannot delete protected RabbitMQ user: {}'.format(username))
    rc, out, err = _rabbit('delete_user', username)
    if rc != 0:
        raise Fail(CODE_OPERATION_FAILED, 'Failed to delete user {}: {}'.format(username, err[:300]))
    return 'User {} dropped.'.format(username)


def rabbit_list_users():
    rc, out, err = _rabbit('list_users', '--formatter', 'json')
    if rc != 0:
        raise Fail(CODE_OPERATION_FAILED, 'list_users failed: {}'.format(err[:300]))
    users = _rabbit_json(out)
    if not isinstance(users, list):
        raise Fail(CODE_OPERATION_FAILED, 'Unexpected list_users output.')
    return sorted((u.get('user') if isinstance(u, dict) else u) for u in users)


def rabbit_create_vhost(vhost, owner):
    _require_vhost(vhost)
    _require_ident(owner, 'owner username')
    rc, out, err = _rabbit('list_vhosts', '--formatter', 'json')
    if rc != 0:
        raise Fail(CODE_OPERATION_FAILED, 'list_vhosts failed: {}'.format(err[:300]))
    vhosts = _rabbit_json(out)
    if isinstance(vhosts, list):
        for v in vhosts:
            if (v.get('name') if isinstance(v, dict) else v) == vhost:
                raise Fail(CODE_DB_ALREADY_EXISTS, 'Vhost {} already exists.'.format(vhost))
    rc, out, err = _rabbit('add_vhost', vhost)
    if rc != 0:
        raise Fail(CODE_OPERATION_FAILED, 'Failed to create vhost {}: {}'.format(vhost, err[:300]))
    rc, out, err = _rabbit('set_permissions', '-p', vhost, owner, '.*', '.*', '.*')
    if rc != 0:
        raise Fail(CODE_OPERATION_FAILED, 'Failed to grant {}: {}'.format(owner, err[:300]))
    return 'Vhost {} created and granted to {}.'.format(vhost, owner)


def rabbit_delete_vhost(vhost):
    _require_vhost(vhost)
    if vhost == '/':
        raise Fail(CODE_VALIDATION, 'Cannot delete the default RabbitMQ vhost.')
    rc, out, err = _rabbit('delete_vhost', vhost)
    if rc != 0:
        raise Fail(CODE_OPERATION_FAILED, 'Failed to delete vhost {}: {}'.format(vhost, err[:300]))
    return 'Vhost {} dropped.'.format(vhost)


def rabbit_list_vhosts():
    rc, out, err = _rabbit('list_vhosts', '--formatter', 'json')
    if rc != 0:
        raise Fail(CODE_OPERATION_FAILED, 'list_vhosts failed: {}'.format(err[:300]))
    vhosts = _rabbit_json(out)
    if not isinstance(vhosts, list):
        raise Fail(CODE_OPERATION_FAILED, 'Unexpected list_vhosts output.')
    return sorted((v.get('name') if isinstance(v, dict) else v) for v in vhosts)


# ── Command ──────────────────────────────────────────────────────────────────

SLUGS = ('mysql', 'mariadb', 'mongodb', 'postgresql', 'redis', 'rabbitmq')

MYSQL_PORT = {'mysql': 3306, 'mariadb': 3307}

COMMON_ACTIONS = ('ping', 'createuser', 'deleteuser', 'listuser')

ACTIONS_BY_SLUG = {
    'mysql': COMMON_ACTIONS + ('createdb', 'deletedb', 'listdb', 'getcollation'),
    'mariadb': COMMON_ACTIONS + ('createdb', 'deletedb', 'listdb', 'getcollation'),
    'mongodb': COMMON_ACTIONS + ('createdb', 'deletedb', 'listdb'),
    'postgresql': COMMON_ACTIONS + ('createdb', 'deletedb', 'listdb', 'getencoding'),
    'redis': COMMON_ACTIONS,
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

        if action == 'ping':
            self._ping(slug)
            return

        if action == 'createuser':
            if slug in MYSQL_PORT:
                message = mysql_create_user(MYSQL_PORT[slug], username, password)
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
                message = mysql_delete_user(MYSQL_PORT[slug], username)
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
                users = mysql_list_users(MYSQL_PORT[slug])
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
                    MYSQL_PORT[slug], dbname, username,
                    collation or default_collation, charset,
                )
            _ok(message)
            return

        if action == 'deletedb':
            if slug == 'mongodb':
                message = mongo_delete_database(dbname)
            elif slug == 'postgresql':
                message = pg_delete_database(dbname)
            else:
                message = mysql_delete_database(MYSQL_PORT[slug], dbname)
            _ok(message)
            return

        if action == 'listdb':
            if slug == 'mongodb':
                databases = mongo_list_databases()
            elif slug == 'postgresql':
                databases = pg_list_databases()
            else:
                databases = mysql_list_databases(MYSQL_PORT[slug])
            _ok('Databases retrieved.', databases)
            return

        if action == 'getcollation':
            _ok('Collations retrieved.', mysql_get_collations(MYSQL_PORT[slug]))
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
            rc, out, err = _mysql_exec(MYSQL_PORT[slug], 'SELECT 1')
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
        else:
            rc, out, err = _rabbit('list_vhosts')
            if rc != 0:
                raise Fail(CODE_OPERATION_FAILED, 'RabbitMQ ping failed: {}'.format(err[:200]))
        _ok('{} is reachable.'.format(slug))
