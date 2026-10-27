"""
MCP Lifecycle Tools
Deploy, stop, start, pause, resume, terminate, renew labs.
"""
from mcp.server.fastmcp import Context
from mcp.types import Tool, TextContent

import logging
from typing import Any, Dict

logger = logging.getLogger(__name__)
from mcp.server.fastmcp import Context

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


# Registry: src/mcp/registry.py (entry "lab_status") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def lab_status(lab: str, ctx: Context) -> dict[str, Any]:
    """Check if a lab is running, stopped, etc."""
    user = await get_current_user(ctx)
    ownership = get_ownership()
    instance_hash = ownership.resolve_lab(user, lab_name=lab)

    from src.mcp.db import get_db
    db = get_db()

    doc = db.machine_labs.find_one(
        {"instance_hash": instance_hash},
        {"status": 1, "lab_type": 1, "lab_name": 1, "created_at": 1}
    )

    if not doc:
        return json_response({"error": "Lab not found"})

    return json_response({
        "instance_hash": instance_hash,
        "lab_name": doc.get("lab_name"),
        "lab_type": doc.get("lab_type"),
        "status": doc.get("status"),
        "created_at": str(doc.get("created_at", ""))
    })

# Registry: src/mcp/registry.py (entry "lab_info") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def lab_info(lab: str, ctx: Context) -> dict[str, Any]:
    """Get full lab information including credentials."""
    user = await get_current_user(ctx)
    ownership = get_ownership()
    instance_hash = ownership.resolve_lab(user, lab_name=lab)

    from src.mcp.db import get_db
    db = get_db()

    doc = db.machine_labs.find_one(
        {"instance_hash": instance_hash},
        {"credentials": 1, "lab_type": 1, "lab_name": 1, "status": 1,
         "code_domain": 1, "gui_domain": 1, "domains": 1, "docker_ip": 1,
         "internal_ip": 1, "expose_web": 1, "http_proxies": 1}
    )

    if not doc:
        return json_response({"error": "Lab not found"})

    creds = doc.get("credentials", {})
    return json_response({
        "instance_hash": instance_hash,
        "lab_name": doc.get("lab_name"),
        "lab_type": doc.get("lab_type"),
        "status": doc.get("status"),
        "code_domain": doc.get("code_domain"),
        "gui_domain": doc.get("gui_domain"),
        "domains": doc.get("domains", []),
        "docker_ip": doc.get("docker_ip") or creds.get("docker_ip"),
        "tunnel_ip": creds.get("tunnel_ip"),
        "credentials": {
            "ssh": creds.get("ssh"),
            "ssh_proxy": creds.get("ssh_proxy"),
            "code_server_url": creds.get("code_server_url"),
            "gui_url": creds.get("gui_url"),
            "password": creds.get("password"),
            "su_pass": creds.get("su_pass"),
            "vnc_pass": creds.get("vnc_pass"),
            "wg_pubkey": creds.get("wg_pubkey"),
        } if creds else None
    })

# Registry: src/mcp/registry.py (entry "lab_logs") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def lab_logs(lab: str, ctx: Context, lines: int = 100) -> dict[str, Any]:
    """Get deployment logs for a lab."""
    user = await get_current_user(ctx)
    ownership = get_ownership()
    instance_hash = ownership.resolve_lab(user, lab_name=lab)

    from src.mcp.db import get_db
    db = get_db()

    # Get from instances collection (deploy_log)
    inst_doc = db.instances.find_one(
        {"instance_hash": instance_hash},
        {"deploy_log": 1}
    )

    if not inst_doc:
        # Check machine_labs for any error info
        lab_doc = db.machine_labs.find_one(
            {"instance_hash": instance_hash},
            {"last_error": 1, "error_at": 1, "status": 1}
        )
        if lab_doc:
            return json_response({
                "instance_hash": instance_hash,
                "logs": f"Status: {lab_doc.get('status')}\nLast error: {lab_doc.get('last_error', 'None')}\nError at: {lab_doc.get('error_at', 'N/A')}",
                "source": "machine_labs"
            })
        return json_response({"error": "Lab not found in instances"})

    deploy_log = inst_doc.get("deploy_log", "")
    if deploy_log:
        log_lines = deploy_log.strip().split('\n')
        return json_response({
            "instance_hash": instance_hash,
            "logs": '\n'.join(log_lines[-lines:]),
            "source": "deploy_log"
        })

    return json_response({
        "instance_hash": instance_hash,
        "logs": "No deploy logs available",
        "source": "deploy_log"
    })

# Registry: src/mcp/registry.py (entry "deploy_lab") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def deploy_lab(lab: str, ctx: Context) -> dict[str, Any]:
    """Redeploy an existing lab (provision or re-provision it).

    Requires an existing lab — pass `lab` as the instance_hash returned
    by list_labs (or the exact lab name). Does NOT create new labs.
    Returns deployment result with logs. For real-time progress tracking,
    poll deploy_progress after calling this tool.
    """
    user = await get_current_user(ctx)
    ownership = get_ownership()
    instance_hash = ownership.resolve_lab(user, lab_name=lab)

    result = await run_labsctl("lab", "deploy", f"--hash={instance_hash}", f"--user={user['username']}", timeout=300)
    
    # Add progress info to result
    from src.mcp.deploy_progress import get_progress_from_deploy_log
    deploy_log = {"logs": result.get("stdout", "").strip().split('\n') if result.get("stdout") else []}
    progress = get_progress_from_deploy_log(deploy_log, "deploy")
    result["progress"] = progress
    
    return json_response(result)

# Registry: src/mcp/registry.py (entry "stop_lab") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def stop_lab(lab: str, ctx: Context) -> dict[str, Any]:
    """Stop a running lab."""
    user = await get_current_user(ctx)
    ownership = get_ownership()
    instance_hash = ownership.resolve_lab(user, lab_name=lab)

    result = await run_labsctl("lab", "stop", f"--hash={instance_hash}", timeout=60)
    return json_response(result)

# Registry: src/mcp/registry.py (entry "start_lab") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def start_lab(lab: str, ctx: Context) -> dict[str, Any]:
    """Start a stopped lab."""
    user = await get_current_user(ctx)
    ownership = get_ownership()
    instance_hash = ownership.resolve_lab(user, lab_name=lab)

    result = await run_labsctl("lab", "start", f"--hash={instance_hash}", timeout=60)
    return json_response(result)

# Registry: src/mcp/registry.py (entry "deploy_progress") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def deploy_progress(lab: str, ctx: Context) -> dict[str, Any]:
    """Get real-time deployment progress for a lab.
    
    Poll this tool after calling deploy_lab or redeploy_lab
    to get current progress percentage and status.
    
    Returns:
    - progress: Percentage (0-100)
    - label: Current step description
    - status: "pending", "running", "completed", or "failed"
    - current_step: Formatted progress string
    - logs: Recent log lines
    """
    user = await get_current_user(ctx)
    ownership = get_ownership()
    instance_hash = ownership.resolve_lab(user, lab_name=lab)

    from src.mcp.db import get_db
    db = get_db()

    # Get deploy_log from machine_labs
    lab_doc = db.machine_labs.find_one(
        {"instance_hash": instance_hash},
        {"deploy_log": 1, "deploy": 1, "status": 1}
    )

    if not lab_doc:
        return json_response({"error": "Lab not found"})

    deploy_log = lab_doc.get("deploy_log", {})
    deploy_info = lab_doc.get("deploy", {})
    lab_status = lab_doc.get("status", "unknown")

    # Check if deployment is in progress
    if lab_status == "deploying" or deploy_info.get("status") == "deploying":
        # Parse logs for progress
        from src.mcp.deploy_progress import parse_deploy_progress
        logs = deploy_log.get("logs", [])
        progress = parse_deploy_progress(logs, "deploy")
        progress["recent_logs"] = logs[-10:] if logs else []
        return json_response(progress)

    # Deployment completed or failed
    from src.mcp.deploy_progress import get_progress_from_deploy_log
    progress = get_progress_from_deploy_log(deploy_log, "deploy")
    progress["recent_logs"] = deploy_log.get("logs", [])[-10:] if deploy_log else []
    
    return json_response(progress)

# Registry: src/mcp/registry.py (entry "pause_lab") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def pause_lab(lab: str, ctx: Context) -> dict[str, Any]:
    """Pause a lab (freeze container). Zero CPU, memory preserved, resume is instant."""
    user = await get_current_user(ctx)
    ownership = get_ownership()
    instance_hash = ownership.resolve_lab(user, lab_name=lab)
    
    if not run_docker_host:
        return json_response({"error": "Docker host commands not available"})
    
    # 1. Pause container on host
    result = await run_docker_host(f"docker pause {instance_hash}", timeout=30)
    if not result.get("success"):
        return json_response({"error": f"Failed to pause container: {result.get('stderr', 'Unknown error')}"})
    
    # 2. Update status in MongoDB
    try:
        from src.mcp.db import get_db
        db = get_db()
        db.machine_labs.update_one(
            {"instance_hash": instance_hash},
            {"$set": {"status": "paused"}}
        )
    except Exception as e:
        logger.warning(f"Failed to update DB status: {e}")
    
    return json_response({"success": True, "message": "Lab paused", "hash": instance_hash})

# Registry: src/mcp/registry.py (entry "resume_lab") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def resume_lab(lab: str, ctx: Context) -> dict[str, Any]:
    """Resume a paused lab. Restores CPU, memory, network instantly."""
    user = await get_current_user(ctx)
    ownership = get_ownership()
    instance_hash = ownership.resolve_lab(user, lab_name=lab)
    
    if not run_docker_host:
        return json_response({"error": "Docker host commands not available"})
    
    # 1. Resume container on host
    result = await run_docker_host(f"docker unpause {instance_hash}", timeout=30)
    if not result.get("success"):
        return json_response({"error": f"Failed to resume container: {result.get('stderr', 'Unknown error')}"})
    
    # 2. Update status in MongoDB
    try:
        from src.mcp.db import get_db
        db = get_db()
        db.machine_labs.update_one(
            {"instance_hash": instance_hash},
            {"$set": {"status": "running"}}
        )
    except Exception as e:
        logger.warning(f"Failed to update DB status: {e}")
    
    return json_response({"success": True, "message": "Lab resumed", "hash": instance_hash})

# Registry: src/mcp/registry.py (entry "terminate_lab") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def terminate_lab(lab: str, ctx: Context, confirm: bool = False) -> dict[str, Any]:
    """Permanently delete a lab."""
    if not confirm:
        return json_response({"error": "Confirmation required. Pass confirm=true to proceed."})

    user = await get_current_user(ctx)
    ownership = get_ownership()
    instance_hash = ownership.resolve_lab(user, lab_name=lab)

    result = await run_labsctl("lab", "remove", f"--hash={instance_hash}", timeout=60)
    return json_response(result)

# Registry: src/mcp/registry.py (entry "renew_lab") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def renew_lab(lab: str, ctx: Context, days: int = 7) -> dict[str, Any]:
    """Renew lab expiration (extend TTL)."""
    user = await get_current_user(ctx)
    ownership = get_ownership()
    instance_hash = ownership.resolve_lab(user, lab_name=lab)

    from src.mcp.db import get_db
    db = get_db()

    # Update expiration in machine_labs
    from datetime import datetime, timedelta
    new_expiry = datetime.utcnow() + timedelta(days=days)

    db.machine_labs.update_one(
        {"instance_hash": instance_hash, "user_id": user["user_id"]},
        {"$set": {"expires_at": new_expiry}}
    )

    return json_response({
        "instance_hash": instance_hash,
        "renewed_until": new_expiry.isoformat()
    })

# Registry: src/mcp/registry.py (entry "ensure_codeserver") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def ensure_codeserver(lab: str, ctx: Context) -> dict[str, Any]:
    """Start code-server on demand."""
    user = await get_current_user(ctx)
    ownership = get_ownership()
    instance_hash = ownership.resolve_lab(user, lab_name=lab)

    result = await run_labsctl("lab", "ensure-codeserver", f"--hash={instance_hash}", f"--user={user['username']}", timeout=60)
    return json_response(result)

# Registry: src/mcp/registry.py (entry "code_status") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def code_status(lab: str, ctx: Context) -> dict[str, Any]:
    """Check if code-server is running."""
    user = await get_current_user(ctx)
    ownership = get_ownership()
    instance_hash = ownership.resolve_lab(user, lab_name=lab)

    result = await run_labsctl("lab", "code-status", f"--hash={instance_hash}", f"--user={user['username']}", timeout=30)
    return json_response(result)

# Registry: src/mcp/registry.py (entry "run_startup_script") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def run_startup_script(lab: str, ctx: Context) -> dict[str, Any]:
    """Run the lab's init.sh startup script."""
    user = await get_current_user(ctx)
    ownership = get_ownership()
    instance_hash = ownership.resolve_lab(user, lab_name=lab)

    result = await run_labsctl("lab", "run-script", f"--hash={instance_hash}", f"--user={user['username']}", timeout=120)
    return json_response(result)

# Registry: src/mcp/registry.py (entry "apply_preferences") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def apply_preferences(lab: str, ctx: Context) -> dict[str, Any]:
    """Hot-reload Traefik + run init.sh."""
    user = await get_current_user(ctx)
    ownership = get_ownership()
    instance_hash = ownership.resolve_lab(user, lab_name=lab)

    result = await run_labsctl("lab", "apply-preferences", f"--hash={instance_hash}", f"--user={user['username']}", timeout=120)
    return json_response(result)

# Registry: src/mcp/registry.py (entry "wait_for_deploy") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def wait_for_deploy(lab: str, ctx: Context, timeout: int = 300) -> dict[str, Any]:
    """Wait for lab deployment to complete."""
    import asyncio
    user = await get_current_user(ctx)
    ownership = get_ownership()
    instance_hash = ownership.resolve_lab(user, lab_name=lab)

    from src.mcp.db import get_db
    db = get_db()

    start_time = asyncio.get_event_loop().time()
    while True:
        if asyncio.get_event_loop().time() - start_time > timeout:
            return json_response({"error": "Timeout waiting for deployment", "instance_hash": instance_hash})

        doc = db.machine_labs.find_one(
            {"instance_hash": instance_hash},
            {"status": 1, "last_error": 1}
        )

        if doc:
            status = doc.get("status")
            if status == "running":
                return json_response({"instance_hash": instance_hash, "status": "running", "message": "Deployment complete"})
            elif status == "error":
                return json_response({"instance_hash": instance_hash, "status": "error", "error": doc.get("last_error")})

        await asyncio.sleep(5)

# Registry: src/mcp/registry.py (entry "test_autologin") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def test_autologin(lab: str, ctx: Context) -> dict[str, Any]:
    """Test SSH auto-login to lab."""
    user = await get_current_user(ctx)
    ownership = get_ownership()
    instance_hash = ownership.resolve_lab(user, lab_name=lab)

    from src.mcp.db import get_db
    db = get_db()

    doc = db.machine_labs.find_one(
        {"instance_hash": instance_hash},
        {"credentials": 1}
    )

    if not doc or not doc.get("credentials"):
        return json_response({"error": "No credentials found"})

    creds = doc["credentials"]
    tunnel_ip = creds.get("tunnel_ip")
    username = user["username"]

    if not tunnel_ip:
        return json_response({"error": "No tunnel IP available"})

    # Test SSH connection
    cmd = f"ssh -o ConnectTimeout=5 -o BatchMode=yes -o StrictHostKeyChecking=no {username}@{tunnel_ip} 'echo OK'"
    result = await run_docker_exec(instance_hash, username, cmd, timeout=15)

    return json_response({
        "instance_hash": instance_hash,
        "test": "ssh_autologin",
        "success": result["success"],
        "output": result["stdout"],
        "error": result["stderr"] if not result["success"] else None
    })

# Registry: src/mcp/registry.py (entry "verify_restart") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def verify_restart(lab: str, ctx: Context) -> dict[str, Any]:
    """Verify lab restarts correctly."""
    user = await get_current_user(ctx)
    ownership = get_ownership()
    instance_hash = ownership.resolve_lab(user, lab_name=lab)

    # Stop then start
    stop_result = await run_labsctl("lab", "stop", f"--hash={instance_hash}", timeout=60)
    if not stop_result["success"]:
        return json_response({"error": "Stop failed", "details": stop_result})

    import asyncio
    await asyncio.sleep(3)

    start_result = await run_labsctl("lab", "start", f"--hash={instance_hash}", timeout=60)
    if not start_result["success"]:
        return json_response({"error": "Start failed", "details": start_result})

    # Verify running
    await asyncio.sleep(5)
    from src.mcp.db import get_db
    db = get_db()
    doc = db.machine_labs.find_one(
        {"instance_hash": instance_hash},
        {"status": 1}
    )

    return json_response({
        "instance_hash": instance_hash,
        "verified": doc.get("status") == "running",
        "status": doc.get("status") if doc else "unknown"
    })

# Registry: src/mcp/registry.py (entry "lab_dashboard") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def lab_dashboard(lab: str, ctx: Context) -> dict[str, Any]:
    """One-screen dashboard for a lab: state, expiry, resources, domains, code-server health.

    Credentials are not included — use lab_info for those.
    """
    user = await get_current_user(ctx)
    ownership = get_ownership()
    instance_hash = ownership.resolve_lab(user, lab_name=lab)
    db = get_db()
    doc = db.machine_labs.find_one({"instance_hash": instance_hash}) or {}

    home = f"/var/labsstorage/home/{user['username']}"
    snap = await run_docker_exec(
        instance_hash,
        "root",
        "echo LOAD $(cut -d' ' -f1-3 /proc/loadavg 2>/dev/null); "
        f"echo MEM $(free -m 2>/dev/null | awk '/^Mem:/{{print $2,$3,$7}}'); "
        f"echo DISK $(df -h {home} 2>/dev/null | tail -1 | awk '{{print $2,$3,$4,$5}}'); "
        "echo UPTIME $(cut -d' ' -f1 /proc/uptime 2>/dev/null); "
        "echo CODE $(pgrep -f code-server >/dev/null 2>&1 && echo running || echo stopped)",
        timeout=12,
    )

    resources: Dict[str, str] = {}
    for line in snap.get("stdout", "").splitlines():
        parts = line.strip().split(" ", 1)
        if len(parts) == 2 and parts[0] in ("LOAD", "MEM", "DISK", "UPTIME", "CODE"):
            resources[parts[0].lower()] = parts[1].strip()

    expires_at = doc.get("expires_at")
    days_left = None
    if expires_at is not None:
        try:
            from datetime import datetime, timezone
            exp = expires_at if isinstance(expires_at, datetime) else datetime.fromisoformat(str(expires_at).replace("Z", "+00:00"))
            if exp.tzinfo is None:
                exp = exp.replace(tzinfo=timezone.utc)
            days_left = (exp - datetime.now(timezone.utc)).days
        except Exception:
            days_left = None

    return json_response({
        "lab": {
            "instance_hash": instance_hash,
            "lab_name": doc.get("lab_name"),
            "lab_type": doc.get("lab_type"),
            "status": doc.get("status"),
            "created_at": str(doc.get("created_at", "")),
            "expires_at": str(expires_at) if expires_at is not None else None,
            "days_left": days_left,
            "last_error": doc.get("last_error"),
        },
        "resources": resources,
        "domains": doc.get("domains") or [],
        "code_domain": doc.get("code_domain"),
        "gui_domain": doc.get("gui_domain"),
        "docker_ip": doc.get("docker_ip"),
        "success": True,
    })

# Registry: src/mcp/registry.py (entry "list_tasks") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def list_tasks(ctx: Context, limit: int = 20) -> dict[str, Any]:
    """List your platform tasks: queued deploy jobs and labs currently deploying/failed."""
    user = await get_current_user(ctx)
    db = get_db()
    uid = user["user_id"]
    limit = max(1, min(int(limit or 20), 50))

    queue = []
    try:
        for job in db.deploy_queue.find({"user_id": uid}).sort("created_at", -1).limit(limit):
            queue.append({
                "id": str(job.get("_id")),
                "lab": job.get("instance_hash") or job.get("lab"),
                "status": job.get("status"),
                "created_at": str(job.get("created_at", "")),
                "detail": job.get("message") or job.get("error"),
            })
    except Exception as e:
        logger.warning(f"deploy_queue read failed: {e}")

    deploys = []
    for lab in db.machine_labs.find(
        {"user_id": uid, "status": {"$in": ["deploying", "queued", "building", "error"]}},
        {"instance_hash": 1, "lab_name": 1, "status": 1, "created_at": 1, "last_error": 1},
    ).sort("created_at", -1).limit(limit):
        deploys.append({
            "lab": lab.get("lab_name"),
            "instance_hash": lab.get("instance_hash"),
            "status": lab.get("status"),
            "created_at": str(lab.get("created_at", "")),
            "error": lab.get("last_error"),
        })

    return json_response({
        "deploy_queue": queue,
        "lab_deploys": deploys,
        "counts": {"queued_jobs": len(queue), "in_flight_deploys": len(deploys)},
        "count": len(queue) + len(deploys),
    })


# ── Tom Labs-parity names (aliases) ───────────────────────────────────────────

async def run_lab_startup_script(lab: str, ctx: Context) -> dict[str, Any]:
    """Run the lab's startup script now."""
    return await run_startup_script(lab, ctx)


async def test_auto_login(lab: str, ctx: Context) -> dict[str, Any]:
    """Test SSH auto-login to a lab."""
    return await test_autologin(lab, ctx)


# ── Tom Labs parity: routing, runtime, passwords, startup script, error page ──

import re as _re
import shlex as _shlex


def _lab_doc(db, user, lab, instance_id=None):
    ownership = get_ownership()
    if instance_id and isinstance(instance_id, str) and _re.match(r"^[0-9a-f]{32}$", instance_id):
        doc = db.machine_labs.find_one({"instance_hash": instance_id})
        if doc and str(doc.get("user_id")) in [str(x) for x in ownership._user_identifiers(user)]:
            return doc, instance_id
        return None, instance_id
    instance_hash = ownership.resolve_lab(user, lab_name=lab)
    return db.machine_labs.find_one({"instance_hash": instance_hash}), instance_hash


_PASSWORD_BAD = "at most 128 characters, no control characters, no `=`, no spaces at the start or end"


def _password_valid(pw):
    if pw is None:
        return True
    if not isinstance(pw, str) or not pw or len(pw) > 128:
        return False
    if any(ord(c) < 32 or ord(c) == 127 for c in pw):
        return False
    if "=" in pw or pw != pw.strip():
        return False
    if _re.search(r"<[A-Za-z]", pw):
        return False
    return True


async def set_lab_password(lab: str, ctx: Context, instance_id: str = None,
                           sudo: str = None, code_server: str = None, vnc: str = None) -> dict[str, Any]:
    """Set the sudo, code-server or VNC password for a lab (applies on next deploy)."""
    user = await get_current_user(ctx)
    db = get_db()
    doc, ih = _lab_doc(db, user, lab, instance_id)
    if not doc:
        return json_response({"error": f"Unknown lab '{lab}'."})
    for name, pw in (("sudo", sudo), ("code_server", code_server), ("vnc", vnc)):
        if not _password_valid(pw):
            return json_response({"error": f"{name}: {_PASSWORD_BAD}"})
    settings = doc.get("lab_settings") or {}
    if sudo is not None:
        settings["sudo"] = sudo
    if code_server is not None:
        settings["code_server"] = code_server
    if vnc is not None:
        settings["vnc"] = vnc
    db.machine_labs.update_one({"_id": doc["_id"]}, {"$set": {"lab_settings": settings}})
    return json_response({
        "lab": lab,
        "instance_id": ih,
        "saved": {k: ("<set>" if settings.get(k) else "<empty>") for k in ("sudo", "code_server", "vnc") if k in settings},
        "note": "The password is saved, not applied. The running lab keeps its old password until the next deploy.",
    })


async def configure_lab_runtime(lab: str, ctx: Context, instance_id: str = None,
                                ssh_keys: list = None, vpn_networks: list = None,
                                always_on: bool = None, preferred_ip: str = None) -> dict[str, Any]:
    """Set a lab's SSH keys, VPN networks, always-on flag or preferred IP (applies on next deploy)."""
    user = await get_current_user(ctx)
    db = get_db()
    doc, ih = _lab_doc(db, user, lab, instance_id)
    if not doc:
        return json_response({"error": f"Unknown lab '{lab}'."})
    updates = {}
    saved = []
    if ssh_keys is not None:
        if not isinstance(ssh_keys, list) or len(ssh_keys) > 32:
            return json_response({"error": "ssh_keys: pass a list of at most 32 key fingerprints."})
        updates.setdefault("runtime", {})["ssh_keys"] = ssh_keys
        saved.append("ssh_keys")
    if vpn_networks is not None:
        if not isinstance(vpn_networks, list) or len(vpn_networks) > 4:
            return json_response({"error": "vpn_networks: pass a list of at most 4 interface names."})
        updates.setdefault("runtime", {})["vpn_networks"] = vpn_networks
        saved.append("vpn_networks")
    if always_on is not None:
        updates.setdefault("runtime", {})["always_on"] = bool(always_on)
        saved.append("always_on")
    if preferred_ip is not None:
        updates.setdefault("runtime", {})["preferred_ip"] = str(preferred_ip)
        saved.append("preferred_ip")
    if updates:
        db.machine_labs.update_one({"_id": doc["_id"]}, {"$set": updates})
    return json_response({
        "lab": lab,
        "instance_id": ih,
        "saved": saved or [],
        "note": "Saved, not applied: call deploy_lab afterwards. This does not change routing.",
    })


async def configure_lab_routing(lab: str, ctx: Context, instance_id: str = None,
                                domains: list = None, public: bool = None,
                                http_proxies: list = None, expose_proxies: list = None,
                                tcp_ports: list = None, remove_ports: list = None,
                                code_server_domain: str = None, replace: bool = False,
                                confirm_replace: bool = False, apply: bool = False) -> dict[str, Any]:
    """Attach registered domains to a lab's public exposure and apply them."""
    user = await get_current_user(ctx)
    db = get_db()
    doc, ih = _lab_doc(db, user, lab, instance_id)
    if not doc:
        return json_response({"error": f"Unknown lab '{lab}'."})
    if (http_proxies or expose_proxies or tcp_ports or remove_ports or code_server_domain):
        return json_response({
            "error": "This install routes domains only: public HTTPS proxies, exposed proxies and raw TCP ports are not published here.",
        })
    if domains is None and public is None:
        routing = doc.get("domains") or []
        return json_response({
            "lab": lab,
            "instance_id": ih,
            "domains": routing,
            "public": bool(routing),
            "verified": [],
            "note": "Send `public` together with `domains`; one without the other is refused. Removed domains are dropped at the next deploy.",
        })
    if not domains:
        return json_response({"error": "Send both `public: true` and the COMPLETE `domains` list. One without the other is refused."})
    if len(domains) != len(set(d.lower().strip() for d in domains)):
        return json_response({"error": "Duplicate entries in `domains`: the list is the COMPLETE list, so duplicates make no sense."})
    if not replace and not confirm_replace and (doc.get("domains") or []):
        return json_response({
            "error": "Calling this in a session that has not read the lab's routing needs confirm_replace=true. Read the stored routing first or pass confirm_replace=true.",
            "stored": doc.get("domains") or [],
        })
    bad = [d for d in domains if not _re.match(r"^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$", d.lower().strip())]
    if bad:
        return json_response({"error": f"Invalid domain names: {', '.join(bad)}"})
    unverified = []
    for d in domains:
        d = d.lower().strip()
        ddoc = db.domains.find_one({"domain": d})
        if not ddoc or ddoc.get("user_id") != user.get("user_id"):
            return json_response({"error": f"Domain '{d}' is not in your account. Register it first (register_domain / claim_tom_domain)."})
        if not ddoc.get("verified", False):
            unverified.append(d)
    if unverified:
        return json_response({"error": f"Unverified domain(s) {', '.join(unverified)}: check them with list_domains first — an unverified domain is dropped at deploy."})
    replaced = list(domains) if replace else sorted(set(list(doc.get("domains") or []) + list(domains)))
    db.machine_labs.update_one({"_id": doc["_id"]}, {"$set": {"routing_saved": replaced, "domains": replaced}})
    db.domains.update_many({"domain": {"$in": list(doc.get("domains") or [])}}, {"$set": {"in_use": False}})
    db.domains.update_many({"domain": {"$in": replaced}, "user_id": user.get("user_id")}, {"$set": {"in_use": True}})
    applied = False
    if apply:
        res = await run_labsctl("lab", "apply-preferences", f"--hash={ih}", f"--user={user['username']}", timeout=120)
        applied = bool(res.get("success"))
    return json_response({
        "lab": lab,
        "instance_id": ih,
        "saved": {"domains": replaced},
        "applied": applied,
        "note": "Routing is saved. Pass apply=true to deploy in the same call, or call deploy_lab afterwards." if not applied else "Applied live.",
    })


async def lab_startup_script(lab: str, ctx: Context, instance_id: str = None, content: str = None) -> dict[str, Any]:
    """Read or replace the script that runs at the start of every deploy."""
    user = await get_current_user(ctx)
    db = get_db()
    doc, ih = _lab_doc(db, user, lab, instance_id)
    if not doc:
        return json_response({"error": f"Unknown lab '{lab}'."})
    if content is None:
        return json_response({
            "lab": lab,
            "instance_id": ih,
            "content": doc.get("startup_script") or "",
            "note": (
                "The script runs as ROOT inside the lab on every deploy. Saving it does not run it; deploy the lab to run it. "
                "A script with `<` followed by a letter is refused, so heredocs do not work; use printf, a quoted echo, or a download. "
                "Max 64 KB."
            ),
        })
    if not isinstance(content, str) or len(content) > 64 * 1024:
        return json_response({"error": "content: at most 64 KB of text."})
    if _re.search(r"<[A-Za-z]", content):
        return json_response({"error": "A script with `<` followed by a letter is refused: the platform would cut it. Use printf, a quoted echo, or a download instead of heredocs."})
    db.machine_labs.update_one({"_id": doc["_id"]}, {"$set": {"startup_script": content}})
    return json_response({
        "lab": lab,
        "instance_id": ih,
        "saved": True,
        "bytes": len(content.encode()),
        "note": "Saved. Call deploy_lab (or run_lab_startup_script) to run it now.",
    })


async def lab_error_page(lab: str, ctx: Context, instance_id: str = None, content: str = None) -> dict[str, Any]:
    """Read or replace the page visitors see when the lab is down."""
    user = await get_current_user(ctx)
    db = get_db()
    doc, ih = _lab_doc(db, user, lab, instance_id)
    if not doc:
        return json_response({"error": f"Unknown lab '{lab}'."})
    if content is None:
        return json_response({
            "lab": lab,
            "instance_id": ih,
            "content": doc.get("error_page") or "",
            "note": "Omit `content` to read the page, pass HTML to replace it, pass \"\" to reset it to the platform default. No extra routing change is needed.",
        })
    if not isinstance(content, str) or len(content) > 64 * 1024:
        return json_response({"error": "content: at most 64 KB."})
    db.machine_labs.update_one({"_id": doc["_id"]}, {"$set": {"error_page": content}})
    return json_response({
        "lab": lab,
        "instance_id": ih,
        "ok": True,
        "note": "Applied at once for the lab's routes on this install's edge; no redeploy is needed.",
    })


# ── Scale groups: not enabled on this install (honest refusal surface) ───

async def scale_lab(lab: str, group: str, ctx: Context, count: int = None, port: int = None,
                    domains: list = None, mode: str = "fixed", min_replicas: int = None,
                    max_replicas: int = None, cpu_target: float = None, mem_target: float = None,
                    healthcheck: str = None, sticky: bool = None, dry_run: bool = False,
                    request_id: str = None, member_instance_id: str = None) -> dict[str, Any]:
    """Replica scale groups are not enabled on this install: refusal."""
    return json_response({
        "success": False,
        "lab": lab, "group": group,
        "error": "Replica scale groups are not enabled on this install. Run copies yourself with deploy_lab (a redeploy per copy) or ask an admin.",
    })


async def scale_policy(group: str, ctx: Context, mode: str = None, count: int = None,
                       min_replicas: int = None, max_replicas: int = None, cpu_target: float = None,
                       mem_target: float = None, enabled: bool = None, cooldown_up_s: int = None,
                       stabilization_down_s: int = None, drain_grace_s: int = None) -> dict[str, Any]:
    """Replica scale groups are not enabled on this install: refusal."""
    return json_response({
        "success": False,
        "group": group,
        "error": "Replica scale groups are not enabled on this install.",
    })


# ── Tom Labs parity: login recon ──────────────────────────────────────────────

async def login_recon(lab: str, ctx: Context, port: int, path: str = None, scheme: str = "http",
                      verify: bool = False, timeout: int = 10, try_login: bool = False) -> dict[str, Any]:
    """Inspect a lab's login page and suggest an auto_login adapter."""
    user = await get_current_user(ctx)
    ownership = get_ownership()
    instance_hash = ownership.resolve_lab(user, lab_name=lab)
    if not path or path == "None":
        path = "/"
    p = path if path.startswith("/") else "/" + path
    url = f"{scheme}://127.0.0.1:{port}{p}"
    cmd = f"curl -s -i -m {timeout} {url!r}"
    res = await run_docker_exec(instance_hash, "root", cmd, timeout=timeout + 5)
    out = res.get("stdout", "")
    if not out.strip():
        return json_response({"error": "No response: the lab may be stopped or nothing listens on that port."})
    head, _sep, body = out.partition("\r\n\r\n")
    lines = head.split("\r\n")
    status = 0
    for ln in lines:
        if ln.upper().startswith("HTTP/"):
            try:
                status = int(ln.split()[1])
            except Exception:
                status = 0
    lower = body.lower()
    has_form = "<form" in lower
    has_passwd = 'type="password"' in lower or "type='password'" in lower or "type=password" in lower
    js_json = ("application/json" in lower or "api/login" in lower or "token" in lower) and "<form" not in lower
    if status == 401:
        adapter = {"mode": "basic", "path": p, "port": port}
        confidence = "high"
        notes = ["The page answers 401 without credentials, so HTTP basic auth is likely."]
    elif has_form and has_passwd:
        import html as _html
        fields = []
        for m in _re.finditer(r"<input[^>]*>", body, _re.I):
            tag = m.group(0)
            typ = (_re.search(r"type\s*=\s*['\"]?([^'\"]\S*)", tag, _re.I) or [None, "text"])[1]
            name = (_re.search(r"name\s*=\s*['\"]([^'\"]+)", tag, _re.I) or [None, None])[1]
            if name:
                fields.append({"name": name, "type": (typ or "text").lower()})
        hidden = [{"name": f["name"], "type": f["type"]} for f in fields if f["type"] == "hidden"]
        userpas = [f["name"] for f in fields if f["type"] == "password"]
        likely_user = next((f["name"] for f in fields if f["name"].lower() in ("email", "username", "user", "login")), None)
        csrf = next((h["name"] for h in hidden if "csrf" in h["name"].lower() or "token" in h["name"].lower() or "_auth" in h["name"].lower()), None)
        adapter = {"mode": "form", "path": p, "port": port,
                   "fields": {"username": likely_user, "password": userpas[0] if userpas else None, "csrf": csrf}}
        confidence = "medium" if csrf else "low"
        notes = ["Read-only reconnaissance: no login was tried.", "Hidden fields listed; paste nothing secret."]
    elif js_json:
        adapter = {"mode": "body_token", "port": port, "path": "/api/login"}
        confidence = "low"
        notes = ["Looks like a JSON or single-page-app login; only a real login attempt can confirm the endpoint shape."]
    else:
        adapter = None
        confidence = "low"
        notes = ["No login form found at this path."]
    return json_response({
        "port": port,
        "path": p,
        "status": status,
        "suggested_adapter": adapter,
        "evidence": {"form": has_form, "password_field": has_passwd, "json_hint": js_json},
        "confidence": confidence,
        "notes": notes,
        "next": "Paste suggested_adapter into the lab's auto-login setup and check it with test_auto_login.",
    })


# ── Tom Labs parity: rename a lab copy ────────────────────────────────────────

async def rename_lab(instance_id: str, name: str, ctx: Context) -> dict[str, Any]:
    """Change the display name of a lab COPY."""
    user = await get_current_user(ctx)
    db = get_db()
    ownership = get_ownership()
    if not instance_id or not _re.match(r"^[0-9a-f]{32}$", instance_id):
        return json_response({"error": "Pass the 32-hex instance id from list_labs."})
    if not _re.match(r"^[A-Za-z0-9 _.\-]{1,60}$", name or ""):
        return json_response({"error": "New display name: 1 to 60 characters."})
    doc = db.machine_labs.find_one({"instance_hash": instance_id})
    if not doc or str(doc.get("user_id")) not in [str(x) for x in ownership._user_identifiers(user)]:
        return json_response({"error": "Unknown instance_id or a lab that is not yours."})
    db.machine_labs.update_one(
        {"_id": doc["_id"]}, {"$set": {"display_name": name}})
    return json_response({
        "instance_id": instance_id,
        "name": name,
        "renamed": True,
        "note": "Copies only on the platform's lab list; on this install the display name is set at once and applies to dashboard/lab lists from the next listing.",
    })
