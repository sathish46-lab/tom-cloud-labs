"""MCP Discovery UI (GET /mcp) and debug routes."""
import logging

from mcp.server.fastmcp import FastMCP
from starlette.requests import Request
from starlette.responses import HTMLResponse, JSONResponse

from src.mcp.registry import catalog
from src.mcp.routes.util import escape_html
from src.mcp.settings import MCP_PUBLIC_URL

logger = logging.getLogger(__name__)


def setup_discovery_routes(mcp: FastMCP):
    """Setup Discovery UI and debug routes."""

    @mcp.custom_route("/mcp", methods=["GET"])
    async def mcp_discovery_ui(request: Request):
        """MCP Discovery UI - shows connection instructions and tool list."""
        base = MCP_PUBLIC_URL
        
        # Get available tools from MCP server
        tools_list = catalog()
        
        # Group by category
        categories = {}
        for tool in tools_list:
            cat = tool.get("category", "Other")
            if cat not in categories:
                categories[cat] = []
            categories[cat].append(tool)
        
        return HTMLResponse(f"""
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Tom Labs MCP Server</title>
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
                <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
                <style>
                    :root {{
                        --primary: #ff9800;
                        --primary-dark: #f57c00;
                        --bg: #fafafa;
                        --card-bg: #fff;
                        --text: #1a1a1a;
                        --muted: #666;
                        --border: #e8e8e8;
                    }}
                    @media (prefers-color-scheme: dark) {{
                        :root {{
                            --bg: #121212;
                            --card-bg: #1e1e1e;
                            --text: #eaeaea;
                            --muted: #aaa;
                            --border: #333;
                        }}
                    }}
                    body {{ background: var(--bg); color: var(--text); font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; min-height: 100vh; }}
                    .hero {{ background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); color: white; padding: 60px 20px; }}
                    .card {{ background: var(--card-bg); border: 1px solid var(--border); border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }}
                    .tool-item {{ padding: 16px; border-bottom: 1px solid var(--border); transition: background 0.15s; }}
                    .tool-item:last-child {{ border-bottom: none; }}
                    .tool-item:hover {{ background: rgba(255,152,0,0.05); }}
                    .tool-name {{ font-family: 'JetBrains Mono', monospace; font-size: 14px; font-weight: 600; color: var(--text); }}
                    .tool-desc {{ color: var(--muted); font-size: 13px; margin-top: 4px; }}
                    .tool-category {{ text-transform: uppercase; font-size: 11px; font-weight: 600; color: var(--primary); letter-spacing: 0.5px; margin: 24px 0 12px; padding-bottom: 8px; border-bottom: 2px solid var(--primary); }}
                    .config-box {{ background: #f5f5f5; border-radius: 8px; padding: 16px; font-family: 'JetBrains Mono', monospace; font-size: 12px; overflow-x: auto; }}
                    @media (prefers-color-scheme: dark) {{ .config-box {{ background: #2a2a2a; }} }}
                    .btn-primary {{ background: var(--primary); border-color: var(--primary); }}
                    .btn-primary:hover {{ background: var(--primary-dark); border-color: var(--primary-dark); }}
                    .badge-oauth {{ background: var(--primary); color: white; font-size: 11px; padding: 4px 8px; border-radius: 4px; }}
                    .status-connected {{ color: #4caf50; }}
                    .status-disconnected {{ color: #f44336; }}
                </style>
            </head>
            <body>
                <div class="hero text-center">
                    <div class="container">
                        <div style="max-width: 720px; margin: 0 auto;">
                            <i class="bx bxl-meta" style="font-size: 48px; margin-bottom: 16px; opacity: 0.9;"></i>
                            <h1 style="font-weight: 700; margin-bottom: 8px;">Tom Labs MCP Server</h1>
                            <p class="lead" style="opacity: 0.9; margin-bottom: 0;">Model Context Protocol endpoint for AI-powered lab management</p>
                        </div>
                    </div>
                </div>
                
                <div class="container py-5">
                    <div class="row g-4">
                        <!-- Connection Info -->
                        <div class="col-12 col-lg-4">
                            <div class="card h-100 p-4">
                                <h3 class="h5 mb-3"><i class="bx bx-link-external me-2"></i>Connection Endpoint</h3>
                                <div class="config-box mb-3" id="endpointUrl">{base}/mcp</div>
                                <button class="btn btn-sm btn-outline-secondary w-100 mb-3" onclick="copyToClipboard('endpointUrl')">
                                    <i class="bx bx-copy me-1"></i> Copy Endpoint
                                </button>
                                
                                <h3 class="h5 mb-3"><i class="bx bx-key me-2"></i>Authentication</h3>
                                <span class="badge-oauth me-2">OAuth 2.1 + PKCE</span>
                                <span class="badge bg-secondary">Bearer Token</span>
                                <p class="text-muted small mt-2">Supports dynamic client registration (RFC 7591)</p>
                                
                                <div class="mt-3 p-3 rounded" style="background: rgba(255,152,0,0.1);">
                                    <strong>Quick Connect (opencode):</strong>
                                    <pre class="config-box mt-2 mb-0" style="font-size: 11px;">opencode mcp add labs {base}/mcp</pre>
                                </div>
                            </div>
                        </div>
                        
                        <!-- OAuth Flow -->
                        <div class="col-12 col-lg-4">
                            <div class="card h-100 p-4">
                                <h3 class="h5 mb-3"><i class="bx bx-shield-check me-2"></i>OAuth Flow</h3>
                                <ol class="small text-muted" style="line-height: 2;">
                                    <li>Client registers via <code>/mcp/register</code></li>
                                    <li>User visits <code>/mcp/authorize</code></li>
                                    <li>User logs in &amp; grants consent</li>
                                    <li>Client exchanges code for tokens at <code>/mcp/token</code></li>
                                    <li>Use access token as Bearer header</li>
                                </ol>
                                <hr>
                                <h6 class="text-muted">Endpoints</h6>
                                <div class="config-box small">
Authorization: {base}/mcp/authorize<br>
Token: {base}/mcp/token<br>
Register: {base}/mcp/register<br>
Metadata: {base}/.well-known/oauth-authorization-server
                                </div>
                            </div>
                        </div>
                        
                        <!-- Quick Start -->
                        <div class="col-12 col-lg-4">
                            <div class="card h-100 p-4">
                                <h3 class="h5 mb-3"><i class="bx bx-rocket me-2"></i>Quick Start</h3>
                                
                                <h6 class="mb-2">1. opencode (CLI)</h6>
                                <pre class="config-box small mb-3"># Auto OAuth flow
opencode mcp auth labs

# Or manual Bearer token
export TOM_LABS_MCP_TOKEN="mcp_xxx..."
opencode</pre>
                                
                                <h6 class="mb-2">2. Claude Code</h6>
                                <pre class="config-box small mb-3">claude mcp add labs {base}/mcp \\
  --header "Authorization: Bearer $TOM_LABS_MCP_TOKEN"</pre>
                                
                                <h6 class="mb-2">3. Cursor / Windsurf</h6>
                                <pre class="config-box small mb-0">Add to mcp.json:
{{
  "mcpServers": {{
    "labs": {{
      "url": "{base}/mcp",
      "headers": {{ "Authorization": "Bearer YOUR_TOKEN" }}
    }}
  }}
}}</pre>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tools Catalog -->
                    <div class="row mt-5">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header bg-transparent border-bottom">
                                    <h3 class="h5 mb-0"><i class="bx bx-cog me-2"></i>Available Tools ({len(tools_list)})</h3>
                                </div>
                                <div class="card-body p-0">
                                    {''.join(f'''
                                    <div class="tool-category">{cat} ({len(tools)})</div>
                                    {''.join(f'''
                                    <div class="tool-item">
                                        <div class="tool-name">{escape_html(t['name'])}</div>
                                        <div class="tool-desc">{escape_html(t['description'])}</div>
                                    </div>''' for t in tools)}
                                    ''' for cat, tools in categories.items())}
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Inspector Link -->
                    <div class="row mt-4">
                        <div class="col-12 text-center">
                            <a href="/mcp/inspector" class="btn btn-primary btn-lg px-5">
                                <i class="bx bx-terminal me-2"></i>Open MCP Inspector (Playground)
                            </a>
                            <p class="text-muted mt-2">Test tools interactively with OAuth authentication</p>
                        </div>
                    </div>
                </div>
                
                <footer class="text-center py-4 text-muted small border-top" style="border-color: var(--border) !important;">
                    Tom Labs MCP Server &middot; <a href="https://github.com/sathish46-lab/tom-cloud-labs" class="text-muted" target="_blank">GitHub</a> &middot; 
                    <a href="/.well-known/oauth-authorization-server" class="text-muted">OAuth Metadata</a> &middot;
                    <a href="/.well-known/oauth-protected-resource" class="text-muted">Resource Metadata</a>
                </footer>
                
                <script>
                    function copyToClipboard(elementId) {{
                        const text = document.getElementById(elementId).textContent;
                        navigator.clipboard.writeText(text).then(() => {{
                            const btn = event.target.closest('button');
                            const original = btn.innerHTML;
                            btn.innerHTML = '<i class="bx bx-check me-1"></i> Copied!';
                            setTimeout(() => btn.innerHTML = original, 2000);
                        }});
                    }}
                </script>
            </body>
            </html>
        """)

    @mcp.custom_route('/debug-path', methods=['GET'])
    async def debug_path(request: Request):
        return JSONResponse({'path': str(request.url.path), 'headers': dict(request.headers)})
