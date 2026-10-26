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
