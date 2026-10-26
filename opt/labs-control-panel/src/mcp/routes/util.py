"""Shared HTML helpers for MCP HTTP routes."""


def escape_html(text: str) -> str:
    """Escape HTML special characters."""
    return (str(text)
        .replace("&", "&amp;")
        .replace("<", "&lt;")
        .replace(">", "&gt;")
        .replace('"', "&quot;")
        .replace("'", "&#x27;"))
