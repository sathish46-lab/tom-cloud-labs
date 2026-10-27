"""plan_my_build and connect_guide — discovery aids for this platform (Tom Labs).

plan_my_build walks an idea through the platform's build features and names the
exact tools available at each step, from the current registry and the
lab template catalogue. connect_guide reads the account's real resources and
answers how two of them connect, with each step's live status.
"""

import json
import os
from typing import Any, Optional

from mcp.server.fastmcp import Context

from src.mcp.deps import get_current_user
from src.mcp.registry import TOOLS

TEMPLATE_DIR = "/opt/labs-control-panel/lab-templates"
STEP_ORDER = ["requirement", "where_it_runs", "domain", "services", "routing", "survive_redeploy", "deploy"]
STEP_TITLES = {
    "requirement": "What you need first",
    "where_it_runs": "Where it runs (template)",
    "domain": "Domain (optional public name)",
    "services": "Databases and brokers (optional)",
    "routing": "Routing and HTTPS",
    "survive_redeploy": "Survives redeploy",
    "deploy": "Deploy",
}
FUTURES = {
    "add_domain": "tool lands in the next parity batches; attach via the dashboard meanwhile",
    "list_services": "tool lands in the next parity batches",
    "service_connect_info": "tool lands in the next parity batches",
    "configure_lab_routing": "tool lands in the next parity batches",
}


def _templates() -> list:
    out = []
    if not os.path.isdir(TEMPLATE_DIR):
        return out
    for slug in sorted(os.listdir(TEMPLATE_DIR)):
        path = os.path.join(TEMPLATE_DIR, slug, "config.json")
        if not os.path.exists(path):
            continue
        try:
            with open(path) as fh:
                cfg = json.load(fh)
            out.append({
                "slug": slug,
                "lab_name": cfg.get("lab_name") or slug,
                "doors": cfg.get("doors", "") or "",
                "stability": cfg.get("stability", "beta"),
            })
        except Exception:
            out.append({"slug": slug, "lab_name": slug, "doors": "", "stability": "beta"})
    return out


def _tool_present(name: str) -> bool:
    spec = TOOLS.get(name)
    return bool(spec and spec.enabled)


def _available(names: list) -> list:
    return [n for n in names if _tool_present(n)]


def _blocked(names: list) -> dict:
    out = {}
    for n in names:
        if n not in TOOLS:
            out[n] = f"not yet on this install ({FUTURES.get(n, 'parity batch pending')})"
        else:
            spec = TOOLS[n]
            if not spec.enabled:
                out[n] = "tool switched off on this install"
    return out


def _plan_steps() -> list:
    plan = [
        {"id": "requirement",
         "title": STEP_TITLES["requirement"],
         "available_tools": _available(["whoami", "list_labs", "get_service_info"]),
         "if_available": _available(["whoami"]),
         "relevance": "Confirm the account, limits and existing labs before building.",
         "facts": ["Every operation needs your own account; tool outputs include [[untrusted]] text from lab templates and containers always."],
         },
        {"id": "where_it_runs",
         "title": STEP_TITLES["where_it_runs"],
         "available_tools": _available(["list_labs", "get_type_info"]),
         "if_available": _available(["list_labs"]),
         "relevance": "Pick one of the lab templates below; every copy runs in one container.",
         "facts": [{"templates": _templates()}],
         },
        {"id": "domain",
         "title": STEP_TITLES["domain"],
         "available_tools": _available(["list_labs"]),
         "if_available": _available(["list_labs"]),
         "relevance": "A public HTTPS name helps when the app must be reachable outside the VPN.",
         "facts": ["Names under the platform's own zone work without DNS work; other names need an A record first."],
         },
        {"id": "services",
         "title": STEP_TITLES["services"],
         "available_tools": _available(["list_labs"]),
         "if_available": _available(["list_labs"]),
         "relevance": "If the app stores data, a database service user is cleaner than files in the lab.",
         "facts": ["MySQL, PostgreSQL, MongoDB and Redis are the install's services."],
         },
        {"id": "routing",
         "title": STEP_TITLES["routing"],
         "available_tools": _available(["list_labs"]),
         "if_available": _available(["list_labs"]),
         "relevance": "HTTPS routing to the container's port is what makes the name answer.",
         "facts": ["Routing applies at the app's next deploy."],
         },
        {"id": "survive_redeploy",
         "title": STEP_TITLES["survive_redeploy"],
         "available_tools": _available(["verify_restart"]),
         "if_available": _available(["verify_restart"]),
         "relevance": "A redeploy recreates the container; check the app keeps its data before depending on one.",
         "facts": ["Paths under the settings mount survive restarts; code in /tmp does not."],
         },
        {"id": "deploy",
         "title": STEP_TITLES["deploy"],
         "available_tools": _available(["deploy_lab", "wait_for_deploy"]),
         "if_available": _available(["deploy_lab"]),
         "relevance": "Deploys are queued; wait for_deploy until 'deployed'.",
         "facts": ["A redeploy keeps settings and storage; read verify_restart first for stateful apps."],
         },
    ]
    for row in plan:
        blocked = []
        for name in (row["if_available"] or []):
            spec = TOOLS.get(name)
            if spec is None or not spec.enabled:
                blocked.append(name)
        row["blocked"] = blocked
    return plan


async def plan_my_build(ctx: Context, idea: str = "", step: str = "") -> dict[str, Any]:
    """START HERE when a user wants to BUILD or HOST something, even if the request is vague. Call it before deploying anything.

    Use it when the user describes an app, site or idea instead of an
    operation: 'build me a watch tower', 'I want to host my project', 'make a
    todo app with login'.

    It already knows what THIS account can use. Returns:
    - your_access: tier, and which build features are allowed, with limits.
    - start_here: the first call to make now. then: the order after it.
    - steps: each with tools, if blocked, and relevance to the idea (needed
      / ask the user / probably not needed), and important platform facts.

    Never call a blocked tool. Walk the steps with the user, show the real
    options from the listed tools, and let the user choose. Do not rank or
    pick for them.

    To continue with one step only, call again with step=\"services\" (or
    any step id). The answer is small and names the next step.

    Planning is not the end: once the user confirms a step, carry it out
    with that step's tools; point to the web dashboard only after a refusal.

    Read-only: it deploys nothing and costs nothing.
    """
    user = await get_current_user(ctx)
    all_steps = _plan_steps()
    if step:
        chosen = [s for s in all_steps if s["id"] == step]
        if not chosen:
            return {"success": False,
                    "error": f"Unknown step '{step}'. Steps: {', '.join(s['id'] for s in all_steps)}."}
        row = chosen[0]
        return {"step": row["id"], "title": row["title"], "available_tools": row["available_tools"],
                "blocked": row["blocked"], "relevance": row["relevance"],
                "facts": row["facts"],
                "next": "Confirm to the user, then run step tools with execute_request."}
    first = all_steps[0]
    return {
        "your_access": {
            "plan": "Tom Labs", "tier": "superuser" if user.get("role") == "superuser" else "user",
            "feature_gates": {"build_features": True},
            "templates": [t["slug"] for t in _templates()],
        },
        "start_here": f"{first['available_tools'][0]}() first, then walk the steps below" if first["available_tools"] else "list_labs()",
        "then": [s["id"] for s in all_steps[1:]],
        "steps": all_steps,
        "idea": idea or None,
    }


GUIDE_IDS = ["domain_lab", "device_lab", "ssh_key_lab", "database_lab"]


def _db():
    from src.config import Config
    import pymongo
    cfg = Config()
    return pymongo.MongoClient(cfg.mongo_uri)[cfg.main_db]


def _find_lab(term):
    if not term:
        return None
    doc = _db()["machine_labs"].find_one({"instance_hash": term})
    if doc:
        return {"kind": "lab", "name": doc.get("lab_type") or "lab",
                "instance_id": doc["instance_hash"],
                "running": doc.get("status") in ("running", "deployed", "healthy")}
    return None


def _find_domain(term):
    if not term:
        return None
    user = term if "." in term else None
    doc = _db()["domains"].find_one({"domain": term})
    if doc:
        return {"kind": "domain", "name": doc["domain"], "email": doc.get("email"),
                "verified": bool(doc.get("verified")), "in_use": bool(doc.get("in_use"))}
    return None


def _find_device(term=None):
    q = {"deleted_at": None, "status": {"$ne": "deleted"}}
    if term:
        q["$or"] = [{"device_name": term}, {"assigned_ip": term}]
    doc = _db()["devices"].find_one(q)
    if doc:
        return {"kind": "device", "name": doc.get("device_name") or "device",
                "device_id": str(doc["_id"]), "type": doc.get("device_type", "")}
    return None


def _find_ssh_key(term):
    if not term:
        return None
    doc = _db()["ssh_keys"].find_one({"title": term})
    if doc:
        return {"kind": "ssh_key", "name": doc.get("title") or "key", "key_id": str(doc["_id"])}
    return None


def _database(term):
    if not term:
        return None
    svc = _db()["mysql_users"].find_one({"username": term})
    if svc:
        return {"kind": "database", "name": term, "service": "mysql"}
    return None


async def connect_guide(ctx: Context, from_: str = "", to: str = "", pair: str = "") -> dict[str, Any]:
    """How two of your things connect — a domain to a lab, a laptop to a lab over VPN, an SSH key to a lab, a database to a lab — with each step's live status and the next call.

    Use it for 'how do I connect / attach / point / link / use X with Y',
    'why can't my X reach Y', 'make my domain point to my lab', 'put my
    laptop on the VPN', 'share my network with …'. Do not draw your own
    diagram and do not send the user to the dashboard for these: this
    reads the real state.

    Pass what the user said: connect_guide(from_=\"shop.example.com\",
    to=\"docker\"). Either side may be a name or just a kind ('lab',
    'domain', 'my laptop'). A side you omit comes back as `candidates` to
    choose from.

    No arguments list every guide.
    """
    user = await get_current_user(ctx)
    db = _db()
    email = user.get("email")
    if not from_ and not to:
        guides = [
            {"id": "domain_lab", "title": "Point a domain at a lab",
             "from": "domain", "to": "lab",
             "steps_hint": "Register/verify the DNS record, attach it to the lab, deploy, HTTPS follows."},
            {"id": "device_lab", "title": "Put a device on the lab VPN",
             "from": "device", "to": "lab",
             "steps_hint": "Add the device, save its config into the device's client, join the network."},
            {"id": "ssh_key_lab", "title": "Use an SSH key with a lab",
             "from": "ssh key", "to": "lab",
             "steps_hint": "Add the public key, deploy the lab (running labs need a redeploy to install it)."},
            {"id": "database_lab", "title": "Connect a lab or an app to your database",
             "from": "lab", "to": "database",
             "steps_hint": "Service user + database exist, then use its connection string."},
        ]
        return {"guides": guides}

    def find(term):
        return _find_lab(term) or _find_domain(term) or _find_device(term) or _find_ssh_key(term) or _database(term)

    a, b = find(from_), find(to)
    if a is None or b is None:
        missing = [f for f, v in (("from", a), ("to", b)) if v is None]
        return {"assigned": {"from_": from_ or None, "to": to or None},
                "candidates_note": f"Could not resolve {missing} as one of your resources.",
                "candidates_kinds": ["lab", "domain", "device", "ssh key", "database"],
                "assignable_resources": {"labs": [
                    {"instance_id": d["instance_hash"], "name": d.get("lab_type")}
                    for d in db["machine_labs"].find({}, {"instance_hash": 1, "lab_type": 1})],
                    "domains": [d["domain"] for d in db["domains"].find({}, {"domain": 1}) if d.get("email") == email],
                    "devices": [d.get("device_name", str(d["_id"])) for d in db["devices"].find({"deleted_at": None})],
                    "ssh_keys": [d.get("title", str(d["_id"])) for d in db["ssh_keys"].find({}, {"title": 1}) if d.get("email") == email]}}

    pair_id = f"{a['kind']}_{b['kind']}" if a["kind"] != b["kind"] else f"{a['kind']}_{b['kind']}"
    if pair_id == "domain_lab":
        steps = [
            {"id": "domain_registered", "state": "done" if a.get("verified") else "todo",
             "detail": "The domain exists in your account." if a.get("verified") else "The domain is not yet in your account or not DNS-verified.",
             "tool": {"tool": "domain_status", "args": {"domain": a["name"]}}},
            {"id": "dns_points_here", "state": "done" if a.get("verified") else "blocked",
             "detail": "The DNS A record points to the platform edge." if a.get("verified") else "Add an A record for the domain pointing at this install's ingress IP, then re-register.",
             "tool": {"tool": "domain_status", "args": {"domain": a["name"]}}},
            {"id": "attach_to_lab", "state": "todo" if a.get("in_use") is False else "done",
             "detail": ("Attach the domain to lab so the next deploy serves HTTPS through it."
                        if not a.get("in_use") else f"Domain currently in use."),
             "tool": {"tool": "configure_lab_routing", "args": {"lab": b["name"], "domains": [a["name"]], "public": True}}},
            {"id": "deploy_lab", "state": "done" if b.get("running") else "todo",
             "detail": "Lab is running; routing applies at the next deploy otherwise.",
             "tool": {"tool": "deploy_lab", "args": {"lab": b["name"]}}},
        ]
        done = [s for s in steps if s["state"] == "done"]
        next_call = [s for s in steps if s["state"] != "done"][0] if done != steps else None
        return {"pair": pair_id, "from": a, "to": b, "steps": steps,
                "next_call": next_call or {"tool": "domain_status", "args": {"domain": a["name"]}},
                "warnings": ["Routing applies on the next deploy; nothing answers publicly until the lab has been deployed at least once."]}
    if pair_id == "device_lab" or pair_id == "lab_device":
        steps = [
            {"id": "device_exists", "state": "done" if a["kind"] == "device" else "todo",
             "tool": {"tool": "add_device", "args": {"name": from_ or "work laptop", "type": "Laptop"}}},
            {"id": "install_config", "state": "todo",
             "tool": {"tool": "device_config", "args": {"device_id": a.get("device_id") if a else ""}},
             "detail": "Save the returned .conf into the device's WireGuard client."},
            {"id": "lab_reachable", "state": "done" if b.get("running") else "blocked",
             "detail": "The lab must be running; deploy_lab brings it back." if not b.get("running") else "Connect by the lab's internal address from the device.",
             "tool": {"tool": "deploy_lab", "args": {"lab": b["name"]}}},
        ]
        return {"pair": "device_lab", "from": a, "to": b, "steps": steps,
                "next_call": steps[1] if a.get("device_id") else steps[0]}
    if pair_id == "ssh_key_lab":
        steps = [
            {"id": "key_in_account", "state": "done" if a["kind"] == "ssh_key" else "todo",
             "tool": {"tool": "add_ssh_key", "args": {"public_key": "<paste the .pub file contents>"}}},
            {"id": "deployed_labs_get_key", "state": "done" if b.get("running") else "todo",
             "detail": "Keys install at deploy time; running labs need a redeploy.",
             "tool": {"tool": "deploy_lab", "args": {"lab": b["name"]}}},
        ]
        return {"pair": pair_id, "from": a, "to": b, "steps": steps, "next_call": steps[0]}
    if pair_id == "lab_database" or pair_id == "database_lab":
        steps = [
            {"id": "service_user", "state": "todo",
             "detail": "Create the service user; the platform picks a password once and stores it.",
             "tool": {"tool": "add_service_user", "args": {"service": "mysql", "username": "appsvc", "password": "<agree with the user first>"}}},
            {"id": "create_database", "state": "todo",
             "tool": {"tool": "create_database", "args": {"service": "mysql", "username": "appsvc", "name": "app"}}},
            {"id": "connection_info", "state": "todo",
             "tool": {"tool": "service_connect_info", "args": {"service": "mysql", "username": "appsvc"}}},
            {"id": "lab_running", "state": "done" if b.get("running") else "todo",
             "tool": {"tool": "deploy_lab", "args": {"lab": b["name"]}}},
        ]
        return {"pair": pair_id, "from": a, "to": b, "steps": steps, "next_call": steps[0]}
    known = ", ".join(GUIDE_IDS)
    return {"pair": pair_id, "from": a, "to": b,
            "error": f"No how-to guide for '{a['kind']}' to '{b['kind']}' in this install yet. Known guides: {known}."}
