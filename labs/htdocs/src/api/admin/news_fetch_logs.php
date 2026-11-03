<?php
/**
 * News fetch log API — GET /api/admin/news_fetch_logs
 * Returns paginated fetch operation history with per-source detail.
 * Admin-only.
 *
 * Query params:
 *   ?source=    filter by source name
 *   ?from=      date (Y-m-d) range start
 *   ?to=        date (Y-m-d) range end
 *   ?page=      pagination page (default 1)
 *   ?limit=     items per page (default 25)
 */

require_once __DIR__ . '/../../../src/load.php';

header('Content-Type: application/json');

$user = AuthMiddleware::requireAdmin();

$source = $_GET['source'] ?? null;
$from  = $_GET['from'] ?? null;
$to    = $_GET['to'] ?? null;
$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = min(100, max(1, (int)($_GET['limit'] ?? 25)));

try {
    $db = DatabaseConnection::getDefaultDatabase();
    $col = $db->selectCollection('news_fetch_logs');

    $filter = [];
    if (!empty($source)) {
        $filter['source'] = $source;
    }
    if ($from && $to) {
        $f = new \DateTimeImmutable($from);
        $t = new \DateTimeImmutable($to);
        $t->modify('+1 day'); // end-of-day
        $filter['started_at'] = [
            '$gte' => $f,
            '$lt'  => $t,
        ];
    } elseif ($from) {
        $f = new \DateTimeImmutable($from);
        $filter['started_at'] = ['$gte' => $f];
    } elseif ($to) {
        $t = new \DateTimeImmutable($to);
        $t->modify('+1 day');
        $filter['started_at'] = ['$lt' => $t];
    }

    $total = $col->countDocuments($filter);
    $cursor = $col->find($filter, [
        'sort' => ['started_at' => -1],
        'skip' => ($page - 1) * $limit,
        'limit' => $limit,
    ]);

    $rows = [];
    foreach ($cursor as $doc) {
        $rows[] = [
            'started_at'  => $doc['started_at'],
            'completed_at' => $doc['completed_at'] ?? $doc['started_at'],
            'sources'     => array_values($doc['sources'] ?? []),
            'total_fetched' => $doc['total_fetched'] ?? 0,
            'total_inserted' => $doc['total_inserted'] ?? 0,
            'total_updated'  => $doc['total_updated'] ?? 0,
            'total_errors'   => $doc['total_errors'] ?? 0,
            'error_details'  => $doc['error_details'] ?? [],
            'triggered_by'  => $doc['triggered_by'] ?? 'system',
        ];
    }

    echo json_encode([
        'status' => 'success',
        'data' => [
            'items' => $rows,
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
        ],
    ]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'error' => errors_from_exception($e, 'admin/news_fetch_logs')['error']]);
}
