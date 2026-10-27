"""
MCP Settings — environment-derived constants.

INTERNAL ONLY: no secrets live here; token/DB secrets stay in src.config
(env.json inside the container). Nothing in this module may be returned
by a tool response.
"""

import os

# Public base URL used in OAuth metadata and the Discovery UI.
MCP_PUBLIC_URL = os.environ.get("MCP_PUBLIC_URL", "https://labs.tomweb.in")

# Public hosts the DNS-rebinding guard accepts for the Host header
# (mirrors server.py; keep both lists in sync).
# Both bare form and :* wildcard are listed on purpose: the SDK matches the
# exact string first, then `host:*` suffix patterns — a bare request (no port)
# or a ported one (proxy) must both pass.
MCP_ALLOWED_HOSTS = [
    h.strip()
    for h in os.environ.get(
        "MCP_ALLOWED_HOSTS",
        "labs.tomweb.in, labs.tomweb.in:*, lab.tomweb.in, lab.tomweb.in:*, "
        "dev.tomweb.in, dev.tomweb.in:*, "
        "127.0.0.1:*, localhost:*, [::1]:*",
    ).split(",")
    if h.strip()
]

# Local HTTP port for the MCP server process.
MCP_SERVER_PORT = int(os.environ.get("MCP_SERVER_PORT", "8099"))

# OAuth 2.1 + PKCE lifetimes (seconds)
OAUTH_AUTH_CODE_TTL = 600  # 10 minutes
OAUTH_ACCESS_TOKEN_TTL = 3600  # 1 hour
OAUTH_REFRESH_TOKEN_TTL = 2592000  # 30 days
PKCE_CODE_VERIFIER_MIN_LEN = 43
PKCE_CODE_VERIFIER_MAX_LEN = 128
