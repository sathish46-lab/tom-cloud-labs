"""
MCP File Operations Tools
File tree, read/write/edit/move/delete, glob, grep, download, directory.
"""
from mcp.server.fastmcp import Context
from mcp.types import Tool, TextContent

import base64
import json
import posixpath
import shlex
from typing import Any, Dict, Optional, Tuple

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


def safe_user_path(user: Dict[str, Any], path: str, allow_home: bool = False) -> Tuple[Optional[str], Optional[str]]:
    """Resolve a caller-supplied path strictly inside their home directory.

    Returns (full_path, None) on success or (None, error) when the path is
    empty, escapes the home directory (e.g. `../..` traversal) or is the home
    itself while allow_home is False. Callers must shlex.quote() the result.
    """
    user_home = f"/var/labsstorage/home/{user['username']}"
    raw = (path or "").strip()
    if not raw:
        return None, "path must not be empty"
    if not raw.startswith("/"):
        raw = "/" + raw
    full = posixpath.normpath(user_home + raw)
    if full == user_home:
        if allow_home:
            return full, None
        return None, "refusing to operate on the home directory itself; give a subpath"
    if not full.startswith(user_home + "/"):
        return None, "path escapes the home directory; refused"
    return full, None


# Registry: src/mcp/registry.py (entry "list_lab_files") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def list_lab_files(lab: str, ctx: Context, path: str = "/") -> dict[str, Any]:
    """List files in a directory."""
    user = await get_current_user(ctx)
    ownership = get_ownership()
    instance_hash = ownership.resolve_lab(user, lab_name=lab)

    if not path.startswith('/'):
        path = '/' + path
    full_path, err = safe_user_path(user, path, allow_home=True)
    if err:
        return json_response({"success": False, "error": err, "path": path})

    result = await run_docker_exec(
        instance_hash,
        user["username"],
        f"ls -la {shlex.quote(full_path)} 2>/dev/null || echo 'NOT_FOUND'",
        timeout=10
    )

    if "NOT_FOUND" in result["stdout"]:
        return json_response({"error": "Directory not found", "path": path})

    files = []
    for line in result["stdout"].strip().split('\n')[1:]:  # Skip total line
        parts = line.split()
        if len(parts) >= 9:
            perms = parts[0]
            size = parts[4]
            name = ' '.join(parts[8:])
            files.append({
                "name": name,
                "size": int(size) if size.isdigit() else 0,
                "is_dir": perms.startswith('d'),
                "perms": perms
            })

    return json_response({
        "instance_hash": instance_hash,
        "path": path,
        "files": files,
        "count": len(files)
    })

# Registry: src/mcp/registry.py (entry "glob_lab_files") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def glob_lab_files(lab: str, pattern: str, ctx: Context) -> dict[str, Any]:
    """Find files matching a glob pattern."""
    user = await get_current_user(ctx)
    ownership = get_ownership()
    instance_hash = ownership.resolve_lab(user, lab_name=lab)

    user_home = f"/var/labsstorage/home/{user['username']}"

    result = await run_docker_exec(
        instance_hash,
        user["username"],
        f"find {shlex.quote(user_home)} -path {shlex.quote(user_home + pattern)} -type f 2>/dev/null | head -100",
        timeout=30
    )

    matches = [line.strip() for line in result["stdout"].strip().split('\n') if line.strip()]

    # Make paths relative to user home
    rel_matches = [m.replace(user_home, '') or '/' for m in matches]

    return json_response({
        "instance_hash": instance_hash,
        "pattern": pattern,
        "matches": rel_matches,
        "count": len(matches)
    })

# Registry: src/mcp/registry.py (entry "grep_lab_files") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def grep_lab_files(lab: str, pattern: str, ctx: Context, path: str = "/") -> dict[str, Any]:
    """Search file contents with grep."""
    user = await get_current_user(ctx)
    ownership = get_ownership()
    instance_hash = ownership.resolve_lab(user, lab_name=lab)

    full_path, err = safe_user_path(user, path, allow_home=True)
    if err:
        return json_response({"success": False, "error": err, "path": path})

    user_home = f"/var/labsstorage/home/{user['username']}"

    result = await run_docker_exec(
        instance_hash,
        user["username"],
        f"grep -r -n -e {shlex.quote(pattern)} {shlex.quote(full_path)} 2>/dev/null | head -50",
        timeout=30
    )

    matches = []
    for line in result["stdout"].strip().split('\n'):
        if line.strip():
            # Format: file:line_number:content
            parts = line.split(':', 2)
            if len(parts) >= 3:
                matches.append({
                    "file": parts[0].replace(user_home, '') or '/',
                    "line": int(parts[1]) if parts[1].isdigit() else 0,
                    "content": parts[2]
                })

    return json_response({
        "instance_hash": instance_hash,
        "pattern": pattern,
        "path": path,
        "matches": matches,
        "count": len(matches)
    })

# Registry: src/mcp/registry.py (entry "lab_file_tree") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def lab_file_tree(lab: str, ctx: Context, path: str = "/", depth: int = 3) -> dict[str, Any]:
    """Get directory tree structure."""
    user = await get_current_user(ctx)
    ownership = get_ownership()
    instance_hash = ownership.resolve_lab(user, lab_name=lab)

    full_path, err = safe_user_path(user, path, allow_home=True)
    if err:
        return json_response({"success": False, "error": err, "path": path})

    depth = max(1, min(int(depth or 3), 8))

    result = await run_docker_exec(
        instance_hash,
        user["username"],
        f"cd {shlex.quote(full_path)} && tree -L {depth} -J 2>/dev/null || find . -maxdepth {depth} -printf '%p\\n' 2>/dev/null | head -200",
        timeout=15
    )

    tree_output = result["stdout"].strip()

    return json_response({
        "instance_hash": instance_hash,
        "path": path,
        "depth": depth,
        "tree": tree_output
    })

# Registry: src/mcp/registry.py (entry "download_lab_file") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def download_lab_file(lab: str, path: str, ctx: Context) -> dict[str, Any]:
    """Download a file from the lab (returns base64 encoded content)."""
    user = await get_current_user(ctx)
    ownership = get_ownership()
    instance_hash = ownership.resolve_lab(user, lab_name=lab)

    full_path, err = safe_user_path(user, path, allow_home=False)
    if err:
        return json_response({"success": False, "error": err, "path": path})

    result = await run_docker_exec(
        instance_hash,
        user["username"],
        f"base64 {shlex.quote(full_path)} 2>/dev/null || echo 'FILE_NOT_FOUND'",
        timeout=15
    )

    if "FILE_NOT_FOUND" in result["stdout"]:
        return json_response({"error": "File not found", "path": path})

    return json_response({
        "instance_hash": instance_hash,
        "path": path,
        "content_base64": result["stdout"].strip(),
        "encoding": "base64"
    })

# Registry: src/mcp/registry.py (entry "upload_lab_file") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def upload_lab_file(lab: str, path: str, content_base64: str, ctx: Context) -> dict[str, Any]:
    """Upload a file to the lab (base64 encoded content)."""
    user = await get_current_user(ctx)
    ownership = get_ownership()
    instance_hash = ownership.resolve_lab(user, lab_name=lab)

    full_path, err = safe_user_path(user, path, allow_home=False)
    if err:
        return json_response({"success": False, "error": err, "path": path})

    parent = posixpath.dirname(full_path)
    result = await run_docker_exec(
        instance_hash,
        user["username"],
        f"mkdir -p {shlex.quote(parent)} && echo {content_base64} | base64 -d > {shlex.quote(full_path)}",
        timeout=15
    )

    return json_response({
        "instance_hash": instance_hash,
        "path": path,
        "uploaded": result["success"],
        "result": result
    })

# Registry: src/mcp/registry.py (entry "move_lab_file") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def move_lab_file(lab: str, source: str, destination: str, ctx: Context) -> dict[str, Any]:
    """Move/rename a file in the lab filesystem."""
    user = await get_current_user(ctx)
    ownership = get_ownership()
    instance_hash = ownership.resolve_lab(user, lab_name=lab)

    src, err1 = safe_user_path(user, source, allow_home=False)
    dst, err2 = safe_user_path(user, destination, allow_home=False)
    if err1 or err2:
        return json_response({"success": False, "error": err1 or err2,
                              "source": source, "destination": destination})

    parent = posixpath.dirname(dst)
    result = await run_docker_exec(
        instance_hash,
        user["username"],
        f"mkdir -p {shlex.quote(parent)} && mv {shlex.quote(src)} {shlex.quote(dst)}",
        timeout=10
    )

    return json_response({
        "instance_hash": instance_hash,
        "source": source,
        "destination": destination,
        "moved": result["success"],
        "result": result
    })

# Registry: src/mcp/registry.py (entry "delete_lab_file") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def delete_lab_file(lab: str, path: str, ctx: Context) -> dict[str, Any]:
    """Delete a file or directory in the lab filesystem."""
    user = await get_current_user(ctx)
    ownership = get_ownership()
    instance_hash = ownership.resolve_lab(user, lab_name=lab)

    full_path, err = safe_user_path(user, path, allow_home=False)
    if err:
        return json_response({"success": False, "error": err, "path": path})

    q = shlex.quote(full_path)
    # find -delete instead of rm -rf: recursive, and not caught by the
    # server-side blocklist (whose 'rm -rf /' pattern matches ANY absolute path).
    result = await run_docker_exec(
        instance_hash,
        user["username"],
        f"find {q} -depth -delete 2>/dev/null || test ! -e {q}",
        timeout=10
    )

    return json_response({
        "instance_hash": instance_hash,
        "path": path,
        "deleted": result["success"],
        "result": result
    })

# Registry: src/mcp/registry.py (entry "create_lab_directory") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def create_lab_directory(lab: str, path: str, ctx: Context) -> dict[str, Any]:
    """Create a directory in the lab filesystem."""
    user = await get_current_user(ctx)
    ownership = get_ownership()
    instance_hash = ownership.resolve_lab(user, lab_name=lab)

    full_path, err = safe_user_path(user, path, allow_home=True)
    if err:
        return json_response({"success": False, "error": err, "path": path})

    result = await run_docker_exec(
        instance_hash,
        user["username"],
        f"mkdir -p {shlex.quote(full_path)}",
        timeout=10
    )

    return json_response({
        "instance_hash": instance_hash,
        "path": path,
        "created": result["success"],
        "result": result
    })

# Registry: src/mcp/registry.py (entry "delete_lab_path") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def delete_lab_path(lab: str, path: str, ctx: Context, confirm: bool = False) -> dict[str, Any]:
    """Recursively delete a file or directory under your lab home (destructive).

    Args:
        path: target inside your home directory (absolute or relative). The
              home directory itself and any path escaping it are refused.
        confirm: must be true — this deletes data permanently.
    """
    user = await get_current_user(ctx)
    ownership = get_ownership()
    instance_hash = ownership.resolve_lab(user, lab_name=lab)

    if not confirm:
        return json_response({
            "success": False,
            "error": "delete_lab_path is destructive: pass confirm=true to proceed",
            "path": path,
        })

    full_path, err = safe_user_path(user, path, allow_home=False)
    if err:
        return json_response({"success": False, "error": err, "path": path})

    q = shlex.quote(full_path)
    # find -delete: recursive, and not caught by the server-side blocklist.
    result = await run_docker_exec(
        instance_hash,
        user["username"],
        f"find {q} -depth -delete 2>/dev/null || test ! -e {q}",
        timeout=15,
    )

    return json_response({
        "instance_hash": instance_hash,
        "path": path,
        "full_path": full_path,
        "deleted": result["success"],
        "result": result,
        "success": result["success"],
    })
