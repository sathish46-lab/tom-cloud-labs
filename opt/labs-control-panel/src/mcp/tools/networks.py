"""
MCP Network Tools
Networks, devices, WireGuard configuration.
"""
from mcp.server.fastmcp import Context
from mcp.types import Tool, TextContent

from typing import Any, Dict

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


# Registry: src/mcp/registry.py (entry "networks") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def networks(ctx: Context) -> dict[str, Any]:
    """List Docker networks."""
    user = await get_current_user(ctx)
    result = await run_docker_exec(
        "any",  # Will be resolved
        "root",
        "docker network ls --format '{{.Name}}\t{{.Driver}}\t{{.Scope}}'",
        timeout=15
    )

    networks = []
    for line in result["stdout"].strip().split('\n'):
        if line.strip():
            parts = line.split('\t')
            if len(parts) >= 3:
                networks.append({
                    "name": parts[0],
                    "driver": parts[1],
                    "scope": parts[2]
                })

    return json_response({"networks": networks, "count": len(networks)})

# Registry: src/mcp/registry.py (entry "create_network") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def create_network(name: str, ctx: Context, driver: str = "bridge") -> dict[str, Any]:
    """Create a Docker network."""
    user = await get_current_user(ctx)
    # Validate name
    import re
    if not re.match(r'^[a-zA-Z0-9._-]+$', name):
        return json_response({"error": "Invalid network name"})

    result = await run_docker_exec(
        "any",
        "root",
        f"docker network create --driver {driver} {name}",
        timeout=30
    )

    return json_response({
        "name": name,
        "driver": driver,
        "created": result["success"],
        "result": result
    })

# Registry: src/mcp/registry.py (entry "delete_network") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def delete_network(name: str, ctx: Context) -> dict[str, Any]:
    """Delete a Docker network."""
    user = await get_current_user(ctx)
    result = await run_docker_exec(
        "any",
        "root",
        f"docker network rm {name}",
        timeout=15
    )

    return json_response({
        "name": name,
        "deleted": result["success"],
        "result": result
    })

# Registry: src/mcp/registry.py (entry "network_devices") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def network_devices(lab: str, ctx: Context, network: str = None) -> dict[str, Any]:
    """List devices on a network."""
    user = await get_current_user(ctx)
    ownership = get_ownership()
    instance_hash = ownership.resolve_lab(user, lab_name=lab)

    from src.mcp.db import get_db
    db = get_db()

    doc = db.machine_labs.find_one(
        {"instance_hash": instance_hash},
        {"docker_ip": 1, "credentials": 1}
    )

    if not doc:
        return json_response({"error": "Lab not found"})

    tunnel_ip = doc.get("credentials", {}).get("tunnel_ip")
    docker_ip = doc.get("docker_ip")

    # Use wg show to see WireGuard peers
    result = await run_docker_exec(
        instance_hash,
        "root",
        "wg show all allowed-ips 2>/dev/null || echo 'WireGuard not available'",
        timeout=10
    )

    return json_response({
        "instance_hash": instance_hash,
        "tunnel_ip": tunnel_ip,
        "docker_ip": docker_ip,
        "wireguard_peers": result["stdout"].strip()
    })

# Registry: src/mcp/registry.py (entry "add_network_device") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def add_network_device(lab: str, device_ip: str, ctx: Context) -> dict[str, Any]:
    """Add a device to a network (WireGuard peer)."""
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
    server_pub_key = creds.get("wg_pubkey")

    if not server_pub_key:
        return json_response({"error": "WireGuard not configured"})

    # This would typically be done on the server side
    # For now, return info on how to configure
    return json_response({
        "instance_hash": instance_hash,
        "message": "WireGuard peer configuration must be done on the server",
        "device_ip": device_ip,
        "server_public_key": server_pub_key,
        "endpoint": f"{creds.get('tunnel_ip')}:51820"
    })

# Registry: src/mcp/registry.py (entry "remove_network_device") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def remove_network_device(lab: str, device_ip: str, ctx: Context) -> dict[str, Any]:
    """Remove a device from a network."""
    user = await get_current_user(ctx)
    ownership = get_ownership()
    instance_hash = ownership.resolve_lab(user, lab_name=lab)

    return json_response({
        "instance_hash": instance_hash,
        "message": "WireGuard peer removal must be done on the server",
        "device_ip": device_ip
    })

# Registry: src/mcp/registry.py (entry "wireguard_config") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def wireguard_config(lab: str, ctx: Context) -> dict[str, Any]:
    """Get WireGuard config for a lab."""
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
        return json_response({"error": "Lab not found or no credentials"})

    creds = doc["credentials"]
    tunnel_ip = creds.get("tunnel_ip")
    wg_privkey = creds.get("wg_privkey")
    wg_pubkey = creds.get("wg_pubkey")

    if not tunnel_ip or not wg_privkey:
        return json_response({"error": "WireGuard not configured for this lab"})

    # Generate client config
    config = f"""[Interface]
PrivateKey = {wg_privkey}
Address = {tunnel_ip}/32
DNS = 1.1.1.1

[Peer]
PublicKey = {creds.get('server_wg_pubkey', 'SERVER_PUBLIC_KEY_HERE')}
AllowedIPs = 172.31.0.0/16
Endpoint = vpn.tomweb.in:51820
PersistentKeepalive = 25
"""

    return json_response({
        "instance_hash": instance_hash,
        "tunnel_ip": tunnel_ip,
        "config": config
    })
