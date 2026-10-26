"""MCP connection tracking: ASGI middleware marking SSE streams connected/disconnected."""
import asyncio
import logging
from datetime import datetime

from src.mcp.auth import get_auth
from src.mcp.db import get_db

logger = logging.getLogger(__name__)

_CONNECTION_CACHE = {}
_CONNECTION_LOCK = None


def get_connection_lock() -> asyncio.Lock:
    global _CONNECTION_LOCK
    if _CONNECTION_LOCK is None:
        _CONNECTION_LOCK = asyncio.Lock()
    return _CONNECTION_LOCK


async def _bump_connection(client_id: str, delta: int):
    """Track live SSE stream count per client in mcp_connections collection."""
    if not client_id:
        return
    try:
        _CONNECTION_CACHE[client_id] = _CONNECTION_CACHE.get(client_id, 0) + delta
        count = _CONNECTION_CACHE[client_id]
        db = get_db()
        if count <= 0:
            db.mcp_connections.update_one(
                {"client_id": client_id},
                {"$set": {"connected": False, "streams": 0, "disconnected_at": datetime.utcnow()}},
                upsert=True,
            )
        else:
            db.mcp_connections.update_one(
                {"client_id": client_id},
                {"$set": {"connected": True, "streams": count, "last_seen_at": datetime.utcnow()}},
                upsert=True,
            )
    except Exception as e:
        logger.warning(f"Connection tracking failed: {e}")


async def _heartbeat_connection(client_id: str):
    """Update last_seen_at for active connection (heartbeat)."""
    if not client_id:
        return
    try:
        db = get_db()
        db.mcp_connections.update_one(
            {"client_id": client_id, "connected": True},
            {"$set": {"last_seen_at": datetime.utcnow()}},
        )
    except Exception as e:
        logger.debug(f"Heartbeat update failed: {e}")


class MCPConnectionTrackingMiddleware:
    """
    ASGI middleware: marks a client as connected while it has an open
    StreamableHTTP SSE stream, and disconnected when the stream closes.
    """

    def __init__(self, app):
        self.app = app

    async def __call__(self, scope, receive, send):
        if scope["type"] != "http":
            await self.app(scope, receive, send)
            return

        path = scope.get("path", "").rstrip("/")
        headers = {}
        for k, v in scope.get("headers") or []:
            try:
                headers[k.decode("latin-1").lower()] = v.decode("latin-1")
            except Exception:
                pass

        accept = headers.get("accept", "")
        is_sse = "text/event-stream" in accept

        client_id = ""
        if is_sse and path == "/mcp":
            auth = headers.get("authorization", "")
            if auth.lower().startswith("bearer "):
                try:
                    info = get_auth().validate_bearer_token(auth[7:].strip())
                    client_id = info.get("client_id") or ""
                except Exception as e:
                    logger.debug(f"Connection auth failed: {e}")

        opened = False
        lock = get_connection_lock()

        async def tracked_receive():
            nonlocal opened
            msg = await receive()
            if client_id and opened and msg.get("type") == "http.disconnect":
                opened = False
                logger.info(f"SSE disconnect for client {client_id}")
                async with lock:
                    await _bump_connection(client_id, -1)
            return msg

        async def tracked_send(message):
            nonlocal opened
            if client_id and message.get("type") == "http.response.start" and not opened:
                opened = True
                logger.info(f"SSE connect for client {client_id}")
                async with lock:
                    await _bump_connection(client_id, +1)
            elif client_id and opened and message.get("type") == "http.response.body":
                # Heartbeat: update last_seen_at on every SSE message
                await _heartbeat_connection(client_id)
                if not message.get("more_body"):
                    opened = False
                    logger.info(f"SSE close for client {client_id}")
                    async with lock:
                        await _bump_connection(client_id, -1)
            await send(message)

        try:
            await self.app(scope, tracked_receive, tracked_send)
        finally:
            if client_id and opened:
                opened = False
                async with lock:
                    await _bump_connection(client_id, -1)
