"""
MCP Process Management Tools
List processes, stop processes, wait for processes, stats, storage, probe.
"""
from mcp.server.fastmcp import Context
from mcp.types import Tool, TextContent

import asyncio
import re
import shlex
from typing import Any, Dict

from src.mcp.db import get_db
from src.mcp.deps import (
    AuthError,
    OwnershipError,
    get_ownership,
    get_current_user,
    json_response,
    run_docker_exec,
    run_docker_exec_detached,
    run_docker_host,
    run_labsctl,
)


# Registry: src/mcp/registry.py (entry "list_lab_processes") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def list_lab_processes(lab: str, ctx: Context) -> dict[str, Any]:
    """List running processes in the lab."""
    user = await get_current_user(ctx)
    ownership = get_ownership()
    instance_hash = ownership.resolve_lab(user, lab_name=lab)

    result = await run_docker_exec(
        instance_hash,
        user["username"],
        "ps aux --no-headers",
        timeout=10
    )

    processes = []
    for line in result["stdout"].strip().split('\n'):
        if line.strip():
            parts = line.split(None, 10)
            if len(parts) >= 11:
                processes.append({
                    "user": parts[0],
                    "pid": int(parts[1]) if parts[1].isdigit() else 0,
                    "cpu": float(parts[2]) if parts[2].replace('.', '').isdigit() else 0,
                    "mem": float(parts[3]) if parts[3].replace('.', '').isdigit() else 0,
                    "vsz": parts[4],
                    "rss": parts[5],
                    "tty": parts[6],
                    "stat": parts[7],
                    "start": parts[8],
                    "time": parts[9],
                    "command": parts[10]
                })

    return json_response({
        "instance_hash": instance_hash,
        "processes": processes,
        "count": len(processes)
    })

# Registry: src/mcp/registry.py (entry "stop_lab_process") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def stop_lab_process(lab: str, pid: int, ctx: Context, signal: str = "TERM") -> dict[str, Any]:
    """Stop a process in the lab."""
    user = await get_current_user(ctx)
    ownership = get_ownership()
    instance_hash = ownership.resolve_lab(user, lab_name=lab)

    result = await run_docker_exec(
        instance_hash,
        user["username"],
        f"kill -{signal} {pid}",
        timeout=10
    )

    return json_response({
        "instance_hash": instance_hash,
        "pid": pid,
        "signal": signal,
        "stopped": result["success"],
        "result": result
    })

# Registry: src/mcp/registry.py (entry "wait_for_process") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def wait_for_process(lab: str, pid: int, ctx: Context, timeout: int = 60) -> dict[str, Any]:
    """Wait for a process to complete."""
    user = await get_current_user(ctx)
    import asyncio
    ownership = get_ownership()
    instance_hash = ownership.resolve_lab(user, lab_name=lab)

    start_time = asyncio.get_event_loop().time()
    while True:
        if asyncio.get_event_loop().time() - start_time > timeout:
            return json_response({"error": "Timeout waiting for process", "pid": pid})

        result = await run_docker_exec(
            instance_hash,
            user["username"],
            f"ps -p {pid} -o pid= 2>/dev/null || echo 'NOT_FOUND'",
            timeout=5
        )

        if "NOT_FOUND" in result["stdout"] or not result["stdout"].strip():
            return json_response({"instance_hash": instance_hash, "pid": pid, "completed": True})

        await asyncio.sleep(2)

# Registry: src/mcp/registry.py (entry "lab_stats") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def lab_stats(lab: str, ctx: Context) -> dict[str, Any]:
    """Get resource usage stats (CPU, memory, disk)."""
    user = await get_current_user(ctx)
    ownership = get_ownership()
    instance_hash = ownership.resolve_lab(user, lab_name=lab)

    result = await run_docker_exec(
        instance_hash,
        "root",
        "docker stats --no-stream --format 'table {{.CPUPerc}}\t{{.MemUsage}}\t{{.MemPerc}}\t{{.NetIO}}\t{{.BlockIO}}' " + instance_hash,
        timeout=15
    )

    # Parse docker stats output
    stats = {}
    for line in result["stdout"].strip().split('\n')[1:]:  # Skip header
        if line.strip():
            parts = line.split('\t')
            if len(parts) >= 5:
                stats = {
                    "cpu_percent": parts[0].strip(),
                    "mem_usage": parts[1].strip(),
                    "mem_percent": parts[2].strip(),
                    "net_io": parts[3].strip(),
                    "block_io": parts[4].strip()
                }

    # Also get disk usage
    disk_result = await run_docker_exec(
        instance_hash,
        "root",
        f"df -h /var/labsstorage/home/{user['username']} 2>/dev/null | tail -1",
        timeout=10
    )

    return json_response({
        "instance_hash": instance_hash,
        "container_stats": stats,
        "disk_usage": disk_result["stdout"].strip() if disk_result["success"] else "N/A"
    })

# Registry: src/mcp/registry.py (entry "storage_usage") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def storage_usage(lab: str, ctx: Context) -> dict[str, Any]:
    """Get detailed storage usage breakdown."""
    user = await get_current_user(ctx)
    ownership = get_ownership()
    instance_hash = ownership.resolve_lab(user, lab_name=lab)

    user_home = f"/var/labsstorage/home/{user['username']}"

    # Get directory sizes
    result = await run_docker_exec(
        instance_hash,
        "root",
        f"du -h --max-depth=2 {user_home} 2>/dev/null | sort -hr | head -30",
        timeout=30
    )

    # Get total usage
    total_result = await run_docker_exec(
        instance_hash,
        "root",
        f"du -sh {user_home} 2>/dev/null",
        timeout=15
    )

    return json_response({
        "instance_hash": instance_hash,
        "user_home": user_home,
        "total_usage": total_result["stdout"].strip() if total_result["success"] else "N/A",
        "breakdown": result["stdout"].strip()
    })

# Registry: src/mcp/registry.py (entry "probe_lab") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def probe_lab(lab: str, ctx: Context) -> dict[str, Any]:
    """Probe lab connectivity and services."""
    user = await get_current_user(ctx)
    ownership = get_ownership()
    instance_hash = ownership.resolve_lab(user, lab_name=lab)

    from src.mcp.db import get_db
    db = get_db()

    doc = db.machine_labs.find_one(
        {"instance_hash": instance_hash},
        {"credentials": 1, "status": 1, "lab_type": 1}
    )

    if not doc:
        return json_response({"error": "Lab not found"})

    results = {
        "instance_hash": instance_hash,
        "status": doc.get("status"),
        "lab_type": doc.get("lab_type"),
        "checks": {}
    }

    creds = doc.get("credentials", {})
    tunnel_ip = creds.get("tunnel_ip")
    docker_ip = creds.get("docker_ip")

    # Test container running
    import subprocess
    container_check = await run_docker_exec(
        instance_hash,
        "root",
        "echo 'container_ok'",
        timeout=5
    )
    results["checks"]["container"] = {
        "running": container_check["success"],
        "output": container_check["stdout"].strip()
    }

    # Test SSH if tunnel_ip available
    if tunnel_ip:
        ssh_test = await run_docker_exec(
            instance_hash,
            user["username"],
            f"ssh -o ConnectTimeout=3 -o BatchMode=yes -o StrictHostKeyChecking=no {user['username']}@{tunnel_ip} 'echo ssh_ok' 2>&1",
            timeout=10
        )
        results["checks"]["ssh"] = {
            "reachable": ssh_test["success"],
            "output": ssh_test["stdout"].strip()[:200]
        }

    # Test code-server if running
    code_check = await run_docker_exec(
        instance_hash,
        user["username"],
        "pgrep -f code-server >/dev/null && echo 'running' || echo 'not_running'",
        timeout=5
    )
    results["checks"]["code_server"] = {
        "running": "running" in code_check["stdout"]
    }

    return json_response(results)

# Registry: src/mcp/registry.py (entry "run_command") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def run_command(lab: str, command: str, ctx: Context, timeout_s: int = 60) -> dict[str, Any]:
    """Run a shell command inside your lab (runs as your user, cwd = your home).

    Args:
        command: the shell command to execute.
        timeout_s: kill after this many seconds (1-300, default 60).
    """
    user = await get_current_user(ctx)
    ownership = get_ownership()
    instance_hash = ownership.resolve_lab(user, lab_name=lab)

    if not command or not command.strip():
        return json_response({"success": False, "error": "command must not be empty"})

    timeout_s = max(1, min(int(timeout_s or 60), 300))
    home = f"/var/labsstorage/home/{user['username']}"
    wrapped = f"cd {home} 2>/dev/null && {command}"

    try:
        result = await run_docker_exec(instance_hash, user["username"], wrapped, timeout=timeout_s)
    except ValueError as e:
        # server-side blocklist refusal
        return json_response({"success": False, "error": str(e)})

    stdout = result.get("stdout") or ""
    stderr = result.get("stderr") or ""
    truncated = False
    if len(stdout) > 60_000:
        stdout = stdout[:60_000] + "\n...[truncated]"
        truncated = True
    if len(stderr) > 20_000:
        stderr = stderr[:20_000] + "\n...[truncated]"
        truncated = True

    return json_response({
        "command": command,
        "exit_code": result.get("exit_code"),
        "stdout": stdout,
        "stderr": stderr,
        "success": result.get("success", False),
        "truncated": truncated,
    })

# Registry: src/mcp/registry.py (entry "start_lab_process") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def start_lab_process(lab: str, name: str, command: str, ctx: Context) -> dict[str, Any]:
    """Start a long-running background process in your lab (survives this call).

    Args:
        name: process id, letters/digits/dot/dash/underscore, max 64 chars.
        command: shell command to run in the background (cwd = your home).
    """
    user = await get_current_user(ctx)
    ownership = get_ownership()
    instance_hash = ownership.resolve_lab(user, lab_name=lab)

    name = (name or "").strip()
    if not re.fullmatch(r"[A-Za-z0-9._-]{1,64}", name) or name in (".", ".."):
        return json_response({
            "success": False,
            "error": "name must match [A-Za-z0-9._-]{1,64} (no slashes)",
        })
    if not command or not command.strip():
        return json_response({"success": False, "error": "command must not be empty"})
    if len(command) > 20_000:
        return json_response({"success": False, "error": "command too long"})

    home = f"/var/labsstorage/home/{user['username']}"
    pdir = f"{home}/.labs-processes"
    pidfile = f"{pdir}/{name}.pid"
    logfile = f"{pdir}/{name}.log"

    # Refuse if a live process with this name already exists.
    probe = await run_docker_exec(
        instance_hash, user["username"],
        f"test -f {pidfile} && kill -0 $(cat {pidfile}) 2>/dev/null && echo ALIVE || echo FREE",
        timeout=10,
    )
    if "ALIVE" in (probe.get("stdout") or ""):
        return json_response({
            "success": False,
            "error": f"process {name!r} is already running (stop it first)",
            "name": name,
        })

    script = (
        f"mkdir -p {pdir} && rm -f {pidfile} && "
        f"nohup bash -c {shlex.quote(command)} > {logfile} 2>&1 < /dev/null & "
        f"echo $! > {pidfile}"
    )
    start = await run_docker_exec_detached(instance_hash, user["username"], script, timeout=30)
    if not start.get("success"):
        return json_response({
            "success": False,
            "error": start.get("stderr") or "failed to start process",
        })

    # docker exec -d returns immediately — poll briefly for the pidfile.
    pid = None
    for _ in range(10):
        await asyncio.sleep(0.2)
        check = await run_docker_exec(
            instance_hash, user["username"],
            f"cat {pidfile} 2>/dev/null || echo MISSING",
            timeout=10,
        )
        out = (check.get("stdout") or "").strip()
        if out and out != "MISSING":
            try:
                pid = int(out.splitlines()[-1])
                break
            except ValueError:
                continue

    if pid is None:
        tail = await run_docker_exec(
            instance_hash, user["username"],
            f"tail -n 20 {logfile} 2>/dev/null || true",
            timeout=10,
        )
        return json_response({
            "success": False,
            "error": "process started but did not report a pid (see log tail)",
            "name": name,
            "log_tail": tail.get("stdout", ""),
        })

    return json_response({
        "success": True,
        "name": name,
        "pid": pid,
        "command": command,
        "log": logfile,
        "hint": "read output with lab_process_logs(lab=..., name=...)",
    })

# Registry: src/mcp/registry.py (entry "lab_process_logs") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def lab_process_logs(lab: str, ctx: Context, name: str = "", lines: int = 200) -> dict[str, Any]:
    """Show background-process log output, or list all started processes.

    Args:
        name: process name (from start_lab_process); omit to list all.
        lines: tail size for the log (1-2000, default 200).
    """
    user = await get_current_user(ctx)
    ownership = get_ownership()
    instance_hash = ownership.resolve_lab(user, lab_name=lab)

    home = f"/var/labsstorage/home/{user['username']}"
    pdir = f"{home}/.labs-processes"

    name = (name or "").strip()
    if not name:
        listing = await run_docker_exec(
            instance_hash, user["username"],
            f"if [ -d {pdir} ]; then for p in {pdir}/*.pid; do "
            "[ -e \"$p\" ] || continue; n=$(basename \"$p\" .pid); "
            "pid=$(cat \"$p\"); if kill -0 \"$pid\" 2>/dev/null; then s=running; else s=stopped; fi; "
            "echo \"$n|$pid|$s\"; done; else echo NO_DIR; fi",
            timeout=10,
        )
        procs = []
        for line in (listing.get("stdout") or "").splitlines():
            parts = line.strip().split("|")
            if len(parts) == 3 and parts[0] != "NO_DIR":
                procs.append({"name": parts[0], "pid": parts[1], "status": parts[2]})
        return json_response({"processes": procs, "count": len(procs)})

    if not re.fullmatch(r"[A-Za-z0-9._-]{1,64}", name) or name in (".", ".."):
        return json_response({"success": False, "error": "invalid process name"})

    pidfile = f"{pdir}/{name}.pid"
    logfile = f"{pdir}/{name}.log"
    lines = max(1, min(int(lines or 200), 2000))

    status = await run_docker_exec(
        instance_hash, user["username"],
        f"if [ -f {pidfile} ] || [ -f {logfile} ]; then echo EXISTS; "
        f"else echo MISSING; fi; "
        f"pid=$(cat {pidfile} 2>/dev/null); "
        "if [ -n \"$pid\" ] && kill -0 \"$pid\" 2>/dev/null; then echo \"PID $pid\"; echo RUNNING; "
        "elif [ -n \"$pid\" ]; then echo \"PID $pid\"; echo STOPPED; else echo NO_PID; fi",
        timeout=10,
    )
    out = status.get("stdout") or ""
    if "MISSING" in out.splitlines()[0:1] and "EXISTS" not in out:
        return json_response({
            "success": False,
            "error": f"no process named {name!r}",
            "hint": "omit name to list started processes",
        })

    log = await run_docker_exec(
        instance_hash, user["username"],
        f"tail -n {lines} {logfile} 2>/dev/null || echo 'NO_LOG'",
        timeout=10,
    )

    pid = None
    for line in out.splitlines():
        if line.startswith("PID "):
            pid = int(line.split()[1])
    running = "RUNNING" in out

    return json_response({
        "name": name,
        "exists": True,
        "pid": pid,
        "running": running,
        "log": log.get("stdout") or "",
        "lines": lines,
    })
