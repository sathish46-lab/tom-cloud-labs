"""
MCP Service Tools
Databases, service credentials, service users.
"""
from mcp.server.fastmcp import Context
from mcp.types import Tool, TextContent

from typing import Any, Dict

from src.mcp.db import get_db
from src.mcp.deps import (
    AuthError,
    OwnershipError,
    get_ownership,
    get_current_user,
    json_response,
    run_docker_exec,
    run_docker_host,
    run_labsctl,
)


# Registry: src/mcp/registry.py (entry "databases") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def databases(lab: str, ctx: Context) -> dict[str, Any]:
    """List databases for a lab."""
    user = await get_current_user(ctx)
    ownership = get_ownership()
    instance_hash = ownership.resolve_lab(user, lab_name=lab)

    from src.mcp.db import get_db
    db = get_db()

    doc = db.machine_labs.find_one(
        {"instance_hash": instance_hash},
        {"credentials": 1, "lab_type": 1}
    )

    if not doc:
        return json_response({"error": "Lab not found"})

    creds = doc.get("credentials", {})
    databases = []

    # Check for common database credentials
    for key, value in creds.items():
        if any(db_type in key.lower() for db_type in ['mysql', 'postgres', 'mongo', 'redis', 'database']):
            databases.append({"type": key, "connection": value})

    return json_response({
        "instance_hash": instance_hash,
        "lab_type": doc.get("lab_type"),
        "databases": databases
    })

# Registry: src/mcp/registry.py (entry "create_database") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def create_database(lab: str, db_type: str, name: str, ctx: Context) -> dict[str, Any]:
    """Create a database."""
    user = await get_current_user(ctx)
    ownership = get_ownership()
    instance_hash = ownership.resolve_lab(user, lab_name=lab)

    # Validate inputs
    import re
    if not re.match(r'^[a-zA-Z0-9_]+$', name):
        return json_response({"error": "Invalid database name"})

    valid_types = ['mysql', 'postgresql', 'mongodb', 'redis']
    if db_type not in valid_types:
        return json_response({"error": f"Invalid database type. Supported: {valid_types}"})

    # Run appropriate creation command in container
    if db_type == 'mysql':
        cmd = f"mysql -u root -e \"CREATE DATABASE IF NOT EXISTS {name};\""
    elif db_type == 'postgresql':
        cmd = f"psql -U postgres -c \"CREATE DATABASE {name};\""
    elif db_type == 'mongodb':
        cmd = f"mongosh --eval \"db.getSiblingDB('{name}').createCollection('init')\""
    elif db_type == 'redis':
        return json_response({"error": "Redis databases are numbered 0-15, use SELECT command"})

    result = await run_docker_exec(
        instance_hash,
        "root",
        cmd,
        timeout=30
    )

    return json_response({
        "instance_hash": instance_hash,
        "database": name,
        "type": db_type,
        "created": result["success"],
        "result": result
    })

# Registry: src/mcp/registry.py (entry "delete_database") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def delete_database(lab: str, db_type: str, name: str, ctx: Context) -> dict[str, Any]:
    """Delete a database."""
    user = await get_current_user(ctx)
    ownership = get_ownership()
    instance_hash = ownership.resolve_lab(user, lab_name=lab)

    import re
    if not re.match(r'^[a-zA-Z0-9_]+$', name):
        return json_response({"error": "Invalid database name"})

    valid_types = ['mysql', 'postgresql', 'mongodb']
    if db_type not in valid_types:
        return json_response({"error": f"Invalid database type. Supported: {valid_types}"})

    if db_type == 'mysql':
        cmd = f"mysql -u root -e \"DROP DATABASE IF EXISTS {name};\""
    elif db_type == 'postgresql':
        cmd = f"psql -U postgres -c \"DROP DATABASE {name};\""
    elif db_type == 'mongodb':
        cmd = f"mongosh --eval \"db.getSiblingDB('{name}').dropDatabase()\""

    result = await run_docker_exec(
        instance_hash,
        "root",
        cmd,
        timeout=30
    )

    return json_response({
        "instance_hash": instance_hash,
        "database": name,
        "type": db_type,
        "deleted": result["success"],
        "result": result
    })

# Registry: src/mcp/registry.py (entry "service_credentials") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def service_credentials(lab: str, ctx: Context) -> dict[str, Any]:
    """List service credentials for a lab."""
    user = await get_current_user(ctx)
    ownership = get_ownership()
    instance_hash = ownership.resolve_lab(user, lab_name=lab)

    from src.mcp.db import get_db
    db = get_db()

    doc = db.machine_labs.find_one(
        {"instance_hash": instance_hash},
        {"credentials": 1}
    )

    if not doc:
        return json_response({"error": "Lab not found"})

    creds = doc.get("credentials", {})
    service_creds = {k: v for k, v in creds.items()
                     if any(s in k.lower() for s in ['api', 'key', 'token', 'secret', 'password', 'cred'])}

    return json_response({
        "instance_hash": instance_hash,
        "service_credentials": service_creds
    })

# Registry: src/mcp/registry.py (entry "create_service_credential") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def create_service_credential(lab: str, name: str, value: str, ctx: Context) -> dict[str, Any]:
    """Create a service credential."""
    user = await get_current_user(ctx)
    ownership = get_ownership()
    instance_hash = ownership.resolve_lab(user, lab_name=lab)

    import re
    if not re.match(r'^[a-zA-Z0-9_]+$', name):
        return json_response({"error": "Invalid credential name"})

    from src.mcp.db import get_db
    db = get_db()

    db.machine_labs.update_one(
        {"instance_hash": instance_hash, "user_id": user["user_id"]},
        {"$set": {f"credentials.{name}": value}}
    )

    return json_response({
        "instance_hash": instance_hash,
        "credential": name,
        "created": True
    })

# Registry: src/mcp/registry.py (entry "delete_service_credential") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def delete_service_credential(lab: str, name: str, ctx: Context) -> dict[str, Any]:
    """Delete a service credential."""
    user = await get_current_user(ctx)
    ownership = get_ownership()
    instance_hash = ownership.resolve_lab(user, lab_name=lab)

    from src.mcp.db import get_db
    db = get_db()

    db.machine_labs.update_one(
        {"instance_hash": instance_hash, "user_id": user["user_id"]},
        {"$unset": {f"credentials.{name}": ""}}
    )

    return json_response({
        "instance_hash": instance_hash,
        "credential": name,
        "deleted": True
    })

# Registry: src/mcp/registry.py (entry "service_users") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def service_users(lab: str, ctx: Context) -> dict[str, Any]:
    """List service users for a lab."""
    user = await get_current_user(ctx)
    ownership = get_ownership()
    instance_hash = ownership.resolve_lab(user, lab_name=lab)

    result = await run_docker_exec(
        instance_hash,
        "root",
        "getent passwd | grep -E '^([^:]+):x:[0-9]+:' | cut -d: -f1,3,6",
        timeout=10
    )

    users = []
    for line in result["stdout"].strip().split('\n'):
        if line:
            parts = line.split(':')
            if len(parts) >= 3:
                users.append({
                    "username": parts[0],
                    "uid": parts[1],
                    "home": parts[2]
                })

    return json_response({
        "instance_hash": instance_hash,
        "users": users,
        "count": len(users)
    })

# Registry: src/mcp/registry.py (entry "add_service_user") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def add_service_user(lab: str, username: str, ctx: Context, password: str = None) -> dict[str, Any]:
    """Add a service user."""
    user = await get_current_user(ctx)
    ownership = get_ownership()
    instance_hash = ownership.resolve_lab(user, lab_name=lab)

    import re
    if not re.match(r'^[a-zA-Z0-9_-]+$', username):
        return json_response({"error": "Invalid username"})

    cmd = f"useradd -m -s /bin/bash {username}"
    if password:
        import base64
        encoded = base64.b64encode(password.encode()).decode()
        cmd += f" && echo {encoded} | base64 -d | passwd --stdin {username}"

    result = await run_docker_exec(
        instance_hash,
        "root",
        cmd,
        timeout=30
    )

    return json_response({
        "instance_hash": instance_hash,
        "username": username,
        "added": result["success"],
        "result": result
    })

# Registry: src/mcp/registry.py (entry "remove_service_user") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def remove_service_user(lab: str, username: str, ctx: Context) -> dict[str, Any]:
    """Remove a service user."""
    user = await get_current_user(ctx)
    ownership = get_ownership()
    instance_hash = ownership.resolve_lab(user, lab_name=lab)

    result = await run_docker_exec(
        instance_hash,
        "root",
        f"userdel -r {username}",
        timeout=30
    )

    return json_response({
        "instance_hash": instance_hash,
        "username": username,
        "removed": result["success"],
        "result": result
    })


# ── Tom Labs-parity names (aliases) ───────────────────────────────────────────

async def list_databases(lab: str, ctx: Context) -> dict[str, Any]:
    """List the databases of a lab."""
    return await databases(lab, ctx)


# ── Tom Labs-parity service platform tools ────────────────────────────────────

SLUGS = ("mysql", "mariadb", "mongodb", "postgresql", "redis", "rabbitmq")

_SERVICE_META = {
    "mysql":      {"name": "MySQL",      "users": "mysql_users",      "res": "mysql_databases",      "ufield": "mysql_username",      "rfield": "db_name",    "port": 3306,  "uri": "mysql",     "cli": "mysql",     "collate": "utf8mb4_0900_ai_ci"},
    "mariadb":    {"name": "MariaDB",    "users": "mariadb_users",    "res": "mariadb_databases",    "ufield": "mariadb_username",    "rfield": "db_name",    "port": 3307,  "uri": "mysql",     "cli": "mysql",     "collate": "utf8mb4_general_ci"},
    "mongodb":    {"name": "MongoDB",    "users": "mongodb_users",    "res": "mongodb_databases",    "ufield": "mongodb_username",    "rfield": "db_name",    "port": 27017, "uri": "mongodb",   "cli": "mongosh",   "collate": None},
    "postgresql": {"name": "PostgreSQL", "users": "postgresql_users", "res": "postgresql_databases", "ufield": "postgresql_username", "rfield": "db_name",    "port": 5432,  "uri": "postgresql","cli": "psql",      "collate": None},
    "redis":      {"name": "Redis",      "users": "redis_users",      "res": None,                    "ufield": "redis_username",     "rfield": None,         "port": 6379,  "uri": "redis",     "cli": "redis-cli", "collate": None},
    "rabbitmq":   {"name": "RabbitMQ",   "users": "rabbitmq_users",   "res": None,                    "ufield": "rabbitmq_username",  "rfield": "vhost",      "port": 5672,  "uri": "amqp",      "cli": None,        "collate": None},
}

_IN_LAB_HOST = "TomCloudLab"


def _my_filter(user):
    ids = [str(x) for x in get_ownership()._user_identifiers(user)]
    return {"$or": [{"email": user.get("email")}, {"user_id": {"$in": ids}}]}


def _service_counts(db, user, meta):
    users_col, res_col = meta["users"], meta["res"]
    users_c = db[users_col]
    my = _my_filter(user)
    usernames = sorted({u.get(meta["ufield"]) for u in users_c.find({**my, meta["ufield"]: {"$ne": None}}, {meta["ufield"]: 1})})
    try:
        res_count = db[res_col].count_documents(my) if res_col else 0
    except Exception:
        res_count = 0
    return len(usernames), res_count, usernames


CONNECTION_NOTE = ("Every service accepts connections from inside a lab on this platform (its shell, startup script or app) on the in-lab hostname "
                   "and port. Whether it is also reachable from the internet, and on which port, depends on this install: when this install publishes "
                   "it, the result gives it as the public endpoint (the service's connection card in the web dashboard shows it as \"Public endpoint\"); "
                   "a null public endpoint means not published, or not known here. A published service usually uses a different port outside. The hostnames "
                   "resolve publicly, but not always to this install. So a timeout or refused connection from outside means the service is not published "
                   "(or not on that port). It is not a bad password, and no tool argument changes it.")

async def list_services(ctx: Context) -> dict[str, Any]:
    """List the user's service users and database counts per service."""
    user = await get_current_user(ctx)
    db = get_db()
    services = {}
    databases_total = 0
    services_in_use = 0
    for slug in SLUGS:
        meta = _SERVICE_META[slug]
        col_exists = meta["users"] in db.list_collection_names()
        if col_exists:
            users_n, db_n, usernames = _service_counts(db, user, meta)
        else:
            users_n, db_n, usernames = 0, 0, []
        used = users_n > 0 or db_n > 0
        databases_total += db_n
        if used:
            services_in_use += 1
        services[slug] = {
            "used": used, "users": users_n, "databases": db_n, "usernames": usernames,
        }
    endpoints = {
        "adminer": {"host": _IN_LAB_HOST, "port": 8080},
    }
    for slug in SLUGS:
        endpoints[slug] = {"host": _IN_LAB_HOST, "port": _SERVICE_META[slug]["port"]}
    return json_response({
        "services": services,
        "services_in_use": services_in_use,
        "databases_total": databases_total,
        "acting_as": "you",
        "note": "usernames are the caller's own service users. Passwords come only from service_credentials; connection strings from service_connect_info.",
        "connect_from": CONNECTION_NOTE,
        "externally_reachable": [],
        "endpoints": endpoints,
    })


def _connect_lines(meta, username, host, port):
    slug = [k for k, v in _SERVICE_META.items() if v is meta][0]
    u = username
    pw = "<password>"
    if slug in ("mysql", "mariadb"):
        url_in = f"mysql://{u}:{pw}@{host}:{port}/<database>"
        cmd = f"{'' if slug == 'mysql' else ''}{meta['cli']} -h {host} -P {port} -u {u} -p <database>"
        env = f"DATABASE_URL={url_in}"
        auth = None
    elif slug == "mongodb":
        url_in = f"mongodb://{u}:{pw}@{host}:{port}/<database>?authSource=admin"
        cmd = f"mongosh \"mongodb://{host}:{port}/<database>?authSource=admin\" -u {u} -p"
        env = f"DATABASE_URL={url_in}"
        auth = "admin"
    elif slug == "postgresql":
        url_in = f"postgresql://{u}:{pw}@{host}:{port}/<database>"
        cmd = f"psql -h {host} -p {port} -U {u} -d <database>"
        env = f"DATABASE_URL={url_in}"
        auth = None
    elif slug == "redis":
        url_in = f"redis://{u}:{pw}@{host}:{port}"
        cmd = f"redis-cli -h {host} -p {port} -a <password>"
        env = f"REDIS_URL={url_in}"
        auth = None
    else:
        url_in = f"amqp://{u}:{pw}@{host}:{port}"
        cmd = None
        env = f"AMQP_URL={url_in}"
        auth = None
    return {
        "in_lab": {"url": url_in, "command": cmd, "env": env},
        "auth_database": auth,
    }


async def service_connect_info(ctx: Context, service: str = None, username: str = None) -> dict[str, Any]:
    """How to connect a lab or app to the user's database or broker."""
    user = await get_current_user(ctx)
    db = get_db()
    slugs = [service] if service else list(SLUGS)
    if service and service not in SLUGS:
        return json_response({"error": f"Unknown service '{service}'. Services: {', '.join(SLUGS)}"})
    out = []
    for slug in slugs:
        meta = _SERVICE_META[slug]
        if meta["users"] not in db.list_collection_names():
            continue
        users_q = {**_my_filter(user), meta["ufield"]: {"$ne": None}}
        usernames = sorted({u.get(meta["ufield"]) for u in db[meta["users"]].find(users_q, {meta["ufield"]: 1})})
        if username and username in usernames:
            usernames = [username]
        elif username:
            continue
        users = []
        for u in usernames:
            try:
                n_res = db[meta["res"]].count_documents({**_my_filter(user), meta["ufield"]: u}) if meta["res"] else 0
            except Exception:
                n_res = 0
            lines = _connect_lines(meta, u, _IN_LAB_HOST, meta["port"])
            row = {
                "username": u,
                "database_count": n_res,
                "database_limit": 10 if slug not in ("redis", "rabbitmq") else None,
                "databases": None,
                "connect": {
                    "in_lab": lines["in_lab"],
                    "public": None,
                },
            }
            if lines["auth_database"]:
                row["auth_database"] = lines["auth_database"]
            users.append(row)
        if users:
            out.append({
                "service": slug,
                "name": meta["name"],
                "in_lab": {"host": _IN_LAB_HOST, "port": meta["port"]},
                "public": None,
                "users": users,
                "dashboard_path": f"/services/{slug}",
            })
    return json_response({
        "services": out,
        "password_placeholder": "<password>",
        "password_from": "service_credentials(service=<service>, username=<username>) returns the stored password; the Services page in the web dashboard shows it too.",
        "how_to": [
            "In the lab, put the in-lab line (not the public one) in the app's environment, e.g. its .env or the lab's startup script.",
            "Replace <password> with the service user's password.",
            "The public endpoint is only for connecting from outside the platform, and only when the install publishes the service.",
        ],
        "externally_reachable": [],
        "connect_from": CONNECTION_NOTE,
        "note": None,
        "acting_as": "you",
    })


async def service_credentials(service: str, username: str, ctx: Context) -> dict[str, Any]:
    """Get the stored password of one of the user's service users."""
    user = await get_current_user(ctx)
    db = get_db()
    if service not in SLUGS:
        return json_response({"error": f"Unknown service '{service}'. Services: {', '.join(SLUGS)}"})
    meta = _SERVICE_META[service]
    if meta["users"] not in db.list_collection_names():
        return json_response({"error": f"The {meta['name']} service is not provisioned on this install."})
    doc = db[meta["users"]].find_one({**_my_filter(user), meta["ufield"]: username})
    if not doc:
        return json_response({"error": f"Unknown service user '{username}' on {service}. Use list_services to see your usernames."})
    import base64, hashlib
    stored = doc.get(meta["ufield"] + "_password") or doc.get("password") or ""
    if doc.get(meta["ufield"] + "_password"):
        stored = doc[meta["ufield"] + "_password"]
    try:
        stored = base64.b64decode(stored).decode()
    except Exception:
        pass
    host, port = _IN_LAB_HOST, meta["port"]
    lines = _connect_lines(meta, username, host, port)
    url_in = lines["in_lab"]["url"]
    if meta["res"]:
        dbs = [{"db_name": d.get(meta["rfield"]), "collation": d.get("collation")} for d in db[meta["res"]].find({**_my_filter(user), meta["ufield"]: username}, {meta["rfield"]: 1, "collation": 1, "_id": 0})]
    else:
        dbs = []
    return json_response({
        "service": service,
        "username": username,
        "host": host,
        "port": port,
        "password": stored,
        "databases": dbs,
        "connect": f"DATABASE_URL={url_in}",
        "note": "Stored in plain text in the platform's database. Give it to the user, don't commit it.",
    })


async def list_databases(service: str, username: str, ctx: Context) -> dict[str, Any]:
    """List the databases of one of the user's service users."""
    user = await get_current_user(ctx)
    db = get_db()
    if service not in SLUGS:
        return json_response({"error": f"Unknown service '{service}'. Services: {', '.join(SLUGS)}"})
    meta = _SERVICE_META[service]
    if meta["users"] not in db.list_collection_names():
        return json_response({"error": f"The {meta['name']} service is not provisioned on this install."})
    udoc = db[meta["users"]].find_one({**_my_filter(user), meta["ufield"]: username})
    if not udoc:
        return json_response({"error": f"Unknown service user '{username}' on {service}. Use list_services to see your usernames."})
    rows = []
    if meta["res"]:
        for d in db[meta["res"]].find({**_my_filter(user), meta["ufield"]: username}, {"_id": 0, meta["rfield"]: 1, "collation": 1, "character_set": 1, "created_at": 1}):
            rows.append(d)
    return json_response({"service": service, "username": username, "databases": rows})
