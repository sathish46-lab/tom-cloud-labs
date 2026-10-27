"""
MCP SSL Tools
Certificate inventory and DNS/expiry troubleshooting for the platform ACME store.

SECURITY: reads the platform Traefik acme.json (server-local file) and the
caller's own domain records in Mongo. Only certificates covering domains the
CALLER owns are returned. No private keys are ever included.
"""
import asyncio
import base64
import json
import os
from datetime import datetime, timezone
from typing import Any, Dict, List, Optional

from mcp.server.fastmcp import Context

from src.mcp.db import get_db
from src.mcp.deps import get_current_user, json_response

# Candidate locations of the platform Traefik ACME store (first hit wins).
ACME_PATHS = (
    "/etc/traefik/acme.json",
    "/var/www/traefik-conf/acme.json",
    "/var/www/labs/traefik-conf/acme.json",
)


def _server_ip() -> Optional[str]:
    """Authoritative public IP from labs/htdocs/ip.json (SERVER_IP)."""
    for path in ("/var/www/labs/htdocs/ip.json", "labs/htdocs/ip.json"):
        try:
            with open(path) as fh:
                ip = json.load(fh).get("SERVER_IP")
            if ip:
                return str(ip)
        except Exception:
            continue
    return None


def _load_server_certs() -> List[Dict[str, Any]]:
    """Parse every certificate in the ACME store (no private keys, no keys at all)."""
    path = next((p for p in ACME_PATHS if os.path.isfile(p)), None)
    if not path:
        return []
    try:
        with open(path) as fh:
            data = json.load(fh)
    except Exception:
        return []

    from cryptography import x509

    now = datetime.now(timezone.utc)
    out: List[Dict[str, Any]] = []
    for resolver, blob in (data or {}).items():
        if not isinstance(blob, dict):
            continue
        for entry in blob.get("Certificates") or []:
            b64 = entry.get("certificate")
            if not b64:
                continue
            try:
                pem = base64.b64decode(b64)
                cert = x509.load_pem_x509_certificate(pem)
            except Exception:
                continue
            main = ((entry.get("domain") or {}).get("main")) or ""
            sans: List[str] = []
            try:
                ext = cert.extensions.get_extension_for_class(x509.SubjectAlternativeName)
                sans = list(ext.value.get_values_for_type(x509.DNSName))
            except Exception:
                pass
            try:
                expires = cert.not_valid_after_utc
            except AttributeError:
                expires = cert.not_valid_after.replace(tzinfo=timezone.utc)
            days_left = (expires - now).days
            out.append({
                "main_domain": main,
                "sans": sans,
                "resolver": resolver,
                "issued": (getattr(cert, "not_valid_before_utc", None)
                           or cert.not_valid_before.replace(tzinfo=timezone.utc)).isoformat(),
                "expires": expires.isoformat(),
                "days_left": days_left,
                "is_expired": days_left < 0,
                "is_valid": days_left >= 0,
            })
    return out


def _owned_names(db, user) -> set:
    """Every domain name the caller owns: registered domains + attached lab domains."""
    names = set()
    for d in db.domains.find({"user_id": user["user_id"]}, {"domain": 1}):
        if d.get("domain"):
            names.add(d["domain"].lower())
    for lab in db.machine_labs.find({"user_id": user["user_id"]}, {"domains": 1, "code_domain": 1, "gui_domain": 1}):
        for key in ("domains",):
            for v in lab.get(key) or []:
                if isinstance(v, str):
                    names.add(v.lower())
        for key in ("code_domain", "gui_domain"):
            if lab.get(key):
                names.add(str(lab[key]).lower())
    return names


async def _resolve_a(domain: str) -> Optional[str]:
    """First A record via Google DNS-over-HTTPS (None = no answer / lookup failed)."""
    import httpx
    try:
        async with httpx.AsyncClient(timeout=5.0) as client:
            resp = await client.get(
                f"https://dns.google/resolve?name={domain}&type=A",
                headers={"Accept": "application/dns-json"},
            )
            if resp.status_code == 200:
                for ans in resp.json().get("Answer", []):
                    if ans.get("type") == 1:
                        return str(ans.get("data"))
    except Exception:
        return None
    return None


# Registry: src/mcp/registry.py (entry "list_ssl_certificates") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def list_ssl_certificates(ctx: Context) -> dict[str, Any]:
    """List TLS certificates covering domains you own (issue/expiry, SANs, days left)."""
    user = await get_current_user(ctx)
    db = get_db()
    owned = _owned_names(db, user)
    all_certs = _load_server_certs()
    mine = [c for c in all_certs
            if c["main_domain"].lower() in owned
            or (set(s.lower() for s in c["sans"]) & owned)]
    return json_response({
        "certificates": mine,
        "count": len(mine),
        "server_cert_count": len(all_certs),
        "owned_domain_count": len(owned),
    })


# Registry: src/mcp/registry.py (entry "ssl_troubleshoot") — enabled/tier/cls controlled there (enabled=False or comment out the entry to disable).

async def ssl_troubleshoot(ctx: Context, domain: str = "") -> dict[str, Any]:
    """Check DNS + certificate health for your domains: A record vs server IP, expiry.

    Args:
        domain: single domain to check; omit to check all domains you own (capped).
    """
    user = await get_current_user(ctx)
    db = get_db()
    server_ip = _server_ip()
    certs = _load_server_certs()
    owned = _owned_names(db, user)

    target = (domain or "").strip().lower()
    if target:
        if owned and target not in owned:
            return json_response({
                "success": False,
                "error": f"domain not found for your account: {domain}",
                "owned_domain_count": len(owned),
            })
        targets = [target]
    else:
        targets = sorted(owned)[:50]

    findings = []
    high = 0
    for name in targets:
        issues: List[str] = []
        dns_ip = await _resolve_a(name)
        if dns_ip is None:
            issues.append("no A record found")
            status = "error"
        elif server_ip and dns_ip != server_ip:
            issues.append(f"A record {dns_ip} does not point at server {server_ip}")
            status = "warning"
        else:
            status = "ok"

        cert = next((c for c in certs
                     if c["main_domain"].lower() == name
                     or name in [s.lower() for s in c["sans"]]), None)
        if cert is None:
            issues.append("no certificate found for this name")
            status = "error" if status == "error" else "warning"
        elif cert["days_left"] < 0:
            issues.append(f"certificate expired {abs(cert['days_left'])} days ago")
            status = "error"
        elif cert["days_left"] < 14:
            issues.append(f"certificate expires in {cert['days_left']} days")
            status = "error" if status == "error" else "warning"

        if status == "error":
            high += 1
        findings.append({
            "domain": name,
            "status": status,
            "dns_a": dns_ip,
            "server_ip": server_ip,
            "issues": issues,
            "has_cert": cert is not None,
            "days_left": cert["days_left"] if cert else None,
            "expires": cert["expires"] if cert else None,
        })

    return json_response({
        "findings": findings,
        "count": len(findings),
        "high": high,
        "server_ip": server_ip,
        "checked_at": datetime.now(timezone.utc).isoformat(),
    })


# ── Tom Labs parity: certificate re-issue requests ────────────────────────────

_REJECT_FORM = {
    "OK", "expiring_soon",  # only failed certificates qualify
}


async def request_ssl_reissue(cert_id: str, ctx: Context, resolver: str = None) -> dict[str, Any]:
    """File a re-issue request for a broken (expired/failing) certificate."""
    user = await get_current_user(ctx)
    db = get_db()
    all_certs = _load_server_certs()
    cert = None
    for c in all_certs:
        fp = c.get("fingerprint")
        if fp and cert_id and fp.startswith(cert_id):
            cert = c
            break
    if cert is None:
        days_left = None
        for c in all_certs:
            if cert_id and (c["main_domain"].lower() in cert_id or cert_id in str(c.get("fingerprint", ""))):
                cert = c
        if cert is None:
            return json_response({"error": f"Unknown cert_id '{cert_id}'. Copy it from list_ssl_certificates."})
    failed = cert.get("is_expired") or (cert.get("days_left") is not None and cert["days_left"] < 30 and False)
    expired = bool(cert.get("is_expired"))
    if not expired:
        return json_response({
            "error": "certificate_not_failing",
            "detail": "Only an expired certificate (or one whose renewal is failing) can be re-issued; yours is still valid.",
            "cert": {"main_domain": cert["main_domain"], "days_left": cert.get("days_left")},
        })
    from datetime import datetime, timezone
    rid = f"req-{datetime.now(timezone.utc).strftime('%Y%m%d%H%M%S')}-{cert['main_domain'].replace('.', '-')}"
    doc = {
        "request_id": rid,
        "user_id": user.get("user_id"),
        "email": user.get("email"),
        "cert_main_domain": cert["main_domain"],
        "resolver": cert.get("resolver") or resolver or "",
        "state": "pending",
        "created_at": datetime.now(timezone.utc).isoformat(),
    }
    db.ssl_reissue_requests.insert_one(dict(doc))
    doc.pop("_id", None)
    return json_response({"accepted": True, "request": doc, "note": "Files a request only. An administrator must approve and apply it; no tool can self-issue."})


async def cancel_ssl_request(request_id: str, ctx: Context) -> dict[str, Any]:
    """Withdraw a re-issue request that has not been applied yet."""
    user = await get_current_user(ctx)
    db = get_db()
    doc = db.ssl_reissue_requests.find_one({"request_id": request_id, "user_id": user.get("user_id")})
    if not doc:
        return json_response({"error": f"Unknown request_id '{request_id}'."})
    if doc.get("state") != "pending":
        return json_response({"error": f"Request is already '{doc.get('state')}' and cannot be withdrawn."})
    db.ssl_reissue_requests.update_one({"_id": doc["_id"]}, {"$set": {"state": "cancelled", "cancelled_at": datetime.now(timezone.utc).isoformat()}})
    return json_response({"cancelled": True, "request_id": request_id})
