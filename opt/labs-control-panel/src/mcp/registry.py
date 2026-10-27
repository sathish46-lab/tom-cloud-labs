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
    "guides": "Guides",
    "discovery": "Discovery",
    "storage": "Storage",
    "lab_mcp": "Lab MCP servers",
    "plan": "Build & connect",
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

    # ── guides ── (parity set)
    _t("currency_guide",      "guides", "read"),
    _t("evaluate_guide",      "guides", "read"),
    _t("competitions_guide",  "guides", "read"),
    _t("learning_paths_guide","guides", "read"),
    _t("community_guide",     "guides", "read"),
    _t("explain_my_credits",  "guides", "read"),
    _t("my_mcp_settings",     "guides", "read"),
    _t("set_my_mcp_setting",  "guides", "write"),

    # ── discovery ── (parity set)
    _t("get_service_info",  "discovery", "read"),
    _t("get_type_info",     "discovery", "read"),
    _t("execute_request",   "discovery", "destructive"),
    _t("plan_my_build",     "plan", "read"),
    _t("connect_guide",     "plan", "read"),

    # ── lifecycle ──
    _t("lab_status",        "lifecycle", "read"),
    _t("lab_dashboard",     "lifecycle", "read"),
    _t("list_tasks",        "lifecycle", "read"),
    _t("lab_info",          "lifecycle", "read"),
    _t("lab_logs",          "lifecycle", "read"),
    _t("deploy_progress", "lifecycle", "read", enabled=False),
    _t("code_status", "lifecycle", "read", enabled=False),
    _t("wait_for_deploy",   "lifecycle", "read"),
    _t("test_auto_login",   "lifecycle", "write"),        # renamed from test_autologin
    _t("login_recon",       "lifecycle", "write"),
    _t("configure_lab_routing", "lifecycle", "write"),
    _t("configure_lab_runtime", "lifecycle", "write"),
    _t("set_lab_password",  "lifecycle", "write"),
    _t("lab_startup_script","lifecycle", "write"),
    _t("lab_error_page",    "lifecycle", "write"),
    _t("scale_lab",         "lifecycle", "write"),
    _t("scale_policy",      "lifecycle", "write"),
    _t("deploy_lab",        "lifecycle", "write"),
    _t("stop_lab",          "lifecycle", "destructive"),
    _t("start_lab", "lifecycle", "write", enabled=False),
    _t("pause_lab",         "lifecycle", "write"),
    _t("resume_lab",        "lifecycle", "write"),
    _t("renew_lab",         "lifecycle", "write"),
    _t("ensure_codeserver", "lifecycle", "write", enabled=False),   # Tom-only extra
    _t("run_startup_script", "lifecycle", "write"),                # Tom Labs canonical name
    _t("run_lab_startup_script", "lifecycle", "write", enabled=False),  # Tom-only alias
    _t("apply_preferences", "lifecycle", "write", enabled=False),
    _t("verify_restart",    "lifecycle", "write"),
    _t("terminate_lab",     "lifecycle", "destructive"),
    _t("rename_lab",        "lifecycle", "write"),

    # ── workspace ──
    _t("workspace_preferences", "workspace", "read", enabled=False),
    _t("read_lab_file",              "workspace", "read"),
    _t("workspace_pin", "workspace", "write", enabled=False),
    _t("workspace_unpin", "workspace", "write", enabled=False),
    _t("apply_workspace_preferences", "workspace", "write", enabled=False),
    _t("write_lab_file",             "workspace", "write"),
    _t("edit_lab_file",              "workspace", "write"),
    _t("lab_workspace",              "workspace", "read"),
    _t("open_workspace",             "workspace", "write"),
    _t("workspace_info",             "workspace", "read"),
    _t("close_workspace",            "workspace", "write"),
    _t("list_projects",              "workspace", "read"),
    _t("write_lab_files",            "workspace", "write"),

    # ── files ──
    _t("list_lab_files",       "files", "read"),
    _t("glob_lab_files",       "files", "read"),
    _t("grep_lab_files",       "files", "read"),
    _t("lab_file_tree",        "files", "read"),
    _t("download_lab_file",    "files", "read"),
    _t("upload_lab_file", "files", "write", enabled=False),
    _t("move_lab_file",        "files", "destructive"),
    _t("create_lab_directory", "files", "write"),
    _t("delete_lab_path",      "files", "destructive"),
    _t("delete_lab_file", "files", "destructive", enabled=False),

    # ── processes ──
    _t("list_lab_processes", "processes", "read"),
    _t("wait_for_process",   "processes", "read"),
    _t("lab_process_logs",   "processes", "read"),
    _t("lab_stats",          "processes", "read"),
        _t("storage_usage",     "storage", "read"),
    _t("storage_space",     "storage", "read"),
    _t("lab_probe",         "processes", "write"),        # renamed from probe_lab
    _t("run_command",        "processes", "destructive"),
    _t("start_lab_process",  "processes", "write"),
    _t("stop_lab_process",   "processes", "destructive"),

    # ── networks ──
        # ── per-lab MCP servers (parity set: full family) ──
    _t("lab_mcp_servers",       "lab_mcp", "read"),
    _t("lab_mcp_tools",         "lab_mcp", "read"),
    _t("lab_mcp_call",          "lab_mcp", "destructive"),
    _t("lab_mcp_status",        "lab_mcp", "read"),
    _t("lab_mcp_add",           "lab_mcp", "write"),
    _t("lab_mcp_import",        "lab_mcp", "write"),
    _t("register_lab_mcp",      "lab_mcp", "write"),
    _t("lab_mcp_remove",        "lab_mcp", "destructive"),
    _t("lab_mcp_config_set",    "lab_mcp", "write"),
    _t("lab_mcp_config_remove", "lab_mcp", "destructive"),
    _t("lab_mcp_secrets",       "lab_mcp", "write"),
    _t("lab_mcp_test",          "lab_mcp", "write"),
    _t("lab_mcp_restart",       "lab_mcp", "write"),
    _t("set_lab_mcp_enabled",   "lab_mcp", "write"),
    _t("set_lab_mcp_network",   "lab_mcp", "write"),
    _t("lab_mcp_expose",        "lab_mcp", "write"),
    _t("remove_lab_mcp",        "lab_mcp", "destructive"),   # Tom Labs alias of lab_mcp_remove
    # ── object storage (parity set; refusals on this install) ──
    _t("prepare_upload",    "storage", "write"),
    _t("upload_status",     "storage", "read"),
    _t("cancel_upload",     "storage", "write"),
    _t("prepare_download",  "storage", "read"),

    # ── VPN / WireGuard devices (parity set) ── aliases below
    _t("networks",              "networks", "read", enabled=False),          # renamed -> list_networks
    _t("network_devices",       "networks", "read", enabled=False),          # folded into list_devices
    _t("wireguard_config",      "networks", "read", enabled=False),          # folded into device_config
    _t("list_networks",         "networks", "read"),
    _t("list_devices",          "networks", "read"),
    _t("device_config",         "networks", "read"),
    _t("lab_wg_exposure",       "networks", "read"),
    _t("add_device",            "networks", "write"),
    _t("delete_device",         "networks", "destructive"),
    _t("share_network",         "networks", "write"),
    _t("unshare_network",       "networks", "destructive"),
    _t("set_default_networks",  "networks", "write"),
    _t("set_lab_wg_exposure",       "networks", "write"),
    _t("set_lab_wg_exposure_members", "networks", "write"),
    _t("create_network",        "networks", "write"),
    _t("delete_network",        "networks", "destructive"),
    _t("add_network_device",    "networks", "write", enabled=False),         # folded into add_device
    _t("remove_network_device", "networks", "destructive", enabled=False),   # folded into delete_device

    # ── domains ──
    _t("available_domains", "domains", "read", enabled=False),
    _t("list_domains",       "domains", "read"),
    _t("domain_status",      "domains", "read"),
    _t("connect_info",       "domains", "read"),
    _t("domain_ssl", "domains", "read", enabled=False),
    _t("domain_error_pages", "domains", "read", enabled=False),
    _t("register_domain", "domains", "write", enabled=False),
    _t("claim_tom_domain", "domains", "write", enabled=False),
    _t("add_domain",         "domains", "write"),
    _t("verify_domain", "domains", "write", enabled=False),
    _t("remove_domain", "domains", "destructive", enabled=False),
    _t("delete_domain",      "domains", "destructive"),

    # ── services ──
    _t("databases",                "services", "read", enabled=False),  # Tom Labs semantics -> list_databases
    _t("list_databases",           "services", "read"),
    _t("list_services",            "services", "read"),
    _t("service_connect_info",     "services", "read"),
    _t("service_credentials",       "services", "read"),
    _t("service_users", "services", "read", enabled=False),
    _t("create_database",           "services", "write"),
    _t("create_service_credential", "services", "write", enabled=False),
    _t("add_service_user",          "services", "write"),
    _t("delete_database",           "services", "destructive"),
    _t("delete_service_credential", "services", "destructive", enabled=False),
    _t("remove_service_user",       "services", "destructive"),

    # ── ssh ──
    _t("ssh_keys",           "ssh", "read", enabled=False),                 # renamed -> list_ssh_keys
    _t("list_ssh_keys",      "ssh", "read"),
    _t("add_ssh_key",        "ssh", "write"),
    _t("enable_ssh_key",     "ssh", "write", enabled=False),                # renamed -> set_ssh_key_enabled
    _t("set_ssh_key_enabled","ssh", "write"),
    _t("delete_ssh_key",     "ssh", "destructive"),

    # ── ssl ──
    _t("list_ssl_certificates", "ssl", "read"),
    _t("ssl_troubleshoot",      "ssl", "read"),
    _t("request_ssl_reissue",   "ssl", "write"),
    _t("cancel_ssl_request",    "ssl", "write"),
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
