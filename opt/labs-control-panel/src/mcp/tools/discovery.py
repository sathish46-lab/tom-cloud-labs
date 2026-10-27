"""Discovery trio — get_service_info / get_type_info / execute_request.

get_service_info ranks registry entries against a plain-words query.
get_type_info returns a JSON-schema view of one operation derived from its
registered signature. execute_request runs an operation through the same
loader-guarded function that MCP tools/call uses, so permission checks and
refusals are identical.
"""

import math
import textwrap
from typing import Any, Optional

from mcp.server.fastmcp import Context

from src.mcp.deps import get_current_user
from src.mcp.registry import TOOLS
from src.mcp import loader as _loader

CLASS_TEXT = {
    "read": "safe to call without asking the user",
    "write": "changes something; do it when the user asked for it. write (requires_confirm: confirm with the user first): an overwrite or reduction that execute_request runs only with confirm=true",
    "destructive": "deletes, stops or overwrites; confirm with the user first (always requires_confirm)",
}

_STOP = {"a", "an", "the", "my", "our", "for", "to", "of", "and", "or", "in", "on", "is", "are", "it", "with", "how", "what", "which", "you", "your"}


def _visible_tools(user) -> dict:
    specs = dict(TOOLS)
    if not user.get("role") == "superuser":
        specs = {n: s for n, s in specs.items() if s.tier != "admin"}
    return {n: s for n, s in specs.items() if s.enabled}


def _func(spec):
    import importlib
    mod = importlib.import_module(f"src.mcp.tools.{spec.module}")
    fn = getattr(mod, spec.func, None)
    if fn is None:
        return None
    g = _loader._guard
    return g(fn, spec)


def _schema_from_fn(spec) -> dict:
    import inspect
    fn = _func(spec)
    if fn is None:
        return {"type": "object", "properties": {}, "additionalProperties": False}
    sig = inspect.signature(fn)
    props, required = {}, []
    doc = (fn.__doc__ or spec.purpose or "").strip()
    for name, param in sig.parameters.items():
        if name in ("ctx", "self") or param.kind is inspect.Parameter.VAR_POSITIONAL:
            continue
        ann = param.annotation
        if ann is Context or getattr(ann, "__name__", "") == "Context":
            continue
        p: dict = {}
        if ann == int:
            p["type"] = "integer"
        elif ann == bool:
            p["type"] = "boolean"
        elif str(ann).startswith("Optional"):
            p["type"] = "string"
        else:
            p["type"] = "string"
        p["description"] = f"Argument '{name}' of {spec.name}."
        if param.default is inspect.Parameter.empty:
            required.append(name)
        props[name] = p
    schema = {"type": "object", "properties": props, "additionalProperties": False}
    if required:
        schema["required"] = required
    return schema


def _summary_of(spec) -> str:
    import importlib, inspect
    fn = _func(spec)
    if fn is None:
        return spec.purpose
    doc = textwrap.dedent(fn.__doc__ or "").strip()
    return spec.purpose or doc.split("\n")[0]


def _example(spec, schema: dict) -> str:
    fn_args = []
    for name, p in schema.get("properties", {}).items():
        if name in schema.get("required", []):
            fn_args.append(f'{name}="{p["type"] and "<{type}>" and name}"')
    if not fn_args:
        return f"{spec.name}()"
    return f'{spec.name}({", ".join(fn_args)})'


def _tokens(text: str):
    return {w for w in text.lower().replace("_", " ").replace("-", " ").split() if len(w) > 2 and w not in _STOP}


async def get_service_info(ctx: Context, query: Optional[str] = None, limit: int = 10) -> dict[str, Any]:
    """Step 1 of discover -> plan -> execute: find the operation for a task.

    With a `query`, the operations that fit it best, each with its class
    (read = safe to call; write; destructive = confirm with the user first)
    and an example call. Without a query, list every service (resource family) and its operations. Read-only.
    """
    user = await get_current_user(ctx)
    if limit is None:
        limit = 10
    try:
        limit = max(1, min(int(limit), 25))
    except (TypeError, ValueError):
        limit = 10
    specs = _visible_tools(user)
    if not query:
        families: dict[str, list] = {}
        for name, spec in sorted(specs.items()):
            fam = {"account": "account", "guides": "assistant services",
                   "lifecycle": "labs (runtime)", "workspace": "lab workspace",
                   "files": "lab files", "processes": "lab processes",
                   "networks": "networks", "domains": "domains & HTTPS",
                   "services": "databases", "ssh": "SSH keys", "ssl": "certificates",
                   "admin": "administration"}.get(spec.module, spec.module)
            families.setdefault(fam, []).append({"operation": name, "class": spec.cls})
        return {"services": [{"service": f, "operations": ops, "count": len(ops)} for f, ops in sorted(families.items())],
                "next": "Name a task; get_service_info(query='<task>') returns the best operations with examples."}
    q = _tokens(query)
    scored = []
    for name, spec in specs.items():
        text = " ".join([name, *_tokens(_summary_of(spec))])
        overlap = len(q & set(text.split()))
        prefix_bonus = sum(1 for t in q if t in name.replace("_", " ").replace("-", " "))
        score = overlap * 2 + prefix_bonus * 3
        if score > 0:
            scored.append((score, name, spec))
    scored.sort(key=lambda x: (-x[0], x[1]))
    matches = []
    for _, name, spec in scored[:limit]:
        schema = _schema_from_fn(spec)
        matches.append({
            "operation": name,
            "title": _summary_of(spec).split(".")[0][:80],
            "summary": _summary_of(spec)[:220],
            "class": spec.cls,
            "requires_confirm": bool(spec.confirm or spec.cls == "destructive"),
            "example": _example(spec, schema),
        })
    return {"query": query, "matches": matches,
            "next": "get_type_info(operation=\"<operation>\") for its arguments, then execute_request(operation, args)." if matches
            else "No operation matched. Try different words, or name the resource (lab, domain, database, …)."}


async def get_type_info(ctx: Context, operation: str) -> dict[str, Any]:
    """Step 2 of discover -> plan -> execute: everything needed to run one operation.

    Returns its input schema (the exact argument values), output schema,
    class (read / write / destructive), whether execute_request needs
    confirm=true, an example call, and what must be true first. Read-only.
    """
    user = await get_current_user(ctx)
    spec = _visible_tools(user).get(operation)
    if spec is None:
        return {"operation": operation, "found": False,
                "suggestion": "Not an operation you can use. get_service_info(query='...') lists fits; check the exact spelling."}
    schema = _schema_from_fn(spec)
    destructive = spec.cls == "destructive"
    return {
        "operation": operation,
        "found": True,
        "title": _summary_of(spec).split(".")[0][:80],
        "class": spec.cls,
        "requires_confirm": bool(destructive),
        "use_when": _summary_of(spec)[:200],
        "summary": _summary_of(spec)[:300],
        "input_schema": schema,
        "output_schema": {"type": "object", "additionalProperties": True},
        "example": _example(spec, schema),
        "next_actions": [],
        "prerequisites": [],
        "classes": CLASS_TEXT,
        "how_to_run": ('execute_request(operation="' + operation + '", args={...}). Put the operation\'s own arguments (input_schema) inside `args`, including one named request_id; the top-level request_id is only an optional idempotency key.'),
        "help": f'help(topic="{operation}") for the full guide',
    }


async def execute_request(ctx: Context, operation: str, args: Optional[dict] = None,
                          confirm: bool = False, request_id: Optional[str] = None) -> dict[str, Any]:
    """Step 3 of discover -> plan -> execute: run one operation and return its result.

    Performs the operation exactly as calling it directly would: the same
    permission checks, the same refusals, the same result. A destructive
    operation runs only with confirm=true: confirm with the user before
    setting it. A refusal is the answer; do not retry.
    """
    user = await get_current_user(ctx)
    spec = _visible_tools(user).get(operation)
    if spec is None:
        return {"success": False, "error": f"Operation '{operation}' does not exist or is not available to you. Use get_service_info(query='...') to find fits."}
    if (spec.cls == "destructive" or spec.confirm) and not confirm:
        return {"success": False, "error": f"'{operation}' is a destructive operation; set confirm only after the user agreed. Nothing was run."}
    args = args or {}
    if request_id:
        args.setdefault("request_id", request_id)
    schema = _schema_from_fn(spec)
    properties = schema.get("properties", {})
    for key in list(args.keys()):
        if key not in properties:
            return {"success": False, "error": f"{operation}: unknown argument '{key}'. Allowed: {', '.join(properties) or '(none)'}."}
    for required in schema.get("required", []):
        if required not in args:
            return {"success": False, "error": f"{operation}: missing required argument '{required}'. get_type_info(operation='{operation}') shows the schema."}
    fn = _func(spec)
    if fn is None:
        return {"success": False, "error": f"Implementation for '{operation}' is missing; nothing was run."}
    try:
        result = await fn(ctx, **args)
    except TypeError as exc:
        return {"success": False, "error": f"{operation}: {exc}"}
    if isinstance(result, dict) and "success" not in result:
        result["success"] = True
    if request_id:
        result["request_id"] = request_id
    return result
