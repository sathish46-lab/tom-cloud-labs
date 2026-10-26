"""
MCP resources — static, read-only documents served over the protocol.

SECURITY: resources are text produced by help_content (static) — no DB
access, no per-user data, no secrets.
"""

import logging

from mcp.server.fastmcp import FastMCP

from src.mcp import help_content

logger = logging.getLogger(__name__)


def setup_resources(mcp: FastMCP) -> None:
    """Register the tomlabs://help/{topic} resource template."""

    @mcp.resource(
        "tomlabs://help/{topic}",
        name="Tom Labs help",
        description="Help topics for the Tom Labs MCP server (overview, getting-started, tools, categories, workflows, security, troubleshooting).",
        mime_type="text/markdown",
    )
    def help_topic(topic: str) -> str:
        return help_content.resource_text(topic)

    logger.info(
        "MCP resources registered: %d help topics",
        len(help_content.resource_catalog()),
    )
