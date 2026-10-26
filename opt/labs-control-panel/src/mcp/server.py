#!/usr/bin/env python3
"""
Tom Labs MCP Server — bootstrap.

This file only builds the FastMCP app and wires the pieces together:

    settings.py / db.py / deps.py    internal contracts (no secrets exposed)
    registry.py                      THE tool manifest (enable/disable/tier/cls)
    loader.py                        the ONLY place tools are registered
    routes/                          OAuth 2.1 + Discovery UI + middleware
    tools/                           tool functions (plain async def)

Security: tool registration is manifest-driven — a function without a
registry entry is never served. DB handles and connection strings never
leave this process.
"""

import os
import sys
import logging
from contextlib import asynccontextmanager
from typing import Any, AsyncIterator, Dict

# Add repo root to path for `from src.mcp...` imports. Append (don't prepend)
# so the installed `mcp` library is NOT shadowed by the local `src/mcp` package.
sys.path.append(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))))

from mcp.server.fastmcp import FastMCP
from mcp.server.auth.settings import AuthSettings
from mcp.server.auth.provider import TokenVerifier, AccessToken

from src.mcp.auth import get_auth, AuthError
from src.mcp.db import get_config, get_db, get_mongo_client, init_mcp_dependencies
from src.mcp.help_content import SERVER_INSTRUCTIONS
from src.mcp.settings import MCP_PUBLIC_URL, MCP_SERVER_PORT
from src.mcp.loader import load_tools
from src.mcp.resources import setup_resources
from src.mcp.routes import mount as mount_routes
from src.mcp.routes.activity import setup_activity_logging
from src.mcp.routes.tracking import MCPConnectionTrackingMiddleware

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)


# ─── Context Management ───

@asynccontextmanager
async def mcp_context(app: FastMCP = None) -> AsyncIterator[Dict[str, Any]]:
    """Provide shared context to all tool calls (initializes auth + ownership)."""
    init_mcp_dependencies()
    yield {
        "config": get_config(),
        "db": get_db(),
        "mongo_client": get_mongo_client(),
    }


class LabsTokenVerifier(TokenVerifier):
    """Validate Bearer tokens against the mcp_tokens collection."""

    async def verify_token(self, token: str) -> AccessToken | None:
        try:
            info = get_auth().validate_bearer_token(token)
            return AccessToken(
                token=token,
                client_id=info.get("client_id", ""),
                scopes=info.get("scopes", ["labs:*"]),
            )
        except AuthError:
            return None


# ─── FastMCP server ───

mcp = FastMCP(
    "Tom Labs MCP Server",
    instructions=SERVER_INSTRUCTIONS,
    lifespan=mcp_context,
    auth=AuthSettings(
        issuer_url=MCP_PUBLIC_URL,
        resource_server_url=f"{MCP_PUBLIC_URL}/mcp",
        required_scopes=["labs:*"],
    ),
    token_verifier=LabsTokenVerifier(),
)


# ─── Wiring (order matters, matches pre-refactor behavior) ───

# 1. HTTP routes: OAuth 2.1 endpoints + Discovery UI (registry-driven catalog)
mount_routes(mcp)

# 2. Tools: manifest-driven registration with security annotations
registered = load_tools(mcp)
logger.info(f"Loaded {registered} tools from registry")

# 3. Resources: static help topics (tomlabs://help/{topic})
setup_resources(mcp)

# 4. Activity logging: wraps the CallToolRequest handler (must come after tools)
setup_activity_logging(mcp)


# ─── Main Entry Point ───

if __name__ == "__main__":
    import uvicorn
    port = MCP_SERVER_PORT

    logger.info(f"Starting Tom Labs MCP Server on port {port}")
    app = MCPConnectionTrackingMiddleware(mcp.streamable_http_app())
    uvicorn.run(app, host="0.0.0.0", port=port)
