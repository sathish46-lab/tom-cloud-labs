"""
MCP Settings — environment-derived constants.

INTERNAL ONLY: no secrets live here; token/DB secrets stay in src.config
(env.json inside the container). Nothing in this module may be returned
by a tool response.
"""

import os

# Public base URL used in OAuth metadata and the Discovery UI.
MCP_PUBLIC_URL = os.environ.get("MCP_PUBLIC_URL", "http://dev.tomweb.in:9080")

# Local HTTP port for the MCP server process.
MCP_SERVER_PORT = int(os.environ.get("MCP_SERVER_PORT", "8099"))

# OAuth 2.1 + PKCE lifetimes (seconds)
OAUTH_AUTH_CODE_TTL = 600  # 10 minutes
OAUTH_ACCESS_TOKEN_TTL = 3600  # 1 hour
OAUTH_REFRESH_TOKEN_TTL = 2592000  # 30 days
PKCE_CODE_VERIFIER_MIN_LEN = 43
PKCE_CODE_VERIFIER_MAX_LEN = 128
