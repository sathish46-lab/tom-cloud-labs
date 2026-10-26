"""MCP activity logging: wraps the CallToolRequest handler to persist every tool call."""
import json
import time
import logging
from datetime import datetime
from typing import Any, Dict

from mcp.server.fastmcp import FastMCP
from mcp.types import CallToolRequest

from src.mcp.auth import get_auth, AuthError
from src.mcp.db import get_db

logger = logging.getLogger(__name__)


def _truncate(value: Any, max_chars: int = 4000) -> Any:
    """Truncate a value to keep stored activity documents small."""
    if isinstance(value, (dict, list)):
        s = json.dumps(value, default=str)
        if len(s) > max_chars:
            try:
                return json.loads(s[:max_chars] + '..."(truncated)"')
            except Exception:
                return s[:max_chars]
        return value
    s = str(value)
    if len(s) > max_chars:
        return s[:max_chars]
    # Try to parse string as JSON (handles nested escaped JSON)
    if isinstance(s, str) and s.strip().startswith('{'):
        try:
            parsed = json.loads(s)
            return parsed
        except Exception:
            pass
    return value


def _extract_identity(request) -> Dict[str, Any]:
    """Extract user/client identity from the request's Authorization header."""
    identity = {"user_id": None, "username": "", "email": "", "client_id": ""}
    try:
        auth_header = (request.headers.get("authorization") or "").strip()
        if not auth_header.lower().startswith("bearer "):
            return identity
        token = auth_header.split(" ", 1)[1].strip()
        info = get_auth().validate_bearer_token(token)
        identity["user_id"] = info.get("user_id")
        identity["username"] = info.get("username", "")
        identity["email"] = info.get("email", "")
        identity["client_id"] = info.get("client_id", "")
    except AuthError:
        pass
    except Exception as e:
        logger.warning(f"Activity identity extraction failed: {e}")
    return identity


async def _log_activity(identity, tool, request_args, response, duration_ms, status, error=None):
    """Persist a single MCP tool call into the mcp_activity collection."""
    try:
        doc = {
            "client_id": identity["client_id"],
            "user_id": identity["user_id"],
            "username": identity["username"],
            "email": identity["email"],
            "tool": tool,
            "request": _truncate(request_args),
            "response": _truncate(response),
            "status": status,
            "error": _truncate(error) if error else None,
            "duration_ms": int(duration_ms),
            "created_at": datetime.utcnow(),
        }
        get_db().mcp_activity.insert_one(doc)
    except Exception as e:
        logger.warning(f"Failed to log MCP activity: {e}")


def setup_activity_logging(mcp: FastMCP):
    """Wrap the lowlevel CallToolRequest handler to log every tool call."""
    try:
        lowlevel = mcp._mcp_server
        original = lowlevel.request_handlers.get(CallToolRequest)
        if original is None:
            logger.warning("CallToolRequest handler not found; activity logging disabled")
            return

        async def logging_handler(req: CallToolRequest):
            tool = req.params.name
            request_args = req.params.arguments or {}
            identity = {"user_id": None, "username": "", "email": "", "client_id": ""}
            try:
                identity = _extract_identity(lowlevel.request_context.request)
            except LookupError:
                pass
            except Exception:
                pass

            start = time.monotonic()
            status = "ok"
            error = None
            response = None
            try:
                result = await original(req)
                response = result
                return result
            except Exception as e:
                status = "error"
                error = str(e)
                raise
            finally:
                duration_ms = (time.monotonic() - start) * 1000
                resp_payload = response
                if hasattr(response, "model_dump"):
                    try:
                        resp_payload = response.model_dump(exclude_none=True)
                    except Exception:
                        pass
                await _log_activity(identity, tool, request_args, resp_payload, duration_ms, status, error)

        lowlevel.request_handlers[CallToolRequest] = logging_handler
        logger.info("MCP activity logging enabled")
    except Exception as e:
        logger.warning(f"Failed to enable MCP activity logging: {e}")
