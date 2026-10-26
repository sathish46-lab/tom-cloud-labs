"""
Tool Loader — the ONLY place tools are registered onto the FastMCP server.

Walks src/mcp/registry.TOOLS and, for each enabled entry:
  1. imports the function from src/mcp.tools.<module>,
  2. wraps tier="admin" tools with a fail-closed role guard that runs
     BEFORE the tool body,
  3. registers it with its MCP ToolAnnotations (the security tags clients
     read: readOnlyHint / destructiveHint / idempotentHint).

Disabled entries are never imported and never registered, so they cannot
appear in tools/list nor be called.
"""

import functools
import importlib
import logging
from typing import List

from mcp.server.fastmcp import FastMCP
from mcp.types import ToolAnnotations

from src.mcp.registry import TOOLS, ToolSpec, enabled_specs, validate_registry

logger = logging.getLogger(__name__)


def annotations_for(spec: ToolSpec) -> ToolAnnotations:
    """Map a registry entry to the MCP security tags every client sees."""
    read = spec.cls == "read"
    destructive = spec.cls == "destructive"
    if spec.idempotent is not None:
        idempotent = spec.idempotent
    else:
        idempotent = True if read else False
    return ToolAnnotations(
        readOnlyHint=read,
        destructiveHint=destructive,
        idempotentHint=idempotent,
        openWorldHint=False,
    )


async def _require_admin(spec: ToolSpec, args, kwargs) -> None:
    """Fail-closed admin gate; runs before the tool body.

    Mirrors feature_gate's policy: any failure to resolve the caller's role
    denies access instead of defaulting open.
    """
    from src.mcp.db import get_db
    from src.mcp.deps import AuthError, get_current_user_from_context

    ctx = kwargs.get("ctx")
    if ctx is None:
        for a in args:
            if hasattr(a, "request_context"):
                ctx = a
                break
    if ctx is None:
        raise AuthError("Authentication required: no request context")

    user = await get_current_user_from_context(ctx)
    try:
        doc = get_db().users.find_one(
            {"user_id": user.get("user_id")},
            {"role": 1},
        )
        if not doc:
            doc = get_db().users.find_one({"email": user.get("email")}, {"role": 1})
        role = (doc or {}).get("role", "user")
    except Exception as e:
        logger.warning(f"Admin role lookup failed for user {user.get('user_id')}: {e}")
        raise PermissionError("Unable to verify admin access") from e

    if role != "superuser":
        raise PermissionError(
            f"Tool '{spec.name}' is restricted to administrator accounts"
        )


def _guard(fn, spec: ToolSpec):
    """Wrap admin-tier tools with the role check; other tiers pass through."""
    if spec.tier != "admin":
        return fn

    @functools.wraps(fn)
    async def guarded(*args, **kwargs):
        await _require_admin(spec, args, kwargs)
        return await fn(*args, **kwargs)

    guarded.__wrapped__ = fn  # explicit, in case wraps() semantics change
    return guarded


def load_tools(mcp: FastMCP) -> int:
    """Register every enabled registry entry. Returns how many were registered."""
    problems = validate_registry()
    if problems:
        raise RuntimeError("Tool registry invalid: " + "; ".join(problems))

    registered: List[str] = []
    skipped: List[str] = []
    for spec in TOOLS.values():
        if not spec.enabled:
            skipped.append(spec.name)
            continue
        mod = importlib.import_module(f"src.mcp.tools.{spec.module}")
        try:
            fn = getattr(mod, spec.func)
        except AttributeError as e:
            raise RuntimeError(
                f"Registry entry '{spec.name}' points at missing function "
                f"{spec.module}.{spec.func}"
            ) from e

        mcp.add_tool(
            _guard(fn, spec),
            name=spec.name,
            description=fn.__doc__,
            annotations=annotations_for(spec),
        )
        registered.append(spec.name)

    logger.info(
        f"MCP tools registered: {len(registered)} "
        f"(disabled/skipped: {len(skipped) or 'none'})"
    )
    if skipped:
        logger.info(f"Disabled tools skipped: {', '.join(skipped)}")
    return len(registered)


def tool_names() -> List[str]:
    """Enabled registry names in registration order (for parity checks)."""
    return [s.name for s in enabled_specs()]
