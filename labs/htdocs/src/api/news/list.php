<?php
/**
 * News list API — GET /api/news
 * Returns {status:'success', data:[...]} paginated list of latest news articles.
 * Public read — no auth required.
 *
 * Query params:
 *   ?source=   filter by source name
 *   ?category= filter by category
 *   ?severity= filter by severity (info|warning|error|primary|success)
 *   ?q=        free-text search on title+summary
 *   ?limit=    max items (default 50)
 *   ?skip=     pagination offset (default 0)
 */

require_once __DIR__ . '/../../../src/load.php';

// No auth gate for public read list

// Build filter from query params
$source = $_GET['source'] ?? null;
$category = $_GET['category'] ?? null;
$severity = $_GET['severity'] ?? null;
$q = $_GET['q'] ?? null;
$limit = (int)($_GET['limit'] ?? 50);
$skip = (int)($_GET['skip'] ?? 0);
$date_input = $_GET['date_filter'] ?? null;
$date_filter = ($date_input === 'today' || $date_input === 'yesterday') ? $date_input : null;

// Import NewsFetcher for getLatest()
$fetcher = new \TomLabs\Labs\NewsFetcher();

// DB query via NewsFetcher::getLatest() — date boundaries computed in IST
$list = $fetcher->getLatest($source, $category, $severity, $q, $limit, $skip, $date_filter);

header('Content-Type: application/json');
echo json_encode(['status' => 'success', 'data' => $list['items'], 'total' => $list['total']]);