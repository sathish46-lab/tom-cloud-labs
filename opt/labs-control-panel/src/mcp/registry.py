"""
Tool Registry — the single source of truth for every MCP tool.

    ★ ENABLE / DISABLE a tool .......... set enabled=False (or comment the entry line out)
    ★ MOVE a tool between tiers ......... tier="user"  <->  tier="admin"
    ★ CHANGE a tool's security class .... cls="read" | "write" | "destructive"

Nothing else registers tools. src/mcp/loader.py iterates this dict and
calls FastMCP.add_tool() only for entries that are enabled — a function
that exists in tools/ but has no entry here is NEVER served.

Security contract:
- tier="user"   → any authenticated caller (feature_gate still applies).
- tier="admin"  → callers whose users.role == "superuser", enforced by the
                  loader BEFORE the tool body runs, fail-closed.
- cls drives the MCP ToolAnnotations every client sees:
      read         readOnlyHint=True,  destructiveHint=False, idempotentHint=True
      write        readOnlyHint=False, destructiveHint=False, idempotentHint=False
      destructive  readOnlyHint=False, destructiveHint=True,  idempotentHint=False
                  + confirm=True refusal (enforced by the loader).
- Purpose strings feed the Discovery UI and (later) help search.
"""

from dataclasses import dataclass
from typing import Dict, List, Optional


VALID_TIERS = ("user", "admin")
VALID_CLASSES = ("read", "write", "destructive")

CATEGORY_BY_MODULE = {
    "account": "Account",
    "lifecycle": "Lifecycle",
    "workspace": "Workspace",
    "files": "Files",
    "processes": "Processes",
    "networks": "Networks",
    "domains": "Domains",
    "services": "Services",
    "ssh": "SSH",
    "ssl": "SSL",
    "admin": "Admin",
}


@dataclass(frozen=True)
class ToolSpec:
    name: str                 # wire name as exposed over MCP (must match func)
    module: str               # module under src/mcp/tools/
    func: str                 # function name inside that module
    tier: str = "user"        # "user" | "admin"
    cls: str = "read"         # "read" | "write" | "destructive"
    enabled: bool = True      # False => never registered, never callable
    idempotent: Optional[bool] = None   # override the class default
    purpose: str = ""         # one-liner for Discovery UI / help (falls back to docstring)
    confirm: bool = False     # destructive tools: caller must pass confirm=True

    def __post_init__(self):
        if self.tier not in VALID_TIERS:
            raise ValueError(f"{self.name}: invalid tier {self.tier!r}")
        if self.cls not in VALID_CLASSES:
            raise ValueError(f"{self.name}: invalid cls {self.cls!r}")


def _t(name: str, module: str, cls: str, **kw) -> ToolSpec:
    """Shorthand: func always equals the wire name in this codebase."""
    return ToolSpec(name=name, module=module, func=name, cls=cls,
                    confirm=kw.pop("confirm", cls == "destructive"), **kw)


# ─── The manifest ───────────────────────────────────────────────────────────
# To disable a tool: enabled=False  (or comment out the whole entry).
# To move it to admins only: tier="admin".

TOOLS: Dict[str, ToolSpec] = {spec.name: spec for spec in [
    # ── account ──
    _t("whoami",            "account",  "read"),
    _t("list_labs",         "account",  "read"),
    _t("my_mcp_clients",    "account",  "read"),
    _t("my_history",        "account",  "read"),
    _t("help",              "account",  "read"),
    _t("lookup",            "account",  "read"),

    # ── lifecycle ──
    _t("lab_status",        "lifecycle", "read"),
    _t("lab_dashboard",     "lifecycle", "read"),
    _t("list_tasks",        "lifecycle", "read"),
    _t("lab_info",          "lifecycle", "read"),
    _t("lab_logs",          "lifecycle", "read"),
    _t("deploy_progress",   "lifecycle", "read"),
    _t("code_status",       "lifecycle", "read"),
    _t("wait_for_deploy",   "lifecycle", "read"),
    _t("test_autologin",    "lifecycle", "read"),
    _t("deploy_lab",        "lifecycle", "write"),
    _t("stop_lab",          "lifecycle", "write"),
    _t("start_lab",         "lifecycle", "write"),
    _t("pause_lab",         "lifecycle", "write"),
    _t("resume_lab",        "lifecycle", "write"),
    _t("renew_lab",         "lifecycle", "write"),
    _t("ensure_codeserver", "lifecycle", "write"),
    _t("run_startup_script","lifecycle", "write"),
    _t("apply_preferences", "lifecycle", "write"),
    _t("verify_restart",    "lifecycle", "write"),
    _t("terminate_lab",     "lifecycle", "destructive"),

    # ── workspace ──
    _t("workspace_preferences",      "workspace", "read"),
    _t("read_lab_file",              "workspace", "read"),
    _t("workspace_pin",              "workspace", "write"),
    _t("workspace_unpin",            "workspace", "write"),
    _t("apply_workspace_preferences","workspace", "write"),
    _t("write_lab_file",             "workspace", "write"),
    _t("edit_lab_file",              "workspace", "write"),
    _t("lab_workspace",              "workspace", "read"),
    _t("list_projects",              "workspace", "read"),
    _t("write_lab_files",            "workspace", "write"),

    # ── files ──
    _t("list_lab_files",       "files", "read"),
    _t("glob_lab_files",       "files", "read"),
    _t("grep_lab_files",       "files", "read"),
    _t("lab_file_tree",        "files", "read"),
    _t("download_lab_file",    "files", "read"),
    _t("upload_lab_file",      "files", "write"),
    _t("move_lab_file",        "files", "write"),
    _t("create_lab_directory", "files", "write"),
    _t("delete_lab_path",      "files", "destructive"),
    _t("delete_lab_file",      "files", "destructive"),

    # ── processes ──
    _t("list_lab_processes", "processes", "read"),
    _t("wait_for_process",   "processes", "read"),
    _t("lab_process_logs",   "processes", "read"),
    _t("lab_stats",          "processes", "read"),
    _t("storage_usage",      "processes", "read"),
    _t("probe_lab",          "processes", "read"),
    _t("run_command",        "processes", "write"),
    _t("start_lab_process",  "processes", "write"),
    _t("stop_lab_process",   "processes", "write"),

    # ── networks ──
    _t("networks",             "networks", "read"),
    _t("network_devices",      "networks", "read"),
    _t("wireguard_config",     "networks", "read"),
    _t("create_network",       "networks", "write"),
    _t("add_network_device",   "networks", "write"),
    _t("delete_network",       "networks", "destructive"),
    _t("remove_network_device","networks", "destructive"),

    # ── domains ──
    _t("available_domains",  "domains", "read"),
    _t("list_domains",       "domains", "read"),
    _t("domain_status",      "domains", "read"),
    _t("connect_info",       "domains", "read"),
    _t("domain_ssl",         "domains", "read"),
    _t("domain_error_pages", "domains", "read"),
    _t("register_domain",    "domains", "write"),
    _t("claim_tom_domain",   "domains", "write"),
    _t("add_domain",         "domains", "write"),
    _t("verify_domain",      "domains", "write"),
    _t("remove_domain",      "domains", "destructive"),
    _t("delete_domain",      "domains", "destructive"),

    # ── services ──
    _t("databases",                 "services", "read"),
    _t("service_credentials",       "services", "read"),
    _t("service_users",             "services", "read"),
    _t("create_database",           "services", "write"),
    _t("create_service_credential", "services", "write"),
    _t("add_service_user",          "services", "write"),
    _t("delete_database",           "services", "destructive"),
    _t("delete_service_credential", "services", "destructive"),
    _t("remove_service_user",       "services", "destructive"),

    # ── ssh ──
    _t("ssh_keys",      "ssh", "read"),
    _t("add_ssh_key",   "ssh", "write"),
    _t("enable_ssh_key","ssh", "write"),
    _t("delete_ssh_key","ssh", "destructive"),

    # ── ssl ──
    _t("list_ssl_certificates", "ssl", "read"),
    _t("ssl_troubleshoot",      "ssl", "read"),
]}


def enabled_specs() -> List[ToolSpec]:
    """Specs that should be registered (enabled only)."""
    return [s for s in TOOLS.values() if s.enabled]


def catalog() -> List[dict]:
    """Discovery-UI view of every enabled tool: name, description, category, tier, cls.

    Imports each tool module lazily to read its docstring when no explicit
    purpose is set, so the UI can never drift from the real functions.
    """
    import importlib

    out: List[dict] = []
    for spec in enabled_specs():
        description = spec.purpose
        if not description:
            try:
                mod = importlib.import_module(f"src.mcp.tools.{spec.module}")
                fn = getattr(mod, spec.func)
                doc = (fn.__doc__ or "").strip()
                description = doc.split("\n", 1)[0] if doc else spec.name
            except Exception:
                description = spec.name
        out.append({
            "name": spec.name,
            "description": description,
            "category": CATEGORY_BY_MODULE.get(spec.module, "Other"),
            "tier": spec.tier,
            "cls": spec.cls,
        })
    return out


def validate_registry() -> List[str]:
    """Structural integrity checks — returns a list of problems (empty = ok)."""
    problems: List[str] = []
    seen = set()
    for key, spec in TOOLS.items():
        if key != spec.name:
            problems.append(f"dict key {key!r} != spec.name {spec.name!r}")
        if spec.name in seen:
            problems.append(f"duplicate tool {spec.name!r}")
        seen.add(spec.name)
        if spec.module not in CATEGORY_BY_MODULE:
            problems.append(f"{spec.name}: unknown module {spec.module!r}")
        if spec.func != spec.name:
            problems.append(f"{spec.name}: func {spec.func!r} must equal wire name")
    return problems
