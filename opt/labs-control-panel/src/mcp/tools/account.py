"""
MCP Account Tools
User identity, history, and MCP client management.
"""
from mcp.server.fastmcp import Context
from mcp.types import Tool, TextContent

from typing import Any, Dict, List
from mcp.server.fastmcp import Context

from src.mcp import help_content
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


# Registry: src/mcp/registry.py (entry "whoami") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def whoami(ctx: Context) -> dict[str, Any]:
    """Get current authenticated user identity."""
    user = await get_current_user(ctx)
    return json_response({
        "user_id": user["user_id"],
        "username": user["username"],
        "email": user["email"],
        "client_id": user["client_id"],
        "scopes": user["scopes"]
    })

# Registry: src/mcp/registry.py (entry "list_labs") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

_TEMPLATE_META_CACHE: Dict[str, dict] = {}
SCALE_GROUPS_UNAVAILABLE = (
    "This platform's lab list does not report scale groups, so they cannot be shown here. "
    "That means 'unknown', not 'none'. The web dashboard's Deployments page shows them."
)


def _template_meta(slug) -> dict:
    """Template metadata from lab-templates/<slug>/config.json (cached)."""
    if not slug:
        return {}
    if slug in _TEMPLATE_META_CACHE:
        return _TEMPLATE_META_CACHE[slug]
    import json as _json
    import os
    meta = {}
    try:
        base = "/opt/labs-control-panel/lab-templates"
        path = os.path.join(base, str(slug), "config.json")
        if os.path.exists(path):
            with open(path) as fh:
                cfg = _json.load(fh)
            meta = {
                "lab_name": cfg.get("lab_name"),
                "stability": cfg.get("stability", "beta"),
                "codeserver": bool(cfg.get("scripts", {}).get("code_server")) or
                              os.path.isdir(os.path.join(base, str(slug), "code-extensions")),
            }
    except Exception:
        meta = {}
    _TEMPLATE_META_CACHE[str(slug)] = meta
    return meta


def _lab_instance_row(lab: dict) -> dict:
    """One lab in the dashboard instances[] shape."""
    from src.mcp.tools.account import _template_meta
    slug = lab.get("lab_type")
    meta = _template_meta(slug)
    status_raw = lab.get("status")
    running = status_raw in ("running", "deployed", "healthy")
    if status_raw in ("running", "deployed"):
        status = "deployed"
    elif status_raw in ("stopped", "exited", "paused"):
        status = status_raw if status_raw == "stopped" else ("paused" if status_raw == "paused" else "stopped")
    elif status_raw == "failed":
        status = "failed"
    elif status_raw == "deploying":
        status = "deploying"
    else:
        status = ""
    display = lab.get("display_name") or meta.get("lab_name") or (str(slug).replace("_", " ").title() if slug else "")
    public = bool(lab.get("domains") or lab.get("http_proxies") or lab.get("expose_web"))
    codeserver = bool(meta.get("codeserver"))
    return {
        "instance_id": lab.get("instance_hash"),
        "lab": slug,
        "lab_name": f"[[untrusted]]{display}[[/untrusted]]",
        "display_name": "",
        "status": status,
        "active": running,
        "running": running,
        "health": "",
        "is_copy": False,
        "slot": None,
        "owner_type": "user",
        "type": "machine",
        "dashboard_url": f"/labs/dashboard/{lab.get('instance_hash')}",
        "public": public,
        "ip": lab.get("internal_ip") or lab.get("docker_ip") or "",
        "stability": meta.get("stability", "beta"),
        "free": False,
        "avatar_url": None,
        "icon_url": None,
        "icon": {"kind": "glyph", "url": None, "glyph": (display[:1].upper() if display else None), "colour": None},
        "launch": {"has_launch": not codeserver, "codeserver": codeserver, "action": "code" if codeserver else "launch"},
        "health_state": {"state": "none", "declared": False, "raw": ""},
    }


async def list_labs(ctx: Context) -> dict[str, Any]:
    """List the user's deployed labs and copies. Use it first: it is the only source of instance ids.

    Covers every lab of the account — running, stopped or failed — with the
    slug, name, type, live status, VPN address and launch flags of each.
    """
    user = await get_current_user(ctx)
    ownership = get_ownership()
    labs = ownership.list_user_labs(user)
    instances = [_lab_instance_row(lab) for lab in labs]
    return json_response({
        "labs": {
            "result": "success",
            "count": len(instances),
            "instances": instances,
        },
        "scale_groups_unavailable": SCALE_GROUPS_UNAVAILABLE,
        "total": len(instances),
        "truncated": False,
    })

# Registry: src/mcp/registry.py (entry "my_mcp_clients") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def my_mcp_clients(ctx: Context) -> dict[str, Any]:
    """List MCP clients (connected agents) for the current user."""
    user = await get_current_user(ctx)
    from src.mcp.db import get_db
    db = get_db()

    clients = list(db.mcp_clients.find(
        {"user_id": user["user_id"], "revoked": {"$ne": True}},
        {"client_id": 1, "client_name": 1, "redirect_uris": 1, "scopes": 1, "created_at": 1, "last_used_at": 1}
    ))

    return json_response({
        "clients": [
            {
                "client_id": c["client_id"],
                "client_name": c["client_name"],
                "redirect_uris": c.get("redirect_uris", []),
                "scopes": c.get("scopes", ["labs:*"]),
                "connected_at": str(c.get("created_at", "")),
                "last_used_at": str(c.get("last_used_at", ""))
            }
            for c in clients
        ],
        "count": len(clients)
    })

# Registry: src/mcp/registry.py (entry "my_history") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def my_history(ctx: Context, limit: int = 50) -> dict[str, Any]:
    """Get recent activity history for the current user."""
    user = await get_current_user(ctx)
    from src.mcp.db import get_db
    db = get_db()

    # Try to get activity from audit log or deploy logs
    activities = []

    # Check machine_labs for deploy history
    labs = list(db.machine_labs.find(
        {"user_id": user["user_id"]},
        {"instance_hash": 1, "lab_name": 1, "lab_type": 1, "status": 1, "created_at": 1, "last_error": 1}
    ).sort("created_at", -1).limit(limit))

    for lab in labs:
        activities.append({
            "type": "lab_deploy",
            "instance_hash": lab["instance_hash"],
            "lab_name": lab.get("lab_name"),
            "lab_type": lab.get("lab_type"),
            "status": lab.get("status"),
            "timestamp": str(lab.get("created_at", "")),
            "error": lab.get("last_error")
        })

    return json_response({
        "activities": activities,
        "count": len(activities)
    })

# Registry: src/mcp/registry.py (entry "help") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def help(ctx: Context, topic: str = "", mode: str = "") -> dict[str, Any]:
    """Get help about this server: overview, a topic guide, or the full tool index.

    Args:
        topic: help topic id (e.g. "getting-started", "security"); implies mode="detail".
        mode: "overview" (default) | "detail" | "index" (machine-readable tool catalog).
    """
    await get_current_user(ctx)
    return json_response(help_content.render_help(topic=topic, mode=mode))

# Registry: src/mcp/registry.py (entry "lookup") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def lookup(ctx: Context, name: str = "", kind: str = "", limit: int = 20) -> dict[str, Any]:
    """Find your resources by name across labs, domains and devices (partial match).

    Args:
        name: substring to search for (empty lists everything you own, capped).
        kind: "lab" | "domain" | "device" (empty = all kinds).
        limit: max results per kind (1-100).
    """
    user = await get_current_user(ctx)
    db = get_db()
    uid = user["user_id"]
    needle = (name or "").strip().lower()
    limit = max(1, min(int(limit or 20), 100))
    want = (kind or "").strip().lower()

    results: List[dict] = []

    if want in ("", "lab"):
        for lab in db.machine_labs.find(
            {"user_id": uid},
            {"instance_hash": 1, "lab_name": 1, "lab_type": 1, "status": 1, "created_at": 1},
        ).limit(limit):
            hay = f"{lab.get('lab_name', '')} {lab.get('instance_hash', '')}".lower()
            if needle and needle not in hay:
                continue
            results.append({
                "kind": "lab",
                "id": lab.get("instance_hash"),
                "name": lab.get("lab_name"),
                "type": lab.get("lab_type"),
                "status": lab.get("status"),
            })

    if want in ("", "domain"):
        for d in db.domains.find(
            {"user_id": uid, "status": {"$ne": "deleted"}},
            {"domain": 1, "type": 1, "verified": 1, "in_use": 1},
        ).limit(limit):
            if needle and needle not in str(d.get("domain", "")).lower():
                continue
            results.append({
                "kind": "domain",
                "id": d.get("domain"),
                "name": d.get("domain"),
                "type": d.get("type"),
                "verified": d.get("verified"),
                "in_use": d.get("in_use"),
            })

    if want in ("", "device"):
        for dev in db.devices.find(
            {"user_id": uid, "status": {"$ne": "deleted"}},
            {"device_name": 1, "device_type": 1, "assigned_ip": 1, "status": 1},
        ).limit(limit):
            hay = f"{dev.get('device_name', '')} {dev.get('assigned_ip', '')}".lower()
            if needle and needle not in hay:
                continue
            results.append({
                "kind": "device",
                "id": dev.get("device_name"),
                "name": dev.get("device_name"),
                "type": dev.get("device_type"),
                "ip": dev.get("assigned_ip"),
                "status": dev.get("status"),
            })

    if want and want not in ("lab", "domain", "device"):
        return json_response({
            "success": False,
            "error": f"unknown kind: {kind!r}",
            "available": ["lab", "domain", "device"],
        })

    return json_response({
        "query": {"name": name, "kind": want or "all"},
        "results": results[: limit * 3],
        "count": len(results),
    })
