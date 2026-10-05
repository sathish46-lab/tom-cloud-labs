<?php
/**
 * GET /api/leaderboard_rows?offset=<rank>&limit=<n>
 *
 * The Global Leaderboard's "Load more" endpoint: returns raw <tr> HTML for the
 * next window of ranks (10 by default) so the browser can append it to
 * tbody.ninja-leaderboard without re-rendering the page.
 */
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/../../src/load.php';

AuthMiddleware::requireAuth();

require_once __DIR__ . '/../utils/leaderboard.php';

$offset = max(1, (int)($_GET['offset'] ?? 1));
$limit  = (int)($_GET['limit'] ?? LB_LOAD_MORE_ROWS);
$limit  = min(LB_MAX_ROWS, max(1, $limit));

$ctfTotal = 0;
$entries  = leaderboard_entries($offset - 1, $limit, $ctfTotal);

foreach ($entries as $entry) {
    echo leaderboard_row_html($entry, $ctfTotal);
}
