#!/usr/bin/env python3
"""svcguard — friendly front door for native (non-HTTP) database ports.

Listens on dedicated guard ports next to the real services. When a browser
sends an HTTP request it answers with a small HTML page explaining which
service lives there and which client to use (MongoDB shows its own built-in
warning, so it is not proxied here). Native clients are piped through
transparently, byte for byte.
"""

import asyncio
import html
import os

LISTEN_HOST = "0.0.0.0"
PEEK_TIMEOUT = 20  # seconds to wait for the first bytes from either side

SERVICES = {
    54321: {
        "backend": ("postgres.tomweb.in", 5432),
        "name": "PostgreSQL",
        "clients": "psql -h &lt;host&gt; -p 54321 -U user",
        "webui": None,
        # PostgreSQL/Redis have no IP whitelists, so plain pass-through is
        # safe. MySQL is NOT guarded: mysqld lacks PROXY-protocol support
        # (MariaDB-only), so source IPs would break its host whitelists —
        # and browsers don't hang on 3306 anyway (mysqld speaks first).
        "proxy_protocol": False,
    },
    63791: {
        "backend": ("redis.tomweb.in", 6379),
        "name": "Redis",
        "clients": "redis-cli -h &lt;host&gt; -p 63791",
        "webui": None,
        "proxy_protocol": False,
    },
    11221: {
        "backend": ("memcached.tomweb.in", 11211),
        "name": "Memcached",
        "clients": "telnet &lt;host&gt; 11221 &nbsp;|&nbsp; mc client / libmemcached",
        "webui": None,
        "proxy_protocol": False,
    },
}

HTTP_PREFIXES = (
    b"GET ", b"POST ", b"HEAD ", b"PUT ", b"DELETE ",
    b"OPTIONS ", b"PATCH ", b"CONNECT ", b"TRACE ",
)

PAGE = """<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>TomCloud Lab — {name}</title>
<style>
 body {{ font-family: -apple-system, 'Segoe UI', Roboto, sans-serif;
        background:#0f172a; color:#e2e8f0; display:flex;
        min-height:100vh; margin:0; align-items:center; justify-content:center; }}
 .card {{ background:#1e293b; border-radius:12px; padding:40px 48px;
         max-width:640px; box-shadow:0 8px 30px rgba(0,0,0,.4); }}
 h1 {{ font-size:22px; margin:0 0 12px; color:#7dd3fc; }}
 code {{ background:#0f172a; padding:2px 8px; border-radius:6px;
        font-size:14px; color:#a5f3fc; }}
 p {{ line-height:1.7; margin:10px 0; }}
 a {{ color:#7dd3fc; }}
 .note {{ color:#94a3b8; font-size:13px; }}
</style></head>
<body><div class="card">
<h1>⚠️ This is the {name} port — not a website</h1>
<p>It looks like you are trying to access {name} over HTTP on the native
protocol port.</p>
<p>Port <code>{port}</code> on this host speaks the {name} wire protocol,
not HTTP. Connect with a {name} client instead:</p>
<p><code>{clients}</code></p>
{webui_html}
<p class="note">Tip: in VS Code, port-forward this same guard port and point
your {name} client at <code>localhost:&lt;local port&gt;</code>.</p>
</div></body></html>"""


def render_page(cfg, port):
    webui_html = ""
    if cfg.get("webui"):
        label, url = cfg["webui"]
        webui_html = (
            "<p>Browser-based access is available via "
            '<a href="{0}">{1}</a>.</p>'.format(url, html.escape(label))
        )
    return PAGE.format(name=cfg["name"], port=port,
                       clients=cfg["clients"], webui_html=webui_html)


async def pipe(src, dst):
    try:
        while True:
            chunk = await src.read(65536)
            if not chunk:
                break
            dst.write(chunk)
            await dst.drain()
    except Exception:
        pass
    finally:
        try:
            dst.close()
        except Exception:
            pass


async def close_writer(writer):
    try:
        writer.close()
        await writer.wait_closed()
    except Exception:
        pass


async def handle(client_reader, client_writer, listen_port):
    cfg = SERVICES[listen_port]
    backend_host, backend_port = cfg["backend"]
    peer = client_writer.get_extra_info("peername")
    try:
        backend_reader, backend_writer = await asyncio.open_connection(
            backend_host, backend_port
        )
    except Exception as exc:
        print("guard: backend {}:{} unreachable from {}: {}".format(
            backend_host, backend_port, peer, exc))
        await close_writer(client_writer)
        return

    client_task = asyncio.ensure_future(client_reader.read(8192))
    backend_task = asyncio.ensure_future(backend_reader.read(8192))
    done, pending = await asyncio.wait(
        {client_task, backend_task},
        return_when=asyncio.FIRST_COMPLETED,
        timeout=PEEK_TIMEOUT,
    )
    if not done:
        for task in pending:
            task.cancel()
        await close_writer(backend_writer)
        await close_writer(client_writer)
        return

    client_first = client_task in done and client_task.exception() is None
    backend_first = backend_task in done and backend_task.exception() is None

    # Browser hit: reply with the friendly page and drop the tunnel.
    if client_first and any(
        (client_task.result() or b"").startswith(p) for p in HTTP_PREFIXES
    ):
        body = render_page(cfg, listen_port).encode()
        client_writer.write(
            b"HTTP/1.0 200 OK\r\n"
            b"Content-Type: text/html; charset=utf-8\r\n"
            b"Content-Length: " + str(len(body)).encode() + b"\r\n"
            b"Connection: close\r\n\r\n" + body
        )
        await client_writer.drain()
        backend_task.cancel()
        await close_writer(backend_writer)
        await close_writer(client_writer)
        print("guard: HTTP request on {} from {} -> served warning page".format(
            listen_port, peer))
        return

    # Native client: forward whatever first bytes arrived and pipe both ways.
    if cfg.get("proxy_protocol"):
        src_ip, src_port = peer[0], peer[1]
        dst_ip, dst_port = client_writer.get_extra_info("sockname")[:2]
        header = "PROXY TCP4 {} {} {} {}\r\n".format(
            src_ip, dst_ip, src_port, dst_port
        ).encode()
        backend_writer.write(header)
    if client_first and client_task.result():
        backend_writer.write(client_task.result())
        await backend_writer.drain()
    if backend_first and backend_task.result():
        client_writer.write(backend_task.result())
        await client_writer.drain()
    for task in pending:
        task.cancel()

    try:
        await asyncio.gather(
            pipe(client_reader, backend_writer),
            pipe(backend_reader, client_writer),
        )
    finally:
        await close_writer(client_writer)
        await close_writer(backend_writer)


async def main():
    servers = []
    for port, cfg in SERVICES.items():
        server = await asyncio.start_server(
            lambda r, w, p=port: handle(r, w, p), LISTEN_HOST, port
        )
        servers.append(server)
        print("guard: {}:{} -> {}:{} ({})".format(
            LISTEN_HOST, port, cfg["backend"][0], cfg["backend"][1],
            cfg["name"]), flush=True)
    await asyncio.gather(*(s.serve_forever() for s in servers))


if __name__ == "__main__":
    try:
        asyncio.run(main())
    except KeyboardInterrupt:
        pass
