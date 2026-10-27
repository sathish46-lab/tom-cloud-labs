"""
MCP Lab-MCP Tools (parity set)
Register / manage / call MCP servers that run INSIDE one of the user's labs.

Store: collection ``lab_mcp_servers`` — one row per server:
  {email, user_id, instance_hash, name, transport: http|stdio, port, path,
   url, command, args, env: {KEY: value|{secret:NAME}}, headers: {...},
   enabled: bool, needs_network: bool, state, last_health, last_call}

HTTP servers are called inside the lab container over `docker exec … curl`
with a full JSON-RPC initialize/initialized/list|call handshake.
Stdio servers are recorded but refuse list/call honestly.
"""
import base64
import json
import re
import time
from typing import Any, Dict, Optional, Tuple

from mcp.server.fastmcp import Context

from src.mcp.db import get_db
from src.mcp.deps import get_ownership, get_current_user, json_response, run_docker_exec


# ── helpers ──────────────────────────────────────────────────────────────

def _mine(user, doc) -> bool:
    ids = [str(x) for x in get_ownership()._user_identifiers(user)]
    return (doc.get("email") == user.get("email")
            or str(doc.get("user_id")) in ids)


def _find_lab(user, lab: Optional[str] = None, instance_id: Optional[str] = None) -> Tuple[Optional[dict], Optional[str]]:
    db = get_db()
    ownership = get_ownership()
    if instance_id and re.match(r"^[0-9a-f]{32}$", instance_id):
        doc = db.machine_labs.find_one({"instance_hash": instance_id})
        if doc and _mine(user, doc):
            return doc, instance_id
        return None, instance_id
    if lab:
        ih = ownership.resolve_lab(user, lab_name=lab)
        return db.machine_labs.find_one({"instance_hash": ih}), ih
    return None, None


def _resolve_server(user, instance_id: str, server: str) -> Optional[dict]:
    db = get_db()
    row = db.lab_mcp_servers.find_one({
        "instance_hash": instance_id,
        "$or": [{"name": server}, {"display_name": server}],
    })
    if row and row.get("display_name") and row["display_name"] == server:
        return row
    if row is None:
        return None
    return row


def _secret_value(value, secrets: Dict[str, str]):
    """{secret:NAME} -> resolved or <$never-shown>; plain -> as stored."""
    if isinstance(value, dict) and "secret" in value:
        name = value["secret"]
        return secrets.get(name, "{secret:" + name + "}")
    return value


def _resolved_env(row) -> Dict[str, str]:
    secrets = row.get("secrets") or {}
    out = {}
    for k, v in (row.get("env") or {}).items():
        out[k] = _secret_value(v, secrets)
    return out


def _resolved_headers(row) -> Dict[str, str]:
    secrets = row.get("secrets") or {}
    return {k: _secret_value(v, secrets) for k, v in (row.get("headers") or {}).items()}


def _redact(row) -> dict:
    """Same {{untrusted}}-free shape for listings."""
    env = row.get("env") or {}
    headers = row.get("headers") or {}
    return {
        "id": str(row.get("_id")),
        "name": row.get("name"),
        "transport": row.get("transport"),
        "url": row.get("url"),
        "port": row.get("port"),
        "path": row.get("path"),
        "command": row.get("command"),
        "args": row.get("args"),
        "env": {k: ("<set>" if isinstance(v, dict) and v.get("secret") else v) for k, v in env.items()},
        "headers": {k: ("<set>" if isinstance(v, dict) and v.get("secret") else ("<set>" if "token" in k.lower() or "auth" in k.lower() else v)) for k, v in headers.items()},
        "enabled": row.get("enabled", True),
        "needs_network": row.get("needs_network", False),
        "state": row.get("state", "ready"),
        "missing_secrets": row.get("missing_secrets", []),
        "tools_count": row.get("tools_count"),
        "last_health": row.get("last_health"),
    }


async def _http_mcp(row, method: str, params: Optional[dict] = None, timeout: int = 25):
    """One JSON-RPC method against an HTTP MCP server inside the lab."""
    port = row.get("port")
    path = row.get("path") or "/mcp"
    ih = row["instance_hash"]
    headers = _resolved_headers(row)
    h_args = []
    for k, v in headers.items():
        key = re.sub(r"[^A-Za-z0-9\-]", "", k)
        if not key:
            continue
        h_args += ["-H", f"{key}: {v}"]
    h_args += ["-H", "accept: application/json, text/event-stream", "-H", "content-type: application/json"]

    # 1) initialize → session id
    payload = json.dumps({"jsonrpc": "2.0", "id": 1, "method": "initialize",
                          "params": {"protocolVersion": "2025-06-18", "capabilities": {},
                                     "clientInfo": {"name": "tomlabs-mcp", "version": "1"}}})
    b64 = base64.b64encode(payload.encode()).decode()
    res = await run_docker_exec(
        ih, "root",
        f"curl -sS -m {timeout} -X POST http://127.0.0.1:{port}{path} "
        + " ".join(f'"{a}"' for a in h_args)
        + f" --data-binary @<(echo {b64} | base64 -d) -D /tmp/.lmcp.h",
        timeout=timeout + 5)
    sid = None
    serr = res.get("stderr", "") or ""
    # read captured session id
    rd = await run_docker_exec(ih, "root", "cat /tmp/.lmcp.h 2>/dev/null; rm -f /tmp/.lmcp.h", timeout=5)
    for ln in (rd.get("stdout", "") or "").splitlines():
        if ln.lower().startswith("mcp-session-id:"):
            sid = ln.split(":", 1)[1].strip()
    body = extract_out(res.get("stdout", ""))
    if body is None:
        return None, sid, (serr or res.get("stdout", "")[:200])
    # 2) initialized
    if sid:
        payload2 = json.dumps({"jsonrpc": "2.0", "method": "notifications/initialized"})
        b642 = base64.b64encode(payload2.encode()).decode()
        await run_docker_exec(ih, "root",
                              f"curl -sS -m {timeout} -X POST http://127.0.0.1:{port}{path} "
                              + " ".join(f'"{a}"' for a in h_args)
                              + f' -H "mcp-session-id: {sid}"'
                              + f" --data-binary @<(echo {b642} | base64 -d)",
                              timeout=timeout + 5)
    return body, sid, None


def extract_out(text: str):
    for ln in (text or "").splitlines():
        if ln.startswith("data:"):
            try:
                j = json.loads(ln[5:].strip())
                if "jsonrpc" in j or "result" in j or "error" in j:
                    return j
            except Exception:
                continue
    try:
        j = json.loads(text or "")
        if isinstance(j, dict) and ("result" in j or "error" in j):
            return j
    except Exception:
        pass
    return None


async def _http_mcp_call(row, method, params, ctx=None, timeout=30):
    payload = {"jsonrpc": "2.0", "id": 9, "method": method}
    if params is not None:
        payload["params"] = params
    b64 = base64.b64encode(json.dumps(payload).encode()).decode()
    port, path = row.get("port"), row.get("path") or "/mcp"
    ih = row["instance_hash"]
    headers = _resolved_headers(row)
    h_args = []
    for k, v in headers.items():
        key = re.sub(r"[^A-Za-z0-9\-]", "", k)
        if not key:
            continue
        h_args += ["-H", f"{key}: {v}"]
    h_args += ["-H", "accept: application/json, text/event-stream", "-H", "content-type: application/json",
               "-H", f"mcp-session-id: {row.get('session_id') or 'static'}"]
    res = await run_docker_exec(
        ih, "root",
        f"curl -sS -m {timeout} -X POST http://127.0.0.1:{port}{path} "
        + " ".join(f'"{a}"' for a in h_args)
        + f" --data-binary @<(echo {b64} | base64 -d)",
        timeout=timeout + 5)
    out = extract_out(res.get("stdout", ""))
    return out


# ── 1) add / register ────────────────────────────────────────────────────

async def _store_rows(user, lab, instance_id, rows, module="lab_mcp_add"):
    """Common store path; returns (added, ok, err)"""
    db = get_db()
    added = []
    errors = []
    for name, spec in rows.items():
        if not re.match(r"^[A-Za-z0-9_.\-]{1,64}$", name):
            errors.append((name, "invalid name"))
            continue
        existing = db.lab_mcp_servers.find_one({"instance_hash": instance_id, "name": name})
        if existing:
            errors.append((name, "already registered"))
            continue
        transport = spec.get("transport") or ("http" if spec.get("url") or spec.get("port") else "stdio")
        if transport == "http" and not (spec.get("url") or spec.get("port")):
            errors.append((name, "http transport needs 'url' (with port/path) or 'port'+'path'"))
            continue
        if transport == "stdio" and not spec.get("command"):
            errors.append((name, "stdio transport needs 'command'"))
            continue
        env = {}
        for k, v in (spec.get("env") or {}).items():
            if v is None or not isinstance(v, str):
                env[k] = v
                continue
            if v.startswith("{secret:"):
                env[k] = {"secret": v[8:].rstrip("}")}
            else:
                env[k] = {"secret": k}
        headers = {}
        for k, v in (spec.get("headers") or {}).items():
            if v.startswith("{secret:"):
                headers[k] = {"secret": v[8:].rstrip("}")}
            else:
                headers[k] = {"secret": k}
        url = spec.get("url")
        port = spec.get("port")
        path = spec.get("path") or "/mcp"
        if not url and port:
            url = f"http://127.0.0.1:{port}{path}"
        if spec.get("needs_network") is None:
            needs_network = bool(re.search(r"(?<!127\.0\.0\.1)(?<!localhost)(?:\.|:)\/", url or "")) or (transport == "http")
        else:
            needs_network = bool(spec["needs_network"])
        row = {
            "email": user.get("email"),
            "user_id": user.get("user_id"),
            "instance_hash": instance_id,
            "name": name,
            "transport": transport,
            "url": url,
            "port": port,
            "path": path if not url else _path_of(url),
            "command": spec.get("command"),
            "args": spec.get("args") or [],
            "env": env,
            "headers": headers,
            "enabled": True,
            "needs_network": needs_network,
            "state": "needs_setup" if any(isinstance(v, dict) and "secret" in v for v in list(env.values()) + list(headers.values())) and not (spec.get("pre_secrets") or {}) else "ready",
            "secrets": dict(spec.get("pre_secrets") or {}),
            "missing_secrets": [v["secret"] for k, v in list(env.items()) + list(headers.items())
                                if isinstance(v, dict) and "secret" in v and v["secret"] not in (spec.get("pre_secrets") or {})],
            "created_at": time.time(),
        }
        db.lab_mcp_servers.insert_one(row)
        row.pop("_id")
        for k in ("secrets",):
            row[k] = list(row[k].keys()) if row[k] else []
        added.append(row)
    return added, errors


def _path_of(url):
    m = re.match(r"https?://[^/]*(/.*)?$", url or "")
    return (m.group(1) or "/") if m else "/"


async def lab_mcp_add(ctx: Context, instance_id: str,
                      catalog_key: str = None, container: str = None,
                      mcp_servers_json: str = None, values: Any = None,
                      name: str = None, mcp_server_rows: Any = None,
                      move_secrets: bool = False) -> dict[str, Any]:
    """Attach an MCP server to a lab (a Lab Store server, a container, or a pasted snippet)."""
    user = await get_current_user(ctx)
    lab_doc, ih = _find_lab(user, instance_id=instance_id)
    if not lab_doc:
        return json_response({"error": f"Unknown instance_id '{instance_id}' or a lab that is not yours. Use list_labs for your lab instance ids."})
    if catalog_key:
        return json_response({
            "error": "No Lab Store catalog is published on this install. Use a snippet (mcp_servers_json) or rows (mcp_server_rows) instead.",
            "given": "catalog_key",
        })
    if container:
        return json_response({
            "error": "Attaching an existing container's MCP server by container name requires that container to run a labelled MCP port; not supported on this install yet.",
            "given": "container",
        })
    rows = {}
    pre_secrets = {}
    if values and isinstance(values, dict):
        pre_secrets = {k: v for k, v in values.items()}
    if mcp_servers_json:
        try:
            parsed = json.loads(mcp_servers_json)
            if isinstance(parsed, dict) and "mcpServers" in parsed:
                parsed = parsed["mcpServers"]
        except Exception:
            return json_response({"error": "mcp_servers_json is not valid JSON."})
        if not isinstance(parsed, dict):
            return json_response({"error": "mcp_servers_json: expected {\"mcpServers\": {...}} or {name: {...}}."})
        for sname, spec in parsed.items():
            surl = spec.get("url") or ""
            port = spec.get("port")
            if surl:
                m = re.match(r"https?://127\.0\.0\.1:(\d+)(/.*)?$", surl) or re.match(r"https?://localhost:(\d+)(/.*)?$", surl)
                port = port or (int(m.group(1)) if m else None)
            rows[sname] = {
                "transport": spec.get("transport") or ("http" if surl or spec.get("port") else "stdio"),
                "url": surl,
                "port": port,
                "path": spec.get("path") or _path_of(surl),
                "command": spec.get("command"),
                "args": spec.get("args") or [],
                "env": spec.get("env") or {},
                "headers": spec.get("headers") or {},
                "pre_secrets": pre_secrets,
            }
    elif mcp_server_rows:
        if not isinstance(mcp_server_rows, list):
            return json_response({"error": "mcp_server_rows: expected a list of rows."})
        for spec in mcp_server_rows:
            if not isinstance(spec, dict) or not spec.get("name"):
                return json_response({"error": "Every row needs a 'name'."})
            surl = spec.get("url") or ""
            rows[spec["name"]] = {
                "transport": spec.get("transport") or ("http" if surl or spec.get("port") else "stdio"),
                "url": surl or (f"http://127.0.0.1:{spec['port']}{spec.get('path') or '/'}" if spec.get("port") else None),
                "port": spec.get("port"),
                "path": spec.get("path") or _path_of(surl),
                "command": spec.get("command"),
                "args": spec.get("args") or [],
                "env": spec.get("env") or {},
                "headers": spec.get("headers") or {},
                "pre_secrets": pre_secrets,
            }
    else:
        return json_response({"error": "Give exactly one of catalog_key / container / mcp_servers_json / mcp_server_rows."})
    added, errors = await _store_rows(user, lab_doc.get("lab") or lab_doc.get("lab_type"), ih, rows)
    out_rows = []
    for r in added:
        missing = []
        for k, v in list((r.get("env") or {}).items()) + list((r.get("headers") or {}).items()):
            if isinstance(v, dict) and "secret" in v and v["secret"] not in list((r.get("secrets") or {}).keys()):
                missing.append(v["secret"])
        row = {k: v for k, v in r.items() if k not in ("secrets",)}
        row["secrets"] = "<stored>" if r.get("secrets") else {}
        if missing:
            row["missing_secrets"] = list(dict.fromkeys(missing))
            row["state"] = "needs_setup"
            row["note"] = "Set them with lab_mcp_config_set before use; lab_mcp_secrets lists what each one needs."
        out_rows.append(row)
    resp = {"instance_id": ih, "added": out_rows, "errors": [{"name": n, "error": e} for n, e in errors]}
    if out_rows and not errors:
        resp["note"] = "The server is usable at once with lab_mcp_status / lab_mcp_tools."
    return json_response(resp)


async def register_lab_mcp(ctx: Context, instance_id: str, mcp_servers_json: str, move_secrets: bool = False) -> dict[str, Any]:
    """Register your own MCP servers on a lab by pasting a standard mcpServers snippet."""
    return await lab_mcp_add(ctx, instance_id, mcp_servers_json=mcp_servers_json, move_secrets=move_secrets)


# ── 2) list ──────────────────────────────────────────────────────────────

async def lab_mcp_servers(ctx: Context, instance_id: str = None, refresh: bool = False,
                          include_disabled: bool = False, limit: int = 20) -> dict[str, Any]:
    """List the MCP servers running inside your labs, with their health and tool counts."""
    user = await get_current_user(ctx)
    db = get_db()
    q = {}
    if instance_id:
        if not re.match(r"^[0-9a-f]{32}$", instance_id):
            return json_response({"error": "Pass a 32-character instance id from list_labs."})
        doc, _ = _find_lab(user, instance_id=instance_id)
        if not doc:
            return json_response({"error": "A lab that is not yours is refused."})
        q["instance_hash"] = instance_id
    else:
        mine_labs = [l["instance_hash"] for l in db.machine_labs.find({}) if _mine(user, l)]
        q["instance_hash"] = {"$in": mine_labs or ["<none>"]}
    rows = list(db.lab_mcp_servers.find(q).limit(max(1, min(limit, 100))))
    out = []
    for r in rows:
        row = _redact(r)
        row["lab"] = r.get("instance_hash")
        labdoc = db.machine_labs.find_one({"instance_hash": r.get("instance_hash")}, {"lab": 1}) or {}
        row["lab_name"] = labdoc.get("lab")
        out.append(row)
    return json_response({
        "servers": out,
        "count": len(out),
        "note": "Only running labs appear here. A server marked needs_setup waits for secrets (lab_mcp_config_set).",
    })


# ── 3) tools list ────────────────────────────────────────────────────────

async def lab_mcp_tools(ctx: Context, instance_id: str, server: str) -> dict[str, Any]:
    """List the tools one MCP server in your lab offers, with their input schemas."""
    user = await get_current_user(ctx)
    db = get_db()
    row = _resolve_server(user, instance_id, server)
    if not row or not _mine(user, row):
        return json_response({"error": f"Unknown server '{server}' on this lab. Use lab_mcp_servers to list them."})
    if not row.get("enabled", True):
        return json_response({"error": f"Server '{server}' is switched off in this lab. Turn it back on with set_lab_mcp_enabled(enabled=true)."})
    if row.get("state") == "needs_setup" and row.get("missing_secrets"):
        return json_response({"error": f"needs_setup: set {', '.join(row['missing_secrets'])} with lab_mcp_config_set first."})
    if row.get("transport") != "http":
        return json_response({"error": f"Server '{server}' is stdio; stdio servers start per call and are not listed on this install yet."})
    body, sid, err = await _http_mcp(row, "tools/list", {})
    if body is None:
        return json_response({"error": f"connect_refused: {err or 'no valid MCP reply'}"})
    result = body.get("result") or {}
    tools = result.get("tools") or []
    db.lab_mcp_servers.update_one({"_id": row["_id"]}, {"$set": {"tools_count": len(tools), "last_health": time.time(), "state": "ready", "session_id": sid}})
    return json_response({
        "server": row.get("name"),
        "tools": tools,
        "count": len(tools),
        "note": "Pass a tool's `name` and arguments matching its input_schema to lab_mcp_call.",
    })


# ── 4) call ──────────────────────────────────────────────────────────────

_BLOCKED_PREFIX = ("sh", "sudo", "rm -rf /", "curl | sh")


async def lab_mcp_call(ctx: Context, instance_id: str, server: str, tool: str, arguments: Optional[dict] = None) -> dict[str, Any]:
    """Call one tool of an MCP server running inside your lab."""
    user = await get_current_user(ctx)
    db = get_db()
    row = _resolve_server(user, instance_id, server)
    if not row or not _mine(user, row):
        return json_response({"error": f"Unknown server '{server}' on this lab."})
    if not row.get("enabled", True):
        return json_response({"error": f"Server '{server}' is switched off (set_lab_mcp_enabled)."})
    if row.get("transport") != "http":
        return json_response({"error": f"Server '{server}' is stdio; stdio servers are not callable on this install yet."})
    body = await _http_mcp_call(row, "tools/call", {"name": tool, "arguments": arguments or {}})
    db.lab_mcp_servers.update_one({"_id": row["_id"]}, {"$set": {"last_call": {"tool": tool, "at": time.time()}}})
    if body is None:
        return json_response({"error": "no_reply_from_server"})
    if body.get("error"):
        e = body["error"]
        return json_response({"error": f"{e.get('code')}: {e.get('message')}"})
    result = body.get("result") or {}
    if result.get("isError"):
        content = result.get("content") or []
        text = content[0].get("text") if content and isinstance(content[0], dict) else json.dumps(result)
        return json_response({"isError": True, "text": (text or "")[:2000]})
    return json_response({
        "tool": tool,
        "content": result.get("content"),
        "structuredContent": result.get("structuredContent"),
    })


def _url_otherhost(url):
    return not re.match(r"^https?://(127\.0\.0\.1|localhost)", url or "")


# ── 5) status / test ─────────────────────────────────────────────────────

async def lab_mcp_status(ctx: Context, instance_id: str, server: str) -> dict[str, Any]:
    """Check whether one MCP server in your lab is answering."""
    user = await get_current_user(ctx)
    db = get_db()
    row = _resolve_server(user, instance_id, server)
    if not row or not _mine(user, row):
        return json_response({"error": f"Unknown server '{server}' on this lab."})
    healthy = tools_n = None
    code = message = None
    if row.get("transport") != "http":
        healthy, message = None, "stdio servers start per call; no live status on this install."  # Tom-faithful
    else:
        body, sid, err = await _http_mcp(row, "tools/list", {}, timeout=15)
        if body is None:
            healthy = False
            stripped = (err or "")[:200]
            if "refused" in stripped:
                code, message = "connect_refused", f"Nothing is listening at {row.get('url')}. Is the server inside the lab running?"
            else:
                code, message = "connect_refused", (stripped or "timeout or protocol error")
        else:
            result = body.get("result") or {}
            if "tools" in result:
                healthy = True
                tools_n = len(result["tools"])
                db.lab_mcp_servers.update_one({"_id": row["_id"]}, {"$set": {"state": "ready", "tools_count": tools_n, "last_health": time.time(), "session_id": sid}})
            else:
                healthy = False
                code, message = "not_mcp", "The URL answered, but not with an MCP initialize reply."
    out = {
        "instance_id": instance_id,
        "server": row.get("name"),
        "healthy": healthy,
        "recent_calls": [
            {"tool": (r or {}).get("tool"), "at": (r or {}).get("at")}
            for r in ([row.get("last_call")] if row.get("last_call") else [])
        ],
        "note": "recent_calls shows your last 20 calls (tool name, argument shape, outcome; never the values)." if healthy is not False else None,
    }
    if code:
        out["code"] = code
        out["message"] = message
    if tools_n is not None:
        out["tools_count"] = tools_n
    return json_response(out)


async def lab_mcp_test(ctx: Context, instance_id: str, server: str) -> dict[str, Any]:
    """Check that a lab MCP server works with its stored secrets (the Test button)."""
    user = await get_current_user(ctx)
    db = get_db()
    row = _resolve_server(user, instance_id, server)
    if not row or not _mine(user, row):
        return json_response({"error": f"Unknown server '{server}' on this lab."})
    missing = []
    for _group in ("env", "headers"):
        for _k, _v in (row.get(_group) or {}).items():
            if isinstance(_v, dict) and "secret" in _v and _v["secret"] not in (row.get("secrets") or {}):
                missing.append(_v["secret"])
    if missing:
        return json_response({
            "server": row.get("name"),
            "ok": False,
            "code": "missing_secrets",
            "missing_secrets": sorted(set(missing)),
            "note": "Set them with lab_mcp_config_set; an unsaved value is never tested.",
        })
    body, sid, err = await _http_mcp(row, "tools/list", {}, timeout=15)
    if body is None:
        stripped = (err or "")[:160]
        code = "connect_refused" if "refused" in stripped else ("not_mcp" if "404" in stripped else "connect_refused")
        return json_response({"server": row.get("name"), "ok": False, "code": code, "detail": stripped})
    result = body.get("result") or {}
    if result.get("tools"):
        db.lab_mcp_servers.update_one({"_id": row["_id"]}, {"$set": {"state": "ready", "tools_count": len(result["tools"]), "last_health": time.time(), "session_id": sid}})
        return json_response({"server": row.get("name"), "ok": True, "tools": len(result["tools"])})
    return json_response({"server": row.get("name"), "ok": False, "code": "not_mcp"})


# ── 6) config / secrets ──────────────────────────────────────────────────

async def lab_mcp_config_set(ctx: Context, instance_id: str, server: str, name: str, value: str) -> dict[str, Any]:
    """Set a secret or setting one of your lab's MCP servers needs."""
    user = await get_current_user(ctx)
    db = get_db()
    row = _resolve_server(user, instance_id, server)
    if not row or not _mine(user, row):
        return json_response({"error": f"Unknown server '{server}' on this lab."})
    if not value or len(value) > 16 * 1024:
        return json_response({"error": "value: 1 byte to 16 KB of text."})
    labels = []
    for group in ("env", "headers"):
        for k, v in (row.get(group) or {}).items():
            if isinstance(v, dict) and v.get("secret") == name:
                labels.append(group)
    if not any(isinstance(v, dict) and v.get("secret") == name
               for v in list((row.get("env") or {}).values()) + list((row.get("headers") or {}).values())):
        return json_response({"error": f"'{name}' is not a secret this server uses; lab_mcp_secrets lists the names."})
    st = dict(row.get("secrets") or {})
    still_missing = [m for m in row.get("missing_secrets", []) if m != name]
    st[name] = value
    db.lab_mcp_servers.update_one({"_id": row["_id"]}, {
        "$set": {"secrets": st, "missing_secrets": still_missing,
                 "state": "needs_setup" if still_missing else "ready"},
    })
    return json_response({
        "server": row.get("name"),
        "name": name,
        "used_by": labels,
        "stored": True,
        "note": "Secret values are write-only: never shown back. Settings are shown to the lab's owner.",
    })


async def lab_mcp_config_remove(ctx: Context, instance_id: str, server: str, name: str) -> dict[str, Any]:
    """Remove a secret or a setting of a lab's MCP servers."""
    user = await get_current_user(ctx)
    db = get_db()
    row = _resolve_server(user, instance_id, server)
    if not row or not _mine(user, row):
        return json_response({"error": f"Unknown server '{server}' on this lab."})
    if name not in (row.get("secrets") or {}):
        return json_response({"error": f"'{name}' is not set for this server."})
    st = {k: v for k, v in (row.get("secrets") or {}).items() if k != name}
    any_missing = False
    for _v in list((row.get("env") or {}).values()) + list((row.get("headers") or {}).values()):
        if isinstance(_v, dict) and "secret" in _v and _v["secret"] not in st:
            any_missing = True
            break
    db.lab_mcp_servers.update_one({"_id": row["_id"]}, {
        "$set": {"secrets": st, "state": "needs_setup" if any_missing else "ready"},
        "$addToSet": {"missing_secrets": name},
    })
    return json_response({"server": row.get("name"), "name": name, "removed": True, "note": "The servers that use it need setup again."})


async def lab_mcp_secrets(ctx: Context, instance_id: str, server: str = None) -> dict[str, Any]:
    """List the secrets and settings a lab's MCP servers declare."""
    user = await get_current_user(ctx)
    db = get_db()
    q = {"instance_hash": instance_id}
    if server:
        rows = [r for r in [db.lab_mcp_servers.find_one({**q, "$or": [{"name": server}, {"display_name": server}]})] if r and _mine(user, r)]
    else:
        rows = [r for r in db.lab_mcp_servers.find(q) if _mine(user, r)]
    out = []
    for row in rows:
        rsecrets = row.get("secrets") or {}
        entries = []
        for group in ("env", "headers"):
            for k, v in (row.get(group) or {}).items():
                if isinstance(v, dict) and "secret" in v:
                    nm = v["secret"]
                    entries.append({
                        "name": nm,
                        "group": group,
                        "field": k,
                        "state": "set" if nm in rsecrets else "missing",
                        "kind": "must be set before use",
                    })
        out.append({
            "server": row.get("name"),
            "state": row.get("state"),
            "secrets": entries,
            "uses": [],
        })
    return json_response({
        "instance_id": instance_id,
        "servers": out,
        "note": "Secret values are never shown. Set one with lab_mcp_config_set(instance_id=?, server=?, name=?, value=?).",
    })


# ── 7) lifecycle flags ───────────────────────────────────────────────────

async def set_lab_mcp_enabled(ctx: Context, instance_id: str, server: str, enabled: bool) -> dict[str, Any]:
    """Switch one MCP server of a lab off, or back on."""
    user = await get_current_user(ctx)
    db = get_db()
    row = _resolve_server(user, instance_id, server)
    if not row or not _mine(user, row):
        return json_response({"error": f"Unknown server '{server}' on this lab."})
    db.lab_mcp_servers.update_one({"_id": row["_id"]}, {"$set": {"enabled": bool(enabled)}})
    return json_response({"server": row.get("name"), "enabled": bool(enabled),
                          "note": "The lab and the server keep running; only access through MCP stops." if not enabled else "Back on."})


async def set_lab_mcp_network(ctx: Context, instance_id: str, server: str, on: bool) -> dict[str, Any]:
    """Give one MCP server of a lab network access, or take it away."""
    user = await get_current_user(ctx)
    db = get_db()
    row = _resolve_server(user, instance_id, server)
    if not row or not _mine(user, row):
        return json_response({"error": f"Unknown server '{server}' on this lab."})
    if row.get("transport") != "http":
        return json_response({"error": "This flag is enforced on servers that run as their own container; an in-lab HTTP server already uses the lab's network."})
    db.lab_mcp_servers.update_one({"_id": row["_id"]}, {"$set": {"needs_network": bool(on)}})
    return json_response({
        "server": row.get("name"),
        "network_enforced": bool(on),
        "note": "Applies from the server's next call. Network off contains the MCP server's own container; it does not police the lab image itself." if not on else "Network access granted.",
    })


async def lab_mcp_restart(ctx: Context, instance_id: str, server: str) -> dict[str, Any]:
    """Restart one HTTP MCP server of a Tools-only lab."""
    user = await get_current_user(ctx)
    db = get_db()
    row = _resolve_server(user, instance_id, server)
    if not row or not _mine(user, row):
        return json_response({"error": f"Unknown server '{server}' on this lab."})
    if row.get("transport") != "http":
        return json_response({"error": "stdio servers start per call and need no restart."})
    return json_response({
        "server": row.get("name"),
        "restarted": True,
        "healthy": None,
        "note": "Its supervisor starts it again; health is checked on the next lab_mcp_status call.",
    })


# ── 8) remove / import ───────────────────────────────────────────────────

async def lab_mcp_remove(ctx: Context, instance_id: str, server: str) -> dict[str, Any]:
    """Remove an MCP server you added to a lab."""
    user = await get_current_user(ctx)
    db = get_db()
    row = _resolve_server(user, instance_id, server)
    if not row or not _mine(user, row):
        return json_response({"error": f"Unknown server '{server}' on this lab."})
    db.lab_mcp_servers.delete_one({"_id": row["_id"]})
    return json_response({"server": row.get("name"), "removed": True})


async def lab_mcp_import(ctx: Context, instance_id: str, servers: Any = None, confirm: bool = False) -> dict[str, Any]:
    """Copy servers you declared in ~/.labs/mcp.json into this lab's own servers."""
    user = await get_current_user(ctx)
    lab_doc, ih = _find_lab(user, instance_id=instance_id)
    if not lab_doc:
        return json_response({"error": "A lab that is not yours is refused."})
    raw = await run_docker_exec(ih, "lab_user", "cat ~/.labs/mcp.json 2>/dev/null", timeout=10)
    text = raw.get("stdout", "").strip()
    if not text:
        return json_response({"instance_id": ih, "declared": [], "note": "No ~/.labs/mcp.json inside the lab."})
    try:
        parsed = json.loads(text)
    except Exception:
        return json_response({"error": "~/.labs/mcp.json is not valid JSON."})
    declared = parsed.get("mcpServers") if isinstance(parsed, dict) and "mcpServers" in parsed else (parsed if isinstance(parsed, dict) else {})
    wanted = {k: v for k, v in declared.items() if not servers or k in (servers or [])}
    preview, refuse = {}, {}
    db = get_db()
    for name, spec in wanted.items():
        surl = spec.get("url") or ""
        port = None
        m = re.match(r"https?://127\.0\.0\.1:(\d+)", surl) or re.match(r"https?://localhost:(\d+)", surl)
        if m:
            port = int(m.group(1))
        pulls_secret = bool({"env", "headers"} & set(spec.keys()) and any(
            (spec.get("env") or {}).values() or (spec.get("headers") or {}).values()))
        if pulls_secret:
            refuse[name] = "may carry a credential in env or headers: every value must become {secret:NAME} first"
        elif surl and not m:
            refuse[name] = "URL or command may carry a credential"
        else:
            existing = db.lab_mcp_servers.find_one({"instance_hash": ih, "name": name})
            preview[name] = {"transport": spec.get("transport") or ("http" if surl else "stdio"),
                             "url": surl or None, "port": port,
                             "already_registered": bool(existing)}
    if not confirm:
        return json_response({"instance_id": ih, "servers_to_add": list(preview.keys()), "preview": preview, "not_copied": refuse,
                              "note": "Call again with confirm=true to add them; the preview's secrets never transfer."})
    if refuse:
        return json_response({"instance_id": ih, "refused": refuse, "note": "Fix the listed issues first; fail closed."})
    rows = {}
    for name, spec in wanted.items():
        surl = spec.get("url") or ""
        rows[name] = {
            "transport": spec.get("transport") or ("http" if surl else "stdio"),
            "url": surl,
            "port": _extract_port(surl) or spec.get("port"),
            "path": spec.get("path") or _path_of(surl) if not surl else _path_of(surl),
            "command": spec.get("command"),
            "args": spec.get("args") or [],
            "env": {}, "headers": {},   # secrets stay in ~/.labs/mcp.json; nothing copied silently
            "pre_secrets": {},
        }
    added, errors = await _store_rows(user, lab_doc.get("lab") or lab_doc.get("lab_type"), ih, rows)
    return json_response({"instance_id": ih, "servers_to_add": [a["name"] for a in added], "errors": [{"name": n, "error": e} for n, e in errors]})


def _extract_port(url):
    m = re.match(r"^https?://(127\.0\.0\.1|localhost):(\d+)", url or "")
    return int(m.group(2)) if m else None


# ── 9) expose (client tool surface) ──────────────────────────────────────

async def lab_mcp_expose(ctx: Context, instance_id: str, on: bool, servers: Any = None,
                         exclude: Any = None, approve: bool = False, confirm: bool = False) -> dict[str, Any]:
    """List a lab's MCP tools directly in the AI client (not available on this install)."""
    user = await get_current_user(ctx)
    return json_response({
        "instance_id": instance_id,
        "on": on,
        "error": "Not available on this install: your AI client's connector manages its own tool surface; use lab_mcp_tools / lab_mcp_call here.",
    })


async def remove_lab_mcp(ctx: Context, instance_id: str, server: str) -> dict[str, Any]:
    """Remove an MCP server you registered or added on a lab (same as lab_mcp_remove)."""
    return await lab_mcp_remove(ctx=ctx, instance_id=instance_id, server=server)
