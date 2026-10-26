"""
MongoDB access for the MCP server — INTERNAL ONLY.

Security contract:
- This module is server-internal. Tool callers never see a database handle,
  connection string or query surface; tools return JSON-safe payloads only.
- Connection details come from src.config (env.json, inside the container)
  and must never appear in a tool response or log line.
- Fail-closed: if the role/ownership lookups cannot be completed, access is
  denied rather than defaulted open.
"""

import logging
from typing import Optional

from src.config import Config

logger = logging.getLogger(__name__)

_config: Optional[Config] = None
_mongo_client = None


def get_config() -> Config:
    """Get or create the shared Config instance."""
    global _config
    if _config is None:
        _config = Config()
    return _config


def get_mongo_client():
    """Get or create the shared MongoDB client."""
    global _mongo_client
    if _mongo_client is None:
        import pymongo
        cfg = get_config()
        _mongo_client = pymongo.MongoClient(cfg.mongo_uri)
    return _mongo_client


def get_db():
    """Get the default application database."""
    cfg = get_config()
    return get_mongo_client()[cfg.main_db]


def init_mcp_dependencies() -> None:
    """Initialize auth and ownership singletons on the shared Mongo client.

    Runs once from the FastMCP lifespan so token validation and ownership
    resolution share one connection pool.
    """
    client = get_mongo_client()

    import src.mcp.auth as auth_mod
    from src.mcp.auth import MCPAuth

    import src.mcp.ownership as ownership_mod
    from src.mcp.ownership import OwnershipResolver

    if auth_mod._auth_instance is None:
        auth_mod._auth_instance = MCPAuth(mongo_client=client)
    if ownership_mod._ownership_instance is None:
        ownership_mod._ownership_instance = OwnershipResolver(mongo_client=client)
