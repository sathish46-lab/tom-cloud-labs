"""
MCP Workspace Tools
Code-server workspace management, preferences, startup scripts.
"""
from mcp.server.fastmcp import Context
from mcp.types import Tool, TextContent

from typing import Any, Dict

import posixpath
import shlex

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


# Registry: src/mcp/registry.py (entry "workspace_pin") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def workspace_pin(lab: str, tab_id: str, ctx: Context) -> dict[str, Any]:
    """Pin a workspace tab in code-server."""
    user = await get_current_user(ctx)
    ownership = get_ownership()
    instance_hash = ownership.resolve_lab(user, lab_name=lab)

    # code-server stores workspace state in ~/.local/share/code-server/User/workspaceStorage
    # This is a simplified implementation - in reality would need to modify workspace JSON
    user_home = f"/var/labsstorage/home/{user['username']}"
    workspace_file = f"{user_home}/.local/share/code-server/User/workspaceStorage/workspace.json"

    result = await run_docker_exec(
        instance_hash,
        user["username"],
        f"mkdir -p $(dirname {workspace_file}) && "
        f"python3 -c \"import json, os; "
        f"data = json.load(open('{workspace_file}')) if os.path.exists('{workspace_file}') else {{}}; "
        f"data.setdefault('pinnedTabs', []); "
        f"data['pinnedTabs'].append('{tab_id}') if '{tab_id}' not in data['pinnedTabs'] else None; "
        f"json.dump(data, open('{workspace_file}', 'w'))\"",
        timeout=30
    )

    return json_response({"instance_hash": instance_hash, "pinned": tab_id, "result": result})

# Registry: src/mcp/registry.py (entry "workspace_unpin") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def workspace_unpin(lab: str, tab_id: str, ctx: Context) -> dict[str, Any]:
    """Unpin a workspace tab in code-server."""
    user = await get_current_user(ctx)
    ownership = get_ownership()
    instance_hash = ownership.resolve_lab(user, lab_name=lab)

    user_home = f"/var/labsstorage/home/{user['username']}"
    workspace_file = f"{user_home}/.local/share/code-server/User/workspaceStorage/workspace.json"

    result = await run_docker_exec(
        instance_hash,
        user["username"],
        f"python3 -c \"import json, os; "
        f"data = json.load(open('{workspace_file}')) if os.path.exists('{workspace_file}') else {{}}; "
        f"data.setdefault('pinnedTabs', []); "
        f"data['pinnedTabs'] = [t for t in data['pinnedTabs'] if t != '{tab_id}']; "
        f"json.dump(data, open('{workspace_file}', 'w'))\"",
        timeout=30
    )

    return json_response({"instance_hash": instance_hash, "unpinned": tab_id, "result": result})

# Registry: src/mcp/registry.py (entry "workspace_preferences") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def workspace_preferences(lab: str, ctx: Context) -> dict[str, Any]:
    """Get code-server preferences."""
    user = await get_current_user(ctx)
    ownership = get_ownership()
    instance_hash = ownership.resolve_lab(user, lab_name=lab)

    user_home = f"/var/labsstorage/home/{user['username']}"
    prefs_file = f"{user_home}/.local/share/code-server/User/settings.json"

    result = await run_docker_exec(
        instance_hash,
        user["username"],
        f"cat {prefs_file} 2>/dev/null || echo '{{}}'",
        timeout=10
    )

    if result["success"]:
        try:
            prefs = json.loads(result["stdout"])
            return json_response({"instance_hash": instance_hash, "preferences": prefs})
        except:
            pass

    return json_response({"instance_hash": instance_hash, "preferences": {}, "raw": result["stdout"]})

# Registry: src/mcp/registry.py (entry "apply_workspace_preferences") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def apply_workspace_preferences(lab: str, preferences: Dict[str, Any], ctx: Context) -> dict[str, Any]:
    """Apply code-server preferences."""
    user = await get_current_user(ctx)
    ownership = get_ownership()
    instance_hash = ownership.resolve_lab(user, lab_name=lab)

    user_home = f"/var/labsstorage/home/{user['username']}"
    prefs_file = f"{user_home}/.local/share/code-server/User/settings.json"

    import json
    prefs_json = json.dumps(preferences)

    result = await run_docker_exec(
        instance_hash,
        user["username"],
        f"mkdir -p $(dirname {prefs_file}) && echo '{prefs_json}' > {prefs_file}",
        timeout=10
    )

    # Restart code-server to apply
    if result["success"]:
        await run_docker_exec(
            instance_hash,
            user["username"],
            f"pkill -f code-server || true",
            timeout=10
        )

    return json_response({"instance_hash": instance_hash, "applied": True, "result": result})

# Registry: src/mcp/registry.py (entry "read_lab_file") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def read_lab_file(lab: str, path: str, ctx: Context) -> dict[str, Any]:
    """Read a file from the lab filesystem."""
    user = await get_current_user(ctx)
    ownership = get_ownership()
    instance_hash = ownership.resolve_lab(user, lab_name=lab)

    # Normalize path
    if not path.startswith('/'):
        path = '/' + path
    user_home = f"/var/labsstorage/home/{user['username']}"
    full_path = user_home + path

    result = await run_docker_exec(
        instance_hash,
        user["username"],
        f"cat {full_path} 2>/dev/null || echo 'FILE_NOT_FOUND'",
        timeout=10
    )

    if result["stdout"].strip() == "FILE_NOT_FOUND":
        return json_response({"error": "File not found", "path": path})

    return json_response({
        "instance_hash": instance_hash,
        "path": path,
        "content": result["stdout"],
        "size": len(result["stdout"])
    })

# Registry: src/mcp/registry.py (entry "write_lab_file") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def write_lab_file(lab: str, path: str, content: str, ctx: Context) -> dict[str, Any]:
    """Write a file to the lab filesystem."""
    user = await get_current_user(ctx)
    ownership = get_ownership()
    instance_hash = ownership.resolve_lab(user, lab_name=lab)

    if not path.startswith('/'):
        path = '/' + path
    user_home = f"/var/labsstorage/home/{user['username']}"
    full_path = user_home + path

    # Write via base64 to handle special characters
    import base64
    encoded = base64.b64encode(content.encode()).decode()

    result = await run_docker_exec(
        instance_hash,
        user["username"],
        f"mkdir -p $(dirname {full_path}) && echo {encoded} | base64 -d > {full_path}",
        timeout=10
    )

    return json_response({
        "instance_hash": instance_hash,
        "path": path,
        "written": result["success"],
        "size": len(content),
        "result": result
    })

# Registry: src/mcp/registry.py (entry "edit_lab_file") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def edit_lab_file(lab: str, path: str, old_text: str, new_text: str, ctx: Context) -> dict[str, Any]:
    """Edit a file in the lab filesystem (replace old_text with new_text)."""
    user = await get_current_user(ctx)
    ownership = get_ownership()
    instance_hash = ownership.resolve_lab(user, lab_name=lab)

    if not path.startswith('/'):
        path = '/' + path
    user_home = f"/var/labsstorage/home/{user['username']}"
    full_path = user_home + path

    import base64
    old_encoded = base64.b64encode(old_text.encode()).decode()
    new_encoded = base64.b64encode(new_text.encode()).decode()

    result = await run_docker_exec(
        instance_hash,
        user["username"],
        f"python3 -c \"import base64; "
        f"content = open('{full_path}').read() if __import__('os').path.exists('{full_path}') else ''; "
        f"old = base64.b64decode('{old_encoded}').decode(); "
        f"new = base64.b64decode('{new_encoded}').decode(); "
        f"if old in content: "
        f"  content = content.replace(old, new, 1); "
        f"  open('{full_path}', 'w').write(content); "
        f"  print('REPLACED') "
        f"else: "
        f"  print('OLD_TEXT_NOT_FOUND')\"",
        timeout=10
    )

    return json_response({
        "instance_hash": instance_hash,
        "path": path,
        "result": result["stdout"].strip(),
        "success": "REPLACED" in result["stdout"]
    })

# Registry: src/mcp/registry.py (entry "lab_workspace") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def lab_workspace(lab: str, ctx: Context) -> dict[str, Any]:
    """Overview of a lab's workspace: home contents, project map, listening ports, code-server."""
    user = await get_current_user(ctx)
    ownership = get_ownership()
    instance_hash = ownership.resolve_lab(user, lab_name=lab)
    home = f"/var/labsstorage/home/{user['username']}"

    result = await run_docker_exec(
        instance_hash, "root",
        f"echo ===DIRS===; ls -1 {home} 2>/dev/null | head -50; "
        "echo ===PROJECTS===; cat /etc/projects 2>/dev/null || true; "
        "echo ===PORTS===; ss -tln 2>/dev/null | awk 'NR>1{print $4}' | head -30; "
        "echo ===CODE===; pgrep -f code-server >/dev/null 2>&1 && echo running || echo stopped",
        timeout=15,
    )

    sections: Dict[str, list] = {"DIRS": [], "PROJECTS": [], "PORTS": []}
    code = "unknown"
    current = None
    for line in result.get("stdout", "").splitlines():
        if line.startswith("===") and line.endswith("==="):
            current = line.strip("=")
            continue
        if current in sections:
            if line.strip():
                sections[current].append(line.strip())
        elif current == "CODE" and line.strip():
            code = line.strip()

    return json_response({
        "instance_hash": instance_hash,
        "home": home,
        "top_level": sections["DIRS"],
        "projects_map": sections["PROJECTS"],
        "listening_ports": sections["PORTS"],
        "code_server": code,
        "success": result["success"],
    })

# Registry: src/mcp/registry.py (entry "list_projects") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def list_projects(lab: str, ctx: Context) -> dict[str, Any]:
    """List git projects in the lab home with their current branch (plus /etc/projects map)."""
    user = await get_current_user(ctx)
    ownership = get_ownership()
    instance_hash = ownership.resolve_lab(user, lab_name=lab)
    home = f"/var/labsstorage/home/{user['username']}"

    script = (
        f"find {home} -maxdepth 4 -type d -name .git 2>/dev/null | head -50 | "
        "while read g; do p=$(dirname \"$g\"); "
        "b=$(git -C \"$p\" rev-parse --abbrev-ref HEAD 2>/dev/null || echo '?'); "
        "printf 'PROJECT %s|%s\\n' \"$p\" \"$b\"; done; "
        "echo ===MAP===; cat /etc/projects 2>/dev/null || true"
    )
    result = await run_docker_exec(instance_hash, "root", script, timeout=30)

    projects = []
    mapped = []
    in_map = False
    for line in result.get("stdout", "").splitlines():
        if line.startswith("PROJECT "):
            path, _, branch = line[len("PROJECT "):].partition("|")
            projects.append({"path": path, "branch": branch})
        elif line.strip() == "===MAP===":
            in_map = True
        elif in_map and line.strip():
            mapped.append(line.strip())

    return json_response({
        "instance_hash": instance_hash,
        "projects": projects,
        "count": len(projects),
        "projects_map": mapped,
    })

# Registry: src/mcp/registry.py (entry "write_lab_files") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def write_lab_files(lab: str, files: list, ctx: Context) -> dict[str, Any]:
    """Write several files into a lab in one call (batch of {path, content}).

    Args:
        files: list of {"path": "...", "content": "..."}. Max 20 files,
               100k chars of content each. Paths are relative to your home.
    """
    user = await get_current_user(ctx)
    ownership = get_ownership()
    instance_hash = ownership.resolve_lab(user, lab_name=lab)

    if not isinstance(files, list) or not files:
        return json_response({"success": False, "error": "files must be a non-empty list"})
    if len(files) > 20:
        return json_response({"success": False, "error": "too many files (max 20)"})

    import base64

    from src.mcp.tools.files import safe_user_path

    user_home = f"/var/labsstorage/home/{user['username']}"
    written, failed = [], []
    for i, item in enumerate(files):
        if not isinstance(item, dict) or not isinstance(item.get("path"), str) or not isinstance(item.get("content"), str):
            failed.append({"index": i, "error": "entry must be {path: str, content: str}"})
            continue
        path = item["path"].strip()
        content = item["content"]
        full_path, err = safe_user_path(user, path, allow_home=False)
        if err:
            failed.append({"index": i, "path": path, "error": err})
            continue
        if len(content) > 100_000:
            failed.append({"index": i, "path": path, "error": "content too large (max 100k chars)"})
            continue
        parent = posixpath.dirname(full_path)
        encoded = base64.b64encode(content.encode()).decode()
        result = await run_docker_exec(
            instance_hash, user["username"],
            f"mkdir -p {shlex.quote(parent)} && echo {encoded} | base64 -d > {shlex.quote(full_path)}",
            timeout=10,
        )
        if result["success"]:
            written.append({"path": path, "size": len(content)})
        else:
            failed.append({"index": i, "path": path, "error": result.get("stderr") or "write failed"})

    return json_response({
        "written": len(written),
        "failed": failed,
        "files": written,
        "success": len(failed) == 0,
    })
