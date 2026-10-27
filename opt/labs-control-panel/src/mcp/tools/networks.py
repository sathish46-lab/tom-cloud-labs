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


# ── WireGuard device management (parity set) ─────────────────────────────

import json as _json
import time as _time
import httpx as _httpx

_VPN_CIDR = "172.30.0.0/16"
_VPN_PORT = "44556"


def _vpn_config():
    for p in ("/var/www/env.json", "/opt/labs-control-panel/env.json"):
        try:
            c = _json.load(open(p))
            if "vpn_url" in c or "vpn_db" in c:
                return c
        except Exception:
            continue
    return {}


def _vpn_request(namespace: str, method: str, params: dict, retries: int = 1):
    cfg = _vpn_config()
    url = (cfg.get("vpn_url") or "https://vpns.tomweb.fun/api") + f"/{namespace}/{method}"
    headers = {"X-API-KEY": cfg.get("api_secret", ""), "Content-Type": "application/x-www-form-urlencoded"}
    try:
        with _httpx.Client(verify=False, timeout=12) as cl:
            for attempt in range(retries + 1):
                r = cl.post(url, headers=headers, content=__import__("urllib.parse", fromlist=["urlencode"]).urlencode(params))
                if r.status_code < 500:
                    return r.json() if r.text.strip().startswith("{") else {"result": r.text}
                _time.sleep(attempt)
    except Exception:
        return None
    return None


def _peer_stats():
    resp = _vpn_request("wg", "get_peers", {"device": "wg0"})
    if isinstance(resp, dict) and isinstance(resp.get("peers"), list):
        return {p.get("peer"): p for p in resp["peers"] if isinstance(p, dict)}
    return {}


def _ago_text(seconds: int) -> str:
    if seconds < 0:
        seconds = 0
    m, s = divmod(seconds, 60)
    h, m = divmod(m, 60)
    d, h = divmod(h, 24)
    parts = []
    for v, unit in ((d, "day"), (h, "hour"), (m, "minute"), (s, "second")):
        if v:
            parts.append(f"{v} {unit}{'s' if v != 1 else ''}")
    return ", ".join(parts) + " ago" if parts else "just now"


def _fmt_bytes(n) -> str:
    n = n or 0
    for unit, div in (("GiB", 1024**3), ("MiB", 1024**2), ("KiB", 1024)):
        if n >= div:
            return f"{n / div:.2f} {unit}"
    return f"{n} B"


def _my_devices(db, user):
    ids = get_ownership()._user_identifiers(user)
    return list(db.devices.find({"$and": [{"$or": [
        {"email": user.get("email")}, {"user_id": {"$in": ids}},
    ]}, {"status": {"$ne": "deleted"}}]}))


def _is_mine(user, doc) -> bool:
    return (doc.get("email") == user.get("email")) or (str(doc.get("user_id")) in [str(x) for x in get_ownership()._user_identifiers(user)])


async def list_networks(ctx: Context) -> dict[str, Any]:
    """List the WireGuard networks (VPN interfaces) you can use."""
    user = await get_current_user(ctx)
    db = get_db()
    peers = _peer_stats()
    rows = [{
        "interface": "wg0",
        "name": "[[untrusted]]Public network[[/untrusted]]",
        "cidr": _VPN_CIDR,
        "port": _VPN_PORT,
        "provisioned": True,
        "available": True,
        "public": True,
        "role": "public",
        "owner": "",
        "owner_type": "",
        "owner_name": "",
        "peers": len(peers),
        "can_manage": False,
        "grants": [],
    }]
    return json_response({
        "networks": rows,
        "can_create": {
            "ok": False, "reason": "org_feature", "limit": 0, "count": 0,
            "message": "Private VPN interfaces are an organization feature — create or join an organization, or ask an admin to enable it for your account.",
        },
        "default_networks": [],
        "acting_as": "you",
        "note": "'peers' counts devices on the network. A network must have none before it can be deleted.",
    })

# Registry: src/mcp/registry.py (entry "list_devices") — enabled/tier/cls controlled there.

async def list_devices(ctx: Context) -> dict[str, Any]:
    """List your WireGuard devices (VPN peers) and whether each is online."""
    user = await get_current_user(ctx)
    db = get_db()
    now = int(_time.time())
    peers = _peer_stats()

    rows = []
    for d in _my_devices(db, user):
        pk = d.get("public_key")
        p = peers.get(pk, {})
        ip = d.get("assigned_ip") or ""
        status = "No Connection"
        handshake_time = None
        hs = p.get("latest handshake", "")
        if hs:
            try:
                import re as _re
                parts = {"days": 0, "hours": 0, "minutes": 0, "seconds": 0}
                for val, unit in _re.findall(r"(\d+)\s+(\w+)", hs):
                    parts[unit] = int(val)
                delta = parts["days"] * 86400 + parts["hours"] * 3600 + parts["minutes"] * 60 + parts["seconds"]
            except Exception:
                delta = None
            if delta is not None:
                handshake_time = now - delta
                status = "Online" if delta < 120 else "Offline"
        transfer = p.get("transfer", "")
        sent = received = "0 B"
        if transfer:
            got = [t.strip() for t in transfer.split(",")]
            received = got[0].replace(" received", "").strip() if got else "0 B"
            sent = got[1].replace(" sent", "").strip() if len(got) > 1 else "0 B"
        rows.append({
            "peer": pk,
            "endpoint": p.get("endpoint", "").split(":")[0],
            "allowed ips": f"{ip}/32" if ip else "",
            "latest handshake": hs if hs else "",
            "transfer": p.get("transfer", "0 B received, 0 B sent") if p else "0 B received, 0 B sent",
            "status": status,
            "handshake_time": handshake_time,
            "sync_time": now,
            "sent": sent,
            "received": received,
            "id": str(d.get("_id")),
            "device_name": d.get("device_name"),
            "device_type": d.get("device_type"),
            "network": "wg0",
            "address": ip,
        })

    # Lab peers belong to the platform; never offer them to the user. Count only.
    lab_peers = sum(1 for pk, _p in peers.items() if all(str(r.get("peer")) != pk for r in rows))
    return json_response({
        "devices": rows,
        "total": len(rows) + lab_peers,
        "lab_peers_note": f"{lab_peers} platform lab peers are not listed." if lab_peers else None,
        "acting_as": "you",
    })

# Registry: src/mcp/registry.py (entry "add_device") — enabled/tier/cls controlled there.

async def add_device(name: str, ctx: Context, type: str = "Laptop", public_key: str = None,
                     network: str = None, request_id: str = None) -> dict[str, Any]:
    """Add a WireGuard device so it can connect to the VPN."""
    user = await get_current_user(ctx)
    if network and network not in (None, "", "wg0"):
        return json_response({"error": f"Unknown network '{network}'. This install offers the shared public VPN (wg0) only."})
    if not name or not str(name).strip():
        return json_response({"error": "A device name is required."})
    platform_made = not public_key
    if platform_made:
        import subprocess as _sp
        r = _sp.run("wg genkey | wg pubkey", shell=True, capture_output=True, text=True)
        key = _sp.run("wg genkey", shell=True, capture_output=True, text=True).stdout.strip()
        pub = _sp.run("wg pubkey", input=key, shell=True, capture_output=True, text=True).stdout.strip()
        public_key = pub
        enc = key
    else:
        public_key = public_key.strip()
        enc = None
    _priv = enc
    resp = _vpn_request("wg", "add_peer", {"public_key": public_key, "email": user.get("email"), "device": "wg0"})
    if not isinstance(resp, dict) or not resp.get("result") or resp.get("result") is False:
        return json_response({"error": f"VPN API refused the peer: {resp}" if resp else "VPN API not reachable"})
    assigned_ip = str(resp.get("result"))
    db = get_db()
    doc = {
        "email": user.get("email"),
        "user_id": user.get("user_id") or user.get("_id"),
        "device_name": str(name).strip(),
        "device_type": type or "Laptop",
        "public_key": public_key,
        "assigned_ip": assigned_ip,
        "status": "offline",
        "created_at": int(_time.time()),
        "is_reserved": True,
    }
    if _priv:
        doc["private_key"] = _priv
    res = db.devices.insert_one(doc)
    out = {
        "device_id": str(res.inserted_id),
        "device_name": doc["device_name"],
        "device_type": doc["device_type"],
        "public_key": public_key,
        "network": "wg0",
        "address": assigned_ip,
        "keypair_generated_by": "platform" if platform_made else "user",
        "acting_as": "you",
        "next": "Call device_config(device_id=...) for the file to import into WireGuard. It contains the private key." if platform_made else "The config will not connect until you replace {private_key} with the private key you generated.",
    }
    if request_id:
        out["request_id"] = request_id
    return json_response(out)

# Registry: src/mcp/registry.py (entry "device_config") — enabled/tier/cls controlled there.

async def device_config(device_id: str, ctx: Context, internal: bool = False) -> dict[str, Any]:
    """Get a device's WireGuard config (.conf file) so the user can connect to the VPN."""
    user = await get_current_user(ctx)
    db = get_db()
    from bson import ObjectId as _OID
    q = None
    try:
        q = {"_id": _OID(device_id)}
    except Exception:
        q = None
    doc = None
    for cand in ([q] if q else []) + [{"_id": device_id}, {"device_name": device_id}, {"public_key": device_id}]:
        d = db.devices.find_one({**cand, "status": {"$ne": "deleted"}})
        if d:
            doc = d
            break
    if not doc:
        return json_response({"error": f"Unknown device_id '{device_id}'. Use list_devices for your devices; 'Lab' peers belong to the platform and have no config here."})
    mine = (doc.get("email") == user.get("email")) or (str(doc.get("user_id")) in [str(x) for x in _user_identifiers(user)])
    if not mine:
        return json_response({"error": "A device that is not yours is refused."})
    cfg = _vpn_config()
    serverpub = cfg.get("wireguard_public_key", "")
    port = str(cfg.get("wireguard_endpoint_port") or 51820)
    endpoint = cfg.get("wireguard_endpoint") or f"vpn.tomweb.in:{port}"
    try:
        tunnel = cfg.get("tunnel_ip", "")
        base = tunnel.split(".")[0:3]
        subnet = ".".join(base) + ".0.0/16" if base else _VPN_CIDR
    except Exception:
        subnet = _VPN_CIDR
    priv = doc.get("private_key") or ""
    contains_private_key = False
    if priv:
        keyline = priv
        contains_private_key = True
        note = ("This config contains a live private key for this account's own device. Give it to the user for their WireGuard client, "
                "but do not repeat it back, store it, or commit it anywhere.")
    else:
        keyline = "{private_key}"
        note = "Replace {private_key} with the private key you generated. The platform never had it. The config will not connect until you do."
    conf = (f"[Interface]\nPrivateKey = {keyline}\nAddress = {doc.get('assigned_ip')}/32\n\n"
            f"[Peer]\nPublicKey = {serverpub}\nAllowedIPs = {subnet}\nEndpoint = {endpoint}\nPersistentKeepalive = 30\n")
    return json_response({
        "device_id": str(doc.get("_id")),
        "config": conf,
        "contains_private_key": contains_private_key,
        "belongs_to": "you" if mine else "other",
        "acting_as": "you",
        "note": note,
    })

# Registry: src/mcp/registry.py (entry "delete_device") — enabled/tier/cls controlled there.

async def delete_device(device_id: str, ctx: Context) -> dict[str, Any]:
    """Delete a device and its WireGuard peer."""
    user = await get_current_user(ctx)
    db = get_db()
    from bson import ObjectId as _OID
    doc = None
    try:
        doc = db.devices.find_one({"_id": _OID(device_id), "status": {"$ne": "deleted"}})
    except Exception:
        doc = db.devices.find_one({"_id": device_id, "status": {"$ne": "deleted"}})
    if not doc:
        return json_response({"error": f"Unknown device_id '{device_id}'. Use list_devices first."})
    mine = _is_mine(user, doc)
    if not mine:
        return json_response({"error": "Only the owner, or a member of the organization that owns it, can read the config or delete it."})
    name = doc.get("device_name")
    resp = _vpn_request("wg", "remove_peer", {"peer": doc.get("public_key"), "device": "wg0"})
    ok_platform = isinstance(resp, dict) and resp.get("result") not in (None, "", False)
    res = db.devices.delete_one({"_id": doc["_id"]})
    deleted = res.deleted_count > 0
    out = {
        "device_id": device_id,
        "deleted": deleted and ok_platform,
        "device_name": name,
        "platform_peer_removed": bool(ok_platform),
        "acting_as": "you",
        "note": "The device stops connecting at once. A platform-generated key cannot be recovered." if deleted else "The platform may answer success when removal failed; check list_devices afterwards and only report success if the device is really gone.",
    }
    return json_response(out)

# ── Network sharing (org-feature refusals, honest parity surface) ────────

_ORG_MSG = "Private VPN networks (and sharing them) are an organization feature; this install offers the shared public VPN (wg0) only."


async def share_network(iface: str, subject_type: str, subject_id: str, ctx: Context) -> dict[str, Any]:
    """Share nothing on this install: reports the organization-feature refusal."""
    return json_response({"success": False, "error": _ORG_MSG, "iface": iface, "acting_as": "you"})


async def unshare_network(iface: str, subject_type: str, subject_id: str, ctx: Context) -> dict[str, Any]:
    """Revoke nothing on this install: reports the organization-feature refusal."""
    return json_response({"success": False, "error": _ORG_MSG, "iface": iface, "state": "not_shared", "acting_as": "you"})


async def set_default_networks(networks: list, ctx: Context) -> dict[str, Any]:
    """Set which networks an organization's labs join by default: org-feature refusal."""
    return json_response({"success": False, "error": _ORG_MSG, "acting_as": "you"})


# ── WireGuard exposure (Tom Labs parity) ──────────────────────────────────────

import re as _re  # local namespace (see below, before first use)

def _declared_ports(db, doc):
    """Ports the lab's template declares: network.exposed_ports + services ports."""
    import json as _json2, os as _os2
    slug = doc.get("lab_type")
    ports, shell = set(), set()
    if slug:
        try:
            cfg = _json2.load(open(_os2.path.join(
                "/opt/labs-control-panel/lab-templates", str(slug), "config.json")))
            for p in ((cfg.get("network") or {}).get("exposed_ports") or []):
                ports.add(int(p))
            for _sk, svc in (cfg.get("services") or {}).items():
                try:
                    ports.add(int(svc.get("port")))
                except (TypeError, ValueError):
                    continue
        except Exception:
            pass
    # port 1111 (code editor) has no special proxy here; 8080 code-server gives a shell
    return sorted(ports)


async def lab_wg_exposure(instance_id: str, ctx: Context) -> dict[str, Any]:
    """Whether this lab exposes its template's declared ports on a WireGuard address (no shell), to whom, and whether the platform enforces it."""
    user = await get_current_user(ctx)
    db = get_db()
    if not instance_id or not _re.match(r"^[0-9a-f]{32}$", instance_id):
        return json_response({"error": "Pass the 32-hex instance id from list_labs."})
    doc = db.machine_labs.find_one({"instance_hash": instance_id})
    if not doc or str(doc.get("user_id")) not in [str(x) for x in get_ownership()._user_identifiers(user)]:
        return json_response({"error": "A lab that is not yours is refused."})
    ports = _declared_ports(db, doc)
    shell_ports = [p for p in ports if p in (8080,)]
    return json_response({
        "instance_id": instance_id,
        "kind": "guard",
        "declared_ports": [
            {"port": p, "shell": p in shell_ports, "template": {"id": p, "port": p}} for p in ports
        ],
        "unconfirmed_ports": [],
        "enforced": False,
        "reason": "WireGuard exposure is not enforced on this host: the platform has no paid-plan exposure policy here.",
        "wg_ip": None,
        "live_ports": [],
        "ports": ports,
    })


async def set_lab_wg_exposure(instance_id: str, on: bool, ctx: Context,
                              confirm: bool = False) -> dict[str, Any]:
    """Turn a lab's WireGuard exposure on or off; owner only, paid plans only."""
    user = await get_current_user(ctx)
    return json_response({
        "instance_id": instance_id,
        "on": bool(on),
        "error": "Not enabled on this install: WireGuard exposure needs the platform's paid-plan exposure policy and a template that declares ports; nothing was changed."
        if on else "Exposure was already off on this host; nothing changed.",
    })


async def set_lab_wg_exposure_members(instance_id: str, members: list, ctx: Context) -> dict[str, Any]:
    """Set which members' devices may reach an organisation lab's exposed ports."""
    user = await get_current_user(ctx)
    return json_response({
        "instance_id": instance_id,
        "members": [],
        "error": "Not enabled on this install: WireGuard exposure itself is off (paid-plan feature); there is no member list to set.",
    })
