"""
MCP Account Tools
User identity, history, and MCP client management.
"""
from mcp.server.fastmcp import Context
from mcp.types import Tool, TextContent

from typing import Any, Dict
from mcp.server.fastmcp import Context


def register_tools(mcp, get_current_user, get_ownership, run_labsctl, run_docker_exec, json_response):
    """Register account-related tools."""

    @mcp.tool()
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

    @mcp.tool()
    async def list_labs(ctx: Context) -> dict[str, Any]:
        """List all labs owned by the current user."""
        user = await get_current_user(ctx)
        ownership = get_ownership()
        labs = ownership.list_user_labs(user)
        return json_response({
            "labs": [
                {
                    "instance_hash": lab["instance_hash"],
                    "lab_name": lab.get("lab_name"),
                    "lab_type": lab.get("lab_type"),
                    "status": lab.get("status"),
                    "created_at": str(lab.get("created_at", ""))
                }
                for lab in labs
            ],
            "count": len(labs)
        })

    @mcp.tool()
    async def my_mcp_clients(ctx: Context) -> dict[str, Any]:
        """List MCP clients (connected agents) for the current user."""
        user = await get_current_user(ctx)
        db = getattr(mcp, '_db', None)
        if db is None:
            from src.mcp.server import get_db
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

    @mcp.tool()
    async def my_history(ctx: Context, limit: int = 50) -> dict[str, Any]:
        """Get recent activity history for the current user."""
        user = await get_current_user(ctx)
        db = getattr(mcp, '_db', None)
        if db is None:
            from src.mcp.server import get_db
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

    @mcp.tool()
    async def help(ctx: Context) -> dict[str, Any]:
        """List all available MCP tools with descriptions."""
        user = await get_current_user(ctx)
        tools = [
            {"name": "whoami", "description": "Get current authenticated user identity"},
            {"name": "list_labs", "description": "List all labs owned by the current user"},
            {"name": "lab_status", "description": "Check if a lab is running, stopped, etc."},
            {"name": "lab_info", "description": "Get full lab information including credentials"},
            {"name": "lab_logs", "description": "Get deployment logs for a lab"},
            {"name": "deploy_lab", "description": "Deploy a lab"},
            {"name": "stop_lab", "description": "Stop a running lab"},
            {"name": "start_lab", "description": "Start a stopped lab"},
            {"name": "pause_lab", "description": "Pause a lab (freeze container)"},
            {"name": "resume_lab", "description": "Resume a paused lab"},
            {"name": "terminate_lab", "description": "Permanently delete a lab"},
            {"name": "renew_lab", "description": "Renew lab expiration (extend TTL)"},
            {"name": "ensure_codeserver", "description": "Start code-server on demand"},
            {"name": "code_status", "description": "Check if code-server is running"},
            {"name": "read_lab_file", "description": "Read a file from the lab filesystem"},
            {"name": "write_lab_file", "description": "Write a file to the lab filesystem"},
            {"name": "edit_lab_file", "description": "Edit a file in the lab filesystem"},
            {"name": "list_lab_files", "description": "List files in a directory"},
            {"name": "glob_lab_files", "description": "Find files matching a glob pattern"},
            {"name": "grep_lab_files", "description": "Search file contents with grep"},
            {"name": "lab_file_tree", "description": "Get directory tree structure"},
            {"name": "download_lab_file", "description": "Download a file from the lab"},
            {"name": "upload_lab_file", "description": "Upload a file to the lab"},
            {"name": "list_lab_processes", "description": "List running processes in the lab"},
            {"name": "stop_lab_process", "description": "Stop a process in the lab"},
            {"name": "wait_for_process", "description": "Wait for a process to complete"},
            {"name": "lab_stats", "description": "Get resource usage stats (CPU, memory, disk)"},
            {"name": "storage_usage", "description": "Get detailed storage usage breakdown"},
            {"name": "wait_for_deploy", "description": "Wait for lab deployment to complete"},
            {"name": "test_autologin", "description": "Test SSH auto-login to lab"},
            {"name": "verify_restart", "description": "Verify lab restarts correctly"},
            {"name": "run_startup_script", "description": "Run the lab's init.sh startup script"},
            {"name": "apply_preferences", "description": "Hot-reload Traefik + run init.sh"},
            {"name": "workspace_pin", "description": "Pin a workspace tab in code-server"},
            {"name": "workspace_unpin", "description": "Unpin a workspace tab"},
            {"name": "workspace_preferences", "description": "Get code-server preferences"},
            {"name": "apply_workspace_preferences", "description": "Apply code-server preferences"},
            {"name": "networks", "description": "List Docker networks"},
            {"name": "create_network", "description": "Create a Docker network"},
            {"name": "delete_network", "description": "Delete a Docker network"},
            {"name": "network_devices", "description": "List devices on a network"},
            {"name": "add_network_device", "description": "Add a device to a network"},
            {"name": "remove_network_device", "description": "Remove a device from a network"},
            {"name": "wireguard_config", "description": "Get WireGuard config for a lab"},
            {"name": "add_domain", "description": "Add a custom domain"},
            {"name": "remove_domain", "description": "Remove a custom domain"},
            {"name": "domain_ssl", "description": "Get SSL certificate info for a domain"},
            {"name": "domain_error_pages", "description": "Get custom error pages for a domain"},
            {"name": "databases", "description": "List databases for a lab"},
            {"name": "create_database", "description": "Create a database"},
            {"name": "delete_database", "description": "Delete a database"},
            {"name": "service_credentials", "description": "List service credentials"},
            {"name": "create_service_credential", "description": "Create service credential"},
            {"name": "delete_service_credential", "description": "Delete service credential"},
            {"name": "service_users", "description": "List service users"},
            {"name": "add_service_user", "description": "Add a service user"},
            {"name": "remove_service_user", "description": "Remove a service user"},
            {"name": "ssh_keys", "description": "List SSH keys for user"},
            {"name": "add_ssh_key", "description": "Add an SSH key"},
            {"name": "delete_ssh_key", "description": "Delete an SSH key"},
            {"name": "enable_ssh_key", "description": "Enable/disable an SSH key"},
            {"name": "my_mcp_clients", "description": "List connected MCP clients"},
            {"name": "my_history", "description": "Get recent activity history"},
            {"name": "help", "description": "List all available MCP tools"},
        ]

        return json_response({"tools": tools, "count": len(tools)})
