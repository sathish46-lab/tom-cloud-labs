"""
Shared tool dependencies — server-internal only.

Tool modules import from here instead of receiving 6 positional
registration arguments. None of these functions are MCP tools and none
may be exposed directly to a caller; they only return JSON-safe dicts.
"""

import asyncio
import json
import logging
from typing import Any, Dict

from src.mcp.auth import AuthError, get_auth
from src.mcp.db import get_config
from src.mcp.ownership import OwnershipError, get_ownership

logger = logging.getLogger(__name__)

__all__ = [
    "AuthError",
    "OwnershipError",
    "get_ownership",
    "get_current_user",
    "get_current_user_from_context",
    "validate_bearer_token",
    "json_response",
    "run_labsctl",
    "run_docker_exec",
    "run_docker_exec_detached",
    "run_docker_host",
]

# Server-side blocklist for every command that runs inside a lab container.
DANGEROUS_PATTERNS = [
    'rm -rf /', 'mkfs', 'dd if=', 'chmod 777',
    'nc -l', 'netcat -l',
    # pipe-to-shell and sensitive targets; compared against a normalized
    # command (whitespace collapsed around | > < ;) so spaced variants match too.
    '|bash', '|sh', '|zsh', 'curl|bash', 'wget|bash',
    '>/etc/passwd', '>/etc/shadow', '>/etc/sudoers', '>/boot/',
    '>/dev/sd', '>/dev/hd', '>/dev/nv',
    ':(){ :|:& };:', '{ :|:& };:',
]


def check_dangerous(command: str) -> None:
    """Raise ValueError if the command matches the server-side blocklist."""
    import re
    cmd_lower = command.lower()
    # collapse whitespace around separators so 'curl x | bash' == 'curl x|bash'
    normalized = re.sub(r"\s*([|><;])\s*", r"\1", cmd_lower)
    for pattern in DANGEROUS_PATTERNS:
        if pattern in cmd_lower or pattern in normalized:
            raise ValueError(f"Command blocked: dangerous pattern '{pattern}'")


async def validate_bearer_token(token: str) -> Dict[str, Any]:
    """Validate a raw Bearer token string and return the user identity dict."""
    auth = get_auth()
    return auth.validate_bearer_token(token)


async def get_current_user(ctx) -> Dict[str, Any]:
    """Resolve the calling user from the FastMCP Context (what tools call)."""
    return await get_current_user_from_context(ctx)


async def get_current_user_from_context(ctx) -> Dict[str, Any]:
    """Extract and validate the user from the FastMCP Context (request headers)."""
    # ctx is the FastMCP Context; its request_context.request is a Starlette Request
    rc = getattr(ctx, "request_context", None)
    if rc is None or rc.request is None:
        raise AuthError("Authentication required: no request context")
    request = rc.request
    auth_header = request.headers.get("authorization") or request.headers.get("Authorization")
    if not auth_header or not auth_header.startswith("Bearer "):
        raise AuthError("Authentication required: missing Bearer token")
    token = auth_header.split(" ", 1)[1]
    return await validate_bearer_token(token)


def json_response(data: Any) -> dict:
    """Return a JSON-safe dict payload (coerces non-serializable values like json.dumps(default=str))."""
    return json.loads(json.dumps(data, default=str))


async def run_labsctl(command: str, *args: str, timeout: int = 300) -> Dict[str, Any]:
    """Run labsctl command as subprocess."""
    cfg = get_config()
    cmd = [cfg.labctl_path, command, *args]
    logger.info(f"Running: {' '.join(cmd)}")

    try:
        proc = await asyncio.create_subprocess_exec(
            *cmd,
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE
        )
        stdout, stderr = await asyncio.wait_for(proc.communicate(), timeout=timeout)

        return {
            "exit_code": proc.returncode,
            "stdout": stdout.decode() if stdout else "",
            "stderr": stderr.decode() if stderr else "",
            "success": proc.returncode == 0
        }
    except asyncio.TimeoutError:
        return {"exit_code": -1, "stdout": "", "stderr": f"Command timed out after {timeout}s", "success": False}
    except Exception as e:
        return {"exit_code": -1, "stdout": "", "stderr": str(e), "success": False}


async def run_docker_exec(instance_hash: str, username: str, command: str, timeout: int = 60) -> Dict[str, Any]:
    """Run a command inside a lab container via docker exec."""
    cfg = get_config()
    check_dangerous(command)

    docker_cmd = [
        "docker", "exec",
        "-u", username,
        instance_hash,
        "bash", "-c", command
    ]

    logger.info(f"Running docker exec: {' '.join(docker_cmd[:4])} ... {command[:50]}")

    try:
        proc = await asyncio.create_subprocess_exec(
            *docker_cmd,
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE
        )
        stdout, stderr = await asyncio.wait_for(proc.communicate(), timeout=timeout)

        return {
            "exit_code": proc.returncode,
            "stdout": stdout.decode() if stdout else "",
            "stderr": stderr.decode() if stderr else "",
            "success": proc.returncode == 0
        }
    except asyncio.TimeoutError:
        return {"exit_code": -1, "stdout": "", "stderr": f"Command timed out after {timeout}s", "success": False}
    except Exception as e:
        return {"exit_code": -1, "stdout": "", "stderr": str(e), "success": False}


async def run_docker_exec_detached(instance_hash: str, username: str, command: str, timeout: int = 30) -> Dict[str, Any]:
    """Start a command inside a lab container WITHOUT waiting for it (docker exec -d).

    Returns as soon as the process is spawned — used for long-running lab
    processes. Same dangerous-pattern blocklist as run_docker_exec.
    """
    cfg = get_config()
    check_dangerous(command)

    docker_cmd = [
        "docker", "exec", "-d",
        "-u", username,
        instance_hash,
        "bash", "-c", command
    ]

    logger.info(f"Starting detached docker exec: {' '.join(docker_cmd[:5])} ... {command[:60]}")

    try:
        proc = await asyncio.create_subprocess_exec(
            *docker_cmd,
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE
        )
        stdout, stderr = await asyncio.wait_for(proc.communicate(), timeout=timeout)
        return {
            "exit_code": proc.returncode,
            "stdout": stdout.decode() if stdout else "",
            "stderr": stderr.decode() if stderr else "",
            "success": proc.returncode == 0
        }
    except asyncio.TimeoutError:
        return {"exit_code": -1, "stdout": "", "stderr": f"Start timed out after {timeout}s", "success": False}
    except Exception as e:
        return {"exit_code": -1, "stdout": "", "stderr": str(e), "success": False}


async def run_docker_host(command: str, timeout: int = 60) -> Dict[str, Any]:
    """Run a docker command on the HOST (not inside container).

    Used for docker pause/unpause which must run on the host.
    The MCP server runs inside the container, so we use docker to execute on host.
    """
    cfg = get_config()

    # Security: only allow pause/unpause/inspect commands
    allowed_commands = ['docker pause ', 'docker unpause ', 'docker inspect ']
    if not any(command.startswith(c) for c in allowed_commands):
        return {"exit_code": -1, "stdout": "", "stderr": f"Command not allowed: {command}", "success": False}

    # Run via docker exec on the host container (Dev_lab)
    docker_cmd = [
        "docker", "exec",
        cfg.orchestrator_container,  # e.g., "Dev_lab"
        "bash", "-c", command
    ]

    logger.info(f"Running docker host command: {command[:80]}")

    try:
        proc = await asyncio.create_subprocess_exec(
            *docker_cmd,
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE
        )
        stdout, stderr = await asyncio.wait_for(proc.communicate(), timeout=timeout)

        return {
            "exit_code": proc.returncode,
            "stdout": stdout.decode() if stdout else "",
            "stderr": stderr.decode() if stderr else "",
            "success": proc.returncode == 0
        }
    except asyncio.TimeoutError:
        return {"exit_code": -1, "stdout": "", "stderr": f"Command timed out after {timeout}s", "success": False}
    except Exception as e:
        return {"exit_code": -1, "stdout": "", "stderr": str(e), "success": False}
