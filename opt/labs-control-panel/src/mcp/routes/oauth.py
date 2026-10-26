"""OAuth 2.1 + PKCE implementation and HTTP routes (metadata, registration, authorize, token, jwks)."""
import base64
import hashlib
import secrets
from datetime import datetime, timedelta
from typing import List, Optional
from urllib.parse import urlencode

from mcp.server.fastmcp import FastMCP
from starlette.requests import Request
from starlette.responses import HTMLResponse, JSONResponse, RedirectResponse

from src.mcp.db import get_db
from src.mcp.routes.util import escape_html
from src.mcp.settings import (
    MCP_PUBLIC_URL,
    OAUTH_ACCESS_TOKEN_TTL,
    OAUTH_AUTH_CODE_TTL,
    OAUTH_REFRESH_TOKEN_TTL,
    PKCE_CODE_VERIFIER_MAX_LEN,
)


def generate_code_verifier() -> str:
    """Generate PKCE code verifier (43-128 chars)."""
    return secrets.token_urlsafe(32)[:PKCE_CODE_VERIFIER_MAX_LEN]

def generate_code_challenge(verifier: str) -> str:
    """Generate PKCE code challenge (S256)."""
    digest = hashlib.sha256(verifier.encode()).digest()
    return base64.urlsafe_b64encode(digest).decode().rstrip('=')

def verify_pkce(verifier: str, challenge: str) -> bool:
    """Verify PKCE code verifier against challenge."""
    return generate_code_challenge(verifier) == challenge

def generate_token() -> str:
    """Generate secure random token."""
    return secrets.token_urlsafe(32)

def get_base_url(request: Request) -> str:
    """Get base URL from request."""
    return f"{request.url.scheme}://{request.url.netloc}"

async def get_oauth_client(db, client_id: str) -> Optional[dict]:
    """Get OAuth client by ID."""
    return db.mcp_clients.find_one({"client_id": client_id})

async def create_oauth_client(db, client_name: str, redirect_uris: List[str], scopes: List[str] = None) -> dict:
    """Create new OAuth client (dynamic registration)."""
    client_id = f"mcp_{secrets.token_urlsafe(16)}"
    client_secret = secrets.token_urlsafe(32)
    
    client_doc = {
        "client_id": client_id,
        "client_secret": client_secret,
        "client_name": client_name,
        "redirect_uris": redirect_uris,
        "scopes": scopes or ["openid", "profile", "email"],
        "created_at": datetime.utcnow(),
        "updated_at": datetime.utcnow()
    }
    
    db.mcp_clients.insert_one(client_doc)
    return client_doc

async def store_auth_code(db, code: str, client_id: str, user_id: str, redirect_uri: str, 
                          scopes: List[str], code_challenge: str, code_challenge_method: str) -> None:
    """Store authorization code."""
    expires_at = datetime.utcnow() + timedelta(seconds=OAUTH_AUTH_CODE_TTL)
    
    db.mcp_oauth_auth_codes.insert_one({
        "code": code,
        "client_id": client_id,
        "user_id": user_id,
        "redirect_uri": redirect_uri,
        "scopes": scopes,
        "code_challenge": code_challenge,
        "code_challenge_method": code_challenge_method,
        "created_at": datetime.utcnow(),
        "expires_at": expires_at
    })

async def get_and_delete_auth_code(db, code: str) -> Optional[dict]:
    """Get and delete authorization code (single use)."""
    doc = db.mcp_oauth_auth_codes.find_one_and_delete({
        "code": code,
        "expires_at": {"$gt": datetime.utcnow()}
    })
    return doc

async def store_tokens(db, client_id: str, user_id: str, scopes: List[str]) -> dict:
    """Store access and refresh tokens."""
    access_token = f"mcp_{generate_token()}"
    refresh_token = f"mcp_rt_{generate_token()}"
    
    access_hash = hashlib.sha256(access_token.encode()).hexdigest()
    refresh_hash = hashlib.sha256(refresh_token.encode()).hexdigest()
    
    access_expires = datetime.utcnow() + timedelta(seconds=OAUTH_ACCESS_TOKEN_TTL)
    refresh_expires = datetime.utcnow() + timedelta(seconds=OAUTH_REFRESH_TOKEN_TTL)
    
    import bcrypt
    access_enc = bcrypt.hashpw(access_token.encode(), bcrypt.gensalt(12))
    refresh_enc = bcrypt.hashpw(refresh_token.encode(), bcrypt.gensalt(12))
    
    # Store in mcp_tokens (compatible with existing Bearer token auth)
    db.mcp_tokens.insert_one({
        "user_id": user_id,
        "client_id": client_id,
        "access_token_hash": access_hash,
        "access_token_enc": access_enc.decode(),
        "access_expires_at": access_expires,
        "refresh_token_hash": refresh_hash,
        "refresh_token_enc": refresh_enc.decode(),
        "refresh_expires_at": refresh_expires,
        "scopes": scopes,
        "created_at": datetime.utcnow(),
        "revoked": False
    })
    
    return {
        "access_token": access_token,
        "refresh_token": refresh_token,
        "expires_in": OAUTH_ACCESS_TOKEN_TTL,
        "token_type": "Bearer",
        "scope": " ".join(scopes)
    }

async def validate_refresh_token(db, refresh_token: str, client_id: str) -> Optional[dict]:
    """Validate and rotate refresh token."""
    refresh_hash = hashlib.sha256(refresh_token.encode()).hexdigest()
    
    import bcrypt
    doc = db.mcp_tokens.find_one({
        "refresh_token_hash": refresh_hash,
        "client_id": client_id,
        "refresh_expires_at": {"$gt": datetime.utcnow()},
        "revoked": {"$ne": True}
    })
    
    if not doc:
        return None
    
    if not bcrypt.checkpw(refresh_token.encode(), doc["refresh_token_enc"].encode()):
        return None
    
    # Revoke old tokens
    db.mcp_tokens.update_one(
        {"_id": doc["_id"]},
        {"$set": {"revoked": True}}
    )
    
    return {
        "user_id": doc["user_id"],
        "scopes": doc.get("scopes", ["labs:*"])
    }

def setup_oauth_routes(mcp: FastMCP):
    """Setup OAuth routes."""
    
    @mcp.custom_route("/.well-known/oauth-authorization-server", methods=["GET"])
    async def oauth_metadata(request: Request):
        """OAuth 2.0 Authorization Server Metadata (RFC 8414)."""
        base = MCP_PUBLIC_URL
        return JSONResponse({
            "issuer": base,
            "authorization_endpoint": f"{base}/mcp/authorize",
            "token_endpoint": f"{base}/mcp/token",
            "registration_endpoint": f"{base}/mcp/register",
            "jwks_uri": f"{base}/mcp/jwks",
            "scopes_supported": ["openid", "profile", "email"],
            "response_types_supported": ["code"],
            "response_modes_supported": ["query", "fragment"],
            "grant_types_supported": ["authorization_code", "refresh_token"],
            "token_endpoint_auth_methods_supported": ["client_secret_post", "client_secret_basic", "none"],
            "code_challenge_methods_supported": ["S256"],
            "subject_types_supported": ["public"],
            "id_token_signing_alg_values_supported": ["RS256"],
            "service_documentation": f"{base}/mcp",
            "ui_locales_supported": ["en"]
        })
    
    @mcp.custom_route("/.well-known/oauth-protected-resource", methods=["GET"])
    async def protected_resource_metadata(request: Request):
        """OAuth 2.0 Protected Resource Metadata (RFC 9728)."""
        base = MCP_PUBLIC_URL
        return JSONResponse({
            "resource": f"{base}/mcp",
            "authorization_servers": [base],
            "scopes_supported": ["openid", "profile", "email"],
            "bearer_methods_supported": ["header"]
        })
    
    @mcp.custom_route("/mcp/register", methods=["POST"])
    async def oauth_register(request: Request):
        """Dynamic Client Registration (RFC 7591)."""
        db = get_db()
        body = await request.json()
        
        client_name = body.get("client_name", "MCP Client")
        redirect_uris = body.get("redirect_uris", [])
        scopes = body.get("scope", "openid profile email").split()
        
        if not redirect_uris:
            return JSONResponse(
                {"error": "invalid_redirect_uri", "error_description": "redirect_uris required"},
                status_code=400
            )
        
        client = await create_oauth_client(db, client_name, redirect_uris, scopes)
        
        return JSONResponse({
            "client_id": client["client_id"],
            "client_secret": client["client_secret"],
            "client_name": client["client_name"],
            "redirect_uris": client["redirect_uris"],
            "scopes": client["scopes"],
            "client_id_issued_at": int(client["created_at"].timestamp()),
            "client_secret_expires_at": 0
        }, status_code=201)
    
    @mcp.custom_route("/mcp/authorize", methods=["GET"])
    async def oauth_authorize(request: Request):
        """OAuth Authorization Endpoint - shows consent page."""
        db = get_db()
        
        # Extract parameters
        client_id = request.query_params.get("client_id")
        redirect_uri = request.query_params.get("redirect_uri")
        response_type = request.query_params.get("response_type", "code")
        scope_original = request.query_params.get("scope", "labs:*")
        scope = "openid profile email"  # Friendly display scope
        state = request.query_params.get("state")
        code_challenge = request.query_params.get("code_challenge")
        code_challenge_method = request.query_params.get("code_challenge_method", "S256")
        resource = request.query_params.get("resource")
        
        # Validate client
        client = await get_oauth_client(db, client_id)
        if not client:
            return HTMLResponse(f"""
                <html><body>
                <h2>Error: Invalid Client</h2>
                <p>Client ID '{client_id}' not found.</p>
                </body></html>
            """, status_code=400)
        
        # Validate redirect_uri
        if redirect_uri not in client["redirect_uris"]:
            return HTMLResponse(f"""
                <html><body>
                <h2>Error: Invalid Redirect URI</h2>
                <p>Redirect URI not allowed for this client.</p>
                </body></html>
            """, status_code=400)
        
        # Validate PKCE
        if not code_challenge:
            return HTMLResponse(f"""
                <html><body>
                <h2>Error: PKCE Required</h2>
                <p>code_challenge parameter is required.</p>
                </body></html>
            """, status_code=400)
        
        if code_challenge_method != "S256":
            return HTMLResponse(f"""
                <html><body>
                <h2>Error: Unsupported PKCE Method</h2>
                <p>Only S256 is supported.</p>
                </body></html>
            """, status_code=400)
        
        # For now, we need user to be logged in via session/cookie
        # In production, integrate with your existing auth system
        # For demo, we'll show a consent page with a "login" link
        
        # Check if user has existing session (via cookie or header)
        # This is a simplified version - integrate with your PHP auth
        user_email = request.cookies.get("user_email") or request.headers.get("X-User-Email")
        user_id = request.cookies.get("user_id") or request.headers.get("X-User-ID")
        
        if not user_id or not user_email:
            # Show login page
            login_url = f"https://dev.tomweb.in/login?redirect=/mcp/authorize?{urlencode(dict(request.query_params))}"
            return RedirectResponse(url=login_url)
        
        # Show consent page
        user_display = user_email.split('@')[0] if user_email else 'User'
        
        return HTMLResponse(f"""<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Approve Access - Tom Labs</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
*{{margin:0;padding:0;box-sizing:border-box}}
body{{font-family:'Inter',-apple-system,BlinkMacSystemFont,sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#1a1a1a;color:#e5e5e5;overflow:hidden;background-image:radial-gradient(ellipse at 50% 0%,rgba(255,152,0,.12),transparent 60%),radial-gradient(ellipse at 80% 100%,rgba(255,87,34,.08),transparent 50%)}}
.card{{width:100%;max-width:460px;padding:32px;background:rgba(40,40,40,.85);border:1px solid rgba(255,255,255,.08);border-radius:16px;box-shadow:0 24px 48px rgba(0,0,0,.4);backdrop-filter:blur(16px)}}
.brand{{font-size:11px;font-weight:600;letter-spacing:1.5px;text-transform:uppercase;color:#888;margin-bottom:20px}}
h1{{font-size:24px;font-weight:700;color:#fff;margin-bottom:12px}}
.desc{{font-size:14px;color:#999;line-height:1.6;margin-bottom:24px}}
.desc strong{{color:#fff;font-weight:600}}
.callback-box{{border:1px solid rgba(255,152,0,.3);border-radius:10px;padding:16px;margin-bottom:20px;background:rgba(255,152,0,.04)}}
.callback-label{{font-size:10px;font-weight:600;letter-spacing:1px;text-transform:uppercase;color:#888;margin-bottom:6px}}
.callback-url{{font-family:'SF Mono',Monaco,'Cascadia Code',monospace;font-size:13px;color:#fff;word-break:break-all}}
.warning{{font-size:12px;color:#777;line-height:1.6;margin-bottom:20px}}
.details-toggle{{font-size:13px;color:#ff9800;cursor:pointer;border:none;background:none;font-family:inherit;padding:0;margin-bottom:20px;display:inline-block}}
.details-toggle:hover{{color:#ffb74d}}
.details-content{{display:none;margin-bottom:20px;padding:16px;background:rgba(255,255,255,.04);border-radius:10px;border:1px solid rgba(255,255,255,.06)}}
.details-content.show{{display:block}}
.detail-label{{font-size:12px;color:#888;font-weight:500}}
.detail-value{{font-family:'SF Mono',Monaco,'Cascadia Code',monospace;font-size:14px;color:#fff;margin-top:4px;word-break:break-all}}
.btn-row{{display:flex;gap:10px;margin-top:8px}}
.btn{{flex:1;padding:12px 20px;border-radius:10px;font-weight:600;font-size:14px;border:none;cursor:pointer;transition:all .15s;font-family:inherit}}
.btn-allow{{background:#ff9800;color:#000}}
.btn-allow:hover{{background:#ffb74d}}
.btn-deny{{background:rgba(255,255,255,.08);color:#999;border:1px solid rgba(255,255,255,.1)}}
.btn-deny:hover{{background:rgba(255,255,255,.12);color:#ccc}}
</style>
</head>
<body>
<div class="card">
<div class="brand">Tom Labs</div>
<h1>Approve access</h1>
<p class="desc"><strong>{escape_html(client['client_name'])}</strong> is asking to use your labs. If you allow it, it acts as you, with exactly the permissions your account already has.</p>

<div class="callback-box">
<div class="callback-label">Your credentials go to</div>
<div class="callback-url">{escape_html(redirect_uri)}</div>
</div>

<p class="warning">Allow this only if you started {escape_html(client['client_name'])} yourself and you recognise the address above. Nobody from Tom Labs will ever ask you to approve this screen.</p>

<button class="details-toggle" onclick="var el=document.getElementById('details');el.classList.toggle('show');this.textContent=el.classList.contains('show')?'− Connection details':'+ Connection details'">+ Connection details</button>
<div class="details-content" id="details">
<div class="detail-label">Client ID</div>
<div class="detail-value">{escape_html(client_id)}</div>
<div class="detail-label" style="margin-top:16px">Scopes requested</div>
<div class="detail-value">{escape_html(' '.join(scope.split()))}</div>
</div>

<form method="POST" action="/mcp/authorize">
<input type="hidden" name="client_id" value="{escape_html(client_id)}">
<input type="hidden" name="redirect_uri" value="{escape_html(redirect_uri)}">
<input type="hidden" name="response_type" value="{escape_html(response_type)}">
<input type="hidden" name="scope" value="{escape_html(scope)}">
<input type="hidden" name="scope_original" value="{escape_html(scope_original)}">
<input type="hidden" name="state" value="{escape_html(state or '')}">
<input type="hidden" name="code_challenge" value="{escape_html(code_challenge)}">
<input type="hidden" name="code_challenge_method" value="{escape_html(code_challenge_method)}">
<input type="hidden" name="resource" value="{escape_html(resource or '')}">
<input type="hidden" name="user_id" value="{escape_html(user_id)}">
<input type="hidden" name="user_email" value="{escape_html(user_email)}">
<input type="hidden" name="action" value="allow">
<div class="btn-row">
<button type="submit" class="btn btn-allow">Allow access</button>
<button type="submit" name="action" value="deny" class="btn btn-deny">Deny</button>
</div>
</form>
</div>
</body>
</html>""")
    
    @mcp.custom_route("/mcp/authorize", methods=["POST"])
    async def oauth_authorize_post(request: Request):
        """Handle consent form submission."""
        db = get_db()
        form = await request.form()
        
        action = form.get("action")
        client_id = form.get("client_id")
        redirect_uri = form.get("redirect_uri")
        response_type = form.get("response_type")
        scope = form.get("scope")  # Display scope (openid profile email)
        scope_original = form.get("scope_original", "labs:*")  # Original scope for token
        state = form.get("state")
        code_challenge = form.get("code_challenge")
        code_challenge_method = form.get("code_challenge_method")
        user_id = form.get("user_id")
        user_email = form.get("user_email")
        
        if action == "deny":
            params = {"error": "access_denied", "error_description": "User denied access"}
            if state:
                params["state"] = state
            return RedirectResponse(url=f"{redirect_uri}?{urlencode(params)}")
        
        # Generate authorization code
        auth_code = secrets.token_urlsafe(32)
        scopes_list = scope_original.split()
        
        await store_auth_code(db, auth_code, client_id, user_id, redirect_uri, 
                              scopes_list, code_challenge, code_challenge_method)
        
        # Redirect back with code
        params = {"code": auth_code}
        if state:
            params["state"] = state
        
        return RedirectResponse(url=f"{redirect_uri}?{urlencode(params)}")
    
    @mcp.custom_route("/mcp/token", methods=["POST"])
    async def oauth_token(request: Request):
        """OAuth Token Endpoint."""
        db = get_db()
        form = await request.form()
        
        grant_type = form.get("grant_type")
        client_id = form.get("client_id")
        client_secret = form.get("client_secret")
        
        # Validate client
        client = await get_oauth_client(db, client_id)
        if not client:
            return JSONResponse(
                {"error": "invalid_client", "error_description": "Invalid client"},
                status_code=401
            )
        
        # Verify client secret (if provided)
        if client_secret and client.get("client_secret") != client_secret:
            return JSONResponse(
                {"error": "invalid_client", "error_description": "Invalid client secret"},
                status_code=401
            )
        
        if grant_type == "authorization_code":
            code = form.get("code")
            code_verifier = form.get("code_verifier")
            redirect_uri = form.get("redirect_uri")
            
            auth_code_doc = await get_and_delete_auth_code(db, code)
            if not auth_code_doc:
                return JSONResponse(
                    {"error": "invalid_grant", "error_description": "Invalid or expired authorization code"},
                    status_code=400
                )
            
            # Verify client_id matches
            if auth_code_doc["client_id"] != client_id:
                return JSONResponse(
                    {"error": "invalid_grant", "error_description": "Client ID mismatch"},
                    status_code=400
                )
            
            # Verify redirect_uri matches
            if auth_code_doc["redirect_uri"] != redirect_uri:
                return JSONResponse(
                    {"error": "invalid_grant", "error_description": "Redirect URI mismatch"},
                    status_code=400
                )
            
            # Verify PKCE
            if not verify_pkce(code_verifier, auth_code_doc["code_challenge"]):
                return JSONResponse(
                    {"error": "invalid_grant", "error_description": "Invalid PKCE code verifier"},
                    status_code=400
                )
            
            # Issue tokens
            tokens = await store_tokens(db, client_id, auth_code_doc["user_id"], auth_code_doc["scopes"])
            return JSONResponse(tokens)
        
        elif grant_type == "refresh_token":
            refresh_token = form.get("refresh_token")
            
            token_data = await validate_refresh_token(db, refresh_token, client_id)
            if not token_data:
                return JSONResponse(
                    {"error": "invalid_grant", "error_description": "Invalid or expired refresh token"},
                    status_code=400
                )
            
            # Issue new tokens
            tokens = await store_tokens(db, client_id, token_data["user_id"], token_data["scopes"])
            return JSONResponse(tokens)
        
        else:
            return JSONResponse(
                {"error": "unsupported_grant_type", "error_description": f"Grant type '{grant_type}' not supported"},
                status_code=400
            )
    
    @mcp.custom_route("/mcp/jwks", methods=["GET"])
    async def jwks(request: Request):
        """JWKS endpoint for token verification."""
        # For now, return empty - implement with actual keys if needed
        return JSONResponse({"keys": []})
