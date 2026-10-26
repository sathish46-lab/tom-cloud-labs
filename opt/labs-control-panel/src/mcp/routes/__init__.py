"""MCP HTTP routes: OAuth 2.1 endpoints + Discovery UI."""
from src.mcp.routes.oauth import setup_oauth_routes
from src.mcp.routes.discovery import setup_discovery_routes

def mount(mcp) -> None:
    """Register all HTTP routes on the FastMCP server (order preserved)."""
    setup_oauth_routes(mcp)
    setup_discovery_routes(mcp)
