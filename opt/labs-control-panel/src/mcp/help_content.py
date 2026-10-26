"""
Help system — static content plus a registry-driven index.

SECURITY: static text only. No DB access, no secrets, no per-user data.
Tool lists come from src/mcp.registry (single source of truth), so help
can never drift from what is actually registered.

Three response shapes (the `help` tool's `mode` argument):
    overview   default — what this server is, category counts, topic list,
               and how to drill down
    detail     full markdown for one topic (`topic="getting-started"`)
    index      machine-readable catalog of every enabled tool + topic list

Also served as MCP resources (`tomlabs://help/{topic}`) and condensed into
FastMCP(server instructions=...) so every client sees a short orientation.
"""

from typing import Any, Dict, List

from src.mcp.registry import CATEGORY_BY_MODULE, catalog, enabled_specs

SERVER_INSTRUCTIONS = (
    "Tom Labs MCP Server: drive your Tom Labs cloud dev containers (labs) over OAuth 2.1. "
    "Call help(topic='getting-started') first if you are new; help(mode='index') returns "
    "the full machine-readable tool catalog. Tools are annotated: readOnlyHint marks safe "
    "reads, destructiveHint marks tools that delete resources (always require the caller's "
    "confirm=true), idempotentHint marks retry-safe operations. Every tool is scoped to the "
    "labs owned by the authenticated user — other users' labs are not addressable. "
    "Long-running lab operations (deploy, wait_for_deploy) are asynchronous: poll "
    "lab_status/lab_logs rather than blocking."
)

MODES = ("overview", "detail", "index")

TOPICS: Dict[str, Dict[str, str]] = {
    "overview": {
        "title": "Server overview",
        "body": """\
# Tom Labs MCP Server

A Model Context Protocol server for Tom Labs: cloud development containers with
SSH, domains, TLS, databases, storage and WireGuard networking.

- Transport: streamable HTTP at `/mcp`, OAuth 2.1 bearer tokens (`labs:*` scope).
- Tools: every tool is registered from a central registry (enable/disable and
  security class are decided there — a tool without a registry entry is never
  served).
- Audience: tools are scoped to the authenticated user. Lab ownership is
  enforced server-side; another user's lab cannot be addressed even with its
  name.
- Categories: Account, Lifecycle, Workspace, Files, Processes, Networks,
  Domains, Services, SSH.

## Help modes
- `help()` → overview (this topic's short form, category counts, topic list)
- `help(topic="getting-started")` → full topic markdown
- `help(mode="index")` → machine-readable catalog of every enabled tool

The same topics are exposed as resources under `tomlabs://help/{topic}`.
""",
    },
    "getting-started": {
        "title": "Getting started",
        "body": """\
# Getting started

1. `whoami()` — confirm who you are authenticated as (user id, role, tier).
2. `list_labs()` — see your labs and their states.
3. `lab_info(lab=...)` — credentials, addresses and expiry for one lab.
4. `lab_status(lab=...)` — cheap liveness check (deployed / stopped / deploying).
5. Start working: `workspace_pin(lab=...)` opens the code editor workspace,
   `read_lab_file` / `write_lab_file` / `run_command`-style tools operate
   inside the lab.

## Typical first flow
    whoami()
    list_labs()
    lab_status(lab="<name>")
    lab_info(lab="<name>")

## Asynchronous operations
`deploy_lab`, `wait_for_deploy`, `wait_for_process` and `verify_restart` are
long-running. They return quickly with progress info — poll `lab_status` or
`lab_logs` instead of assuming completion.

## If a tool says "Available labs: ..."
The ownership guard self-heals: it lists the labs you actually own. Re-issue
the call with one of those names. You can never address another user's lab.
""",
    },
    "tools": {
        "title": "Using tools safely",
        "body": """\
# Using tools safely

Every tool carries MCP ToolAnnotations derived from its registry class:

- `readOnlyHint: true`  — safe, changes nothing (list/read/status/help).
- `destructiveHint: true` — deletes or destroys (terminate, delete, remove,
  revoke). These tools refuse to run unless you pass `confirm: true`.
- `idempotentHint: true` — retrying is safe; the result does not change with
  repeats (mostly reads).

## Security model
- Authentication: OAuth 2.1 bearer token; scope `labs:*`.
- Authorization: lab ownership enforced server-side on every lab-scoped call;
  admin-tier tools additionally require the `superuser` role (fail-closed).
- Feature gate: whole categories (mcp / admin) can be switched off at runtime;
  a gated tool answers with a clear refusal, not an empty result.
- Data: tool responses contain only your own resources. Database handles and
  internal connection strings never leave the server.

## Argument validation
Mutating global tools validate arguments before touching anything; a wrong
type is rejected with an argument-validation error and no side effects.

## Errors you may see
- Ownership refusal with an `Available labs:` list — pick a lab you own.
- Feature-gate refusal — the capability is switched off for now.
- 401 — token missing/expired; re-authenticate.
- Validation error — fix the argument shape from the tool's input schema.
""",
    },
    "categories": {
        "title": "Tool categories",
        "body": """\
# Tool categories

- **Account** — identity (`whoami`), your lab list, MCP clients, activity
  history, help.
- **Lifecycle** — deploy, start/stop/pause/resume, renew, terminate, logs,
  status, deploy progress, code-server health, restart verification.
- **Workspace** — code-server preferences, pin/unpin tabs, read/write/edit
  lab files through the workspace.
- **Files** — list/glob/grep/tree/download/upload/move/create/delete inside
  a lab.
- **Processes** — list lab processes, wait for jobs, CPU/memory/disk stats,
  storage usage, HTTP probe, stop a process.
- **Networks** — Docker networks, devices, WireGuard config.
- **Domains** — register/claim/add/verify/remove domains, SSL and error-page
  info.
- **Services** — databases and service users/credentials (MySQL, MariaDB,
  PostgreSQL, MongoDB, Redis, RabbitMQ).
- **SSH** — list/add/enable/delete SSH keys.

`help(mode='index')` returns the authoritative per-tool list with each tool's
category, security class and one-line purpose — that index always matches
what is registered.
""",
    },
    "workflows": {
        "title": "Common workflows",
        "body": """\
# Common workflows

## Spin up and edit code
    list_labs()
    deploy_lab(lab="<name>")        # if stopped
    wait_for_deploy(lab="<name>")
    workspace_pin(lab="<name>")
    write_lab_file(lab="<name>", path="app/main.py", content="...")
    read_lab_file(lab="<name>", path="app/main.py")

## Inspect a running lab
    lab_status(lab="...")
    lab_info(lab="...")
    lab_logs(lab="...")
    list_lab_processes(lab="...")
    lab_stats(lab="...")

## Attach a domain
    available_domains()
    add_domain(lab="...", domain="app.example.com")   # or register/claim
    verify_domain(...)
    domain_ssl(domain="...")

## Databases
    databases(lab="...")
    add_service_user(...); create_database(...)
    service_credentials(...)          # credentials for YOUR user only

## Housekeeping
    my_history()                      # what you (and your clients) did
    my_mcp_clients()                  # connected MCP clients
    terminate_lab(lab="...", confirm=true)   # destructive → confirm required
""",
    },
    "security": {
        "title": "Security model",
        "body": """\
# Security model

- **Auth**: OAuth 2.1 authorization-code + PKCE for clients; bearer tokens are
  stored hashed (SHA-256 lookup hash + bcrypt secret) and expire.
- **Scope**: one scope, `labs:*`. Admin-tier tools additionally require the
  `superuser` role; the check runs before the tool body, fail-closed.
- **Ownership**: every lab-scoped call resolves the lab against the caller's
  own labs. Names belonging to other users are refused and never leak data.
- **Annotations**: destructive tools advertise `destructiveHint` and require
  `confirm: true`.
- **Registry control**: tools are enabled/disabled centrally; commenting out
  a registry entry (or `enabled=False`) removes the tool from the server
  entirely — no code path can register an undeclared tool.
- **Audit**: MCP calls are logged to an activity record (user, tool, outcome,
  timing) for `my_history` and admin review.
- **No secret egress**: internal DB handles, connection strings and platform
  secrets are never part of tool responses or logs.
""",
    },
    "troubleshooting": {
        "title": "Troubleshooting",
        "body": """\
# Troubleshooting

- **"Available labs: ..." refusal** — you asked for a lab you do not own (or a
  typo). Re-issue with one of the listed names.
- **401 Unauthorized** — token missing, expired or revoked. Run the OAuth
  flow again and send `Authorization: Bearer <token>`.
- **Feature gate refusal** — the `mcp` or `admin` feature is off; ask your
  administrator to enable it.
- **Tool missing from tools/list** — it is disabled in the registry, or your
  tier cannot see it. `help(mode='index')` lists what is actually enabled for
  you.
- **Deploy seems stuck** — check `deploy_progress(lab=...)` and
  `lab_logs(lab=...)`; deploys are asynchronous.
- **code-server won't open** — `code_status(lab=...)`, then
  `ensure_codeserver(lab=...)`.
- **Where did a call go?** — `my_history()` shows your recent MCP activity;
  admins can audit globally.
""",
    },
}


def topic_ids() -> List[str]:
    return list(TOPICS.keys())


def _category_summary() -> List[Dict[str, Any]]:
    """[{name, count}] for every category that has at least one enabled tool."""
    counts: Dict[str, int] = {}
    for spec in enabled_specs():
        cat = CATEGORY_BY_MODULE.get(spec.module, "Other")
        counts[cat] = counts.get(cat, 0) + 1
    return [{"name": name, "count": counts[name]} for name in sorted(counts)]


def render_overview() -> Dict[str, Any]:
    """Mode 1 — orientation: what/why/where to go next."""
    return {
        "mode": "overview",
        "server": "Tom Labs MCP Server",
        "tool_count": len(enabled_specs()),
        "categories": _category_summary(),
        "topics": [{"id": t, "title": TOPICS[t]["title"]} for t in TOPICS],
        "next": "help(topic='getting-started') for a walkthrough, "
                "help(mode='index') for the full tool catalog, "
                "or read the resource tomlabs://help/<topic>.",
    }


def render_detail(topic: str) -> Dict[str, Any]:
    """Mode 2 — full markdown for one topic (or a helpful unknown-topic error)."""
    entry = TOPICS.get(topic)
    if entry is None:
        return {
            "success": False,
            "error": f"unknown help topic: {topic!r}",
            "available": topic_ids(),
        }
    return {
        "mode": "detail",
        "topic": topic,
        "title": entry["title"],
        "body": entry["body"],
        "resource_uri": f"tomlabs://help/{topic}",
    }


def render_index() -> Dict[str, Any]:
    """Mode 3 — machine-readable catalog straight from the registry."""
    tools = [
        {
            "name": t["name"],
            "category": t["category"],
            "cls": t["cls"],
            "tier": t["tier"],
            "purpose": t["description"],
        }
        for t in catalog()
    ]
    return {
        "mode": "index",
        "tool_count": len(tools),
        "tools": tools,
        "topics": topic_ids(),
        "modes": list(MODES),
    }


def render_help(topic: str = "", mode: str = "") -> Dict[str, Any]:
    """Dispatch the `help` tool. topic implies detail unless mode is explicit."""
    topic = (topic or "").strip()
    mode = (mode or "").strip().lower() or ("detail" if topic else "overview")

    if mode == "topic":          # accepted alias
        mode = "detail"
    if mode not in MODES:
        return {
            "success": False,
            "error": f"unknown help mode: {mode!r}",
            "available": list(MODES),
        }
    if mode == "detail" and not topic:
        return {
            "success": False,
            "error": "mode='detail' requires a topic",
            "available": topic_ids(),
        }
    if mode == "overview":
        out = render_overview()
        if topic:                # unknown topic with default mode → say so
            err = render_detail(topic)
            if err.get("success") is False:
                out["topic_error"] = err["error"]
                out["available"] = err["available"]
        return out
    if mode == "detail":
        return render_detail(topic)
    return render_index()


def resource_text(topic: str) -> str:
    """Markdown for the tomlabs://help/{topic} resource."""
    entry = TOPICS.get(topic)
    if entry is None:
        ids = ", ".join(topic_ids())
        return f"# Unknown help topic: {topic}\n\nAvailable topics: {ids}\n"
    return entry["body"]


def resource_catalog() -> List[Dict[str, str]]:
    """Entry list for resources/list — one resource per topic."""
    return [
        {
            "uri": f"tomlabs://help/{t}",
            "name": f"help:{t}",
            "title": TOPICS[t]["title"],
            "description": f"Tom Labs help topic — {TOPICS[t]['title']}",
            "mimeType": "text/markdown",
        }
        for t in TOPICS
    ]
