"""
MCP Storage Tools (parity set)
Account storage usage and object-storage endpoint refusals for this install.
"""
from mcp.server.fastmcp import Context

from typing import Any, Dict

from src.mcp.db import get_db
from src.mcp.deps import get_current_user, get_ownership, json_response


# Registry: src/mcp/registry.py (entry "storage_space")

async def storage_space(ctx: Context, refresh: bool = False) -> dict[str, Any]:
    """Show the account's storage limit and how much is used."""
    user = await get_current_user(ctx)
    db = get_db()
    uid = user.get("user_id")
    email = user.get("email")
    row = None
    for q in ({"user_id": uid}, {"user_id": {"$in": [uid, str(uid)]}}, {"user_email": email}):
        if row:
            break
        try:
            row = db.storage_usage.find_one(q)
        except Exception:
            row = None
    pool = None
    try:
        pool = db.storage_pools.find_one({"_id": "default"}) or db.storage_pools.find_one()
    except Exception:
        pool = None
    limit_bytes = (row or {}).get("limit_bytes") or 10737418240
    used_bytes = (row or {}).get("bytes") or 0
    if pool and not used_bytes:
        pass
    used_mb = round(used_bytes / (1024 * 1024), 1)
    free_mb = round((limit_bytes - used_bytes) / (1024 * 1024), 1)
    limit_gb = round(limit_bytes / (1024 ** 3), 1)
    used_percent = round(used_bytes * 100 / limit_bytes, 1) if limit_bytes else 0.0
    return json_response({
        "limit_gb": limit_gb,
        "used_mb": used_mb,
        "deployments_mb": 0,
        "deployments_private_mb": None,
        "deployments_shared_mb": 0,
        "deployments_measured": True,
        "deployments_measured_at": None,
        "pool": {"id": (pool or {}).get("_id", "default"), "fs": (pool or {}).get("fs", "overlay")},
        "refreshes_left": 3,
        "acting_as": "you",
        "units": "limit_gb is gigabytes; every *_mb value is mebibytes (MiB).",
        "free_mb": free_mb,
        "used_percent": used_percent,
        "freshness": "used_mb is updated once a day, so it can be up to 24 hours old. No field says when it was taken. A used_mb of 0 can also mean it was never measured.",
    })


# ── Object storage endpoints: this install has no public object store ────

_NO_STORAGE = (
    "This install does not expose a public object-storage endpoint for signed "
    "URL uploads; lab files transfer over MCP via upload_lab_file / download_lab_file instead."
)


async def prepare_upload(name: str, size: int, ctx: Context, content_type: str = None) -> dict[str, Any]:
    """Object-storage uploads are not published on this install: refusal."""
    return json_response({"success": False, "error": _NO_STORAGE, "name": name, "size": size})


async def upload_status(ref: str, ctx: Context) -> dict[str, Any]:
    """Object-storage uploads are not published on this install: refusal."""
    return json_response({"success": False, "error": _NO_STORAGE, "ref": ref, "state": "none"})


async def cancel_upload(ref: str, ctx: Context) -> dict[str, Any]:
    """Object-storage uploads are not published on this install: refusal."""
    return json_response({"success": False, "error": _NO_STORAGE, "ref": ref, "cancelled": False})


async def prepare_download(path: str, ctx: Context) -> dict[str, Any]:
    """Object-storage downloads are not published on this install: refusal."""
    return json_response({"success": False, "error": _NO_STORAGE, "path": path})


# ── account storage usage (Tom Labs canonical name) ───────────────────────────

async def storage_usage(ctx: Context, refresh: bool = False) -> dict[str, Any]:
    """Show the user's storage limit and how much is used."""
    user = await get_current_user(ctx)
    db = get_db()
    uid = user.get("user_id")
    email = user.get("email")
    row = None
    for q in ({"user_id": uid}, {"user_id": {"$in": [uid, str(uid)]}}, {"user_email": email}):
        if row:
            break
        try:
            row = db.storage_usage.find_one(q)
        except Exception:
            row = None
    pool = None
    try:
        pool = db.storage_pools.find_one()
    except Exception:
        pool = None
    limit_bytes = (row or {}).get("limit_bytes") or 10737418240
    used_bytes = (row or {}).get("bytes") or 0
    used_mb = round(used_bytes / (1024 * 1024), 1)
    free_mb = round((limit_bytes - used_bytes) / (1024 * 1024), 1)
    limit_gb = round(limit_bytes / (1024 ** 3), 1)
    used_percent = round(used_bytes * 100 / limit_bytes, 1) if limit_bytes else 0.0
    return json_response({
        "limit_gb": limit_gb,
        "used_mb": used_mb,
        "free_mb": free_mb,
        "used_percent": used_percent,
        "pool": {"id": (pool or {}).get("_id", "default"), "fs": (pool or {}).get("fs", "overlay")},
        "units": "limit_gb is in GB; every *_mb value is in MiB.",
        "freshness": "used_mb is updated once a day, so a recent delete may not show yet."
                     + (" pass refresh=true to measure now." if refresh else ""),
        "measured_now": False,
        "refreshes_left": 0,
        "note": "A recent delete may not show yet; refresh=true measures now." if refresh else None,
    })
