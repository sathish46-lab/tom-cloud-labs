<?php
/**
 * News list HTMX endpoint — GET /news/_list.php
 * Returns server-rendered HTML partial of news cards (no JSON).
 * Used by HTMX "Load More" and filter/search reloads.
 *
 * Query params:
 *   ?source=, ?category=, ?severity=, ?q=, ?skip=, ?limit=, ?group_by_date=
 */

require_once __DIR__ . '/../../src/load.php';

// No auth gate — public read
$_GET['source']     = $_GET['source'] ?? null;
$_GET['category']   = $_GET['category'] ?? null;
$_GET['severity']   = $_GET['severity'] ?? null;
$_GET['q']          = $_GET['q'] ?? null;
$_GET['limit']      = (int)($_GET['limit'] ?? 10);
$_GET['skip']       = (int)($_GET['skip'] ?? 0);
$_GET['group_by_date'] = !empty($_GET['group_by_date']) ? '1' : '0';

// Include the shared partial which renders HTML directly
include __DIR__ . '/../../src/template/partials/_news_list.php';
