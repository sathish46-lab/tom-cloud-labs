<?php
/**
 * News item detail API — GET /api/news?id=<guid>
 * Returns {status:'success', data:{...}} single article with raw_json for detail view.
 * Public read — no auth required.
 */

require_once __DIR__ . '/../../../src/load.php';

$id = $_GET['id'] ?? '';

if (empty($id)) {
    http_response_code(400);
    header('Content-Type: application/json');
    exit(json_encode(['status' => 'error', 'error' => 'Missing id parameter']));
}

require_once __DIR__ . '/../../../src/lib/labs/NewsFetcher.class.php';

$fetcher = new \TomLabs\Labs\NewsFetcher();
$db = DatabaseConnection::getDefaultDatabase();
$col = $db->selectCollection($fetcher::COLLECTION);

$doc = $col->findOne(['guid' => $id, 'deleted_at' => null], [
    'projection' => [
        'guid' => 1, 'title' => 1, 'link' => 1, 'source' => 1,
        'summary' => 1, 'published_at' => 1, 'fetched_at' => 1,
        'category' => 1, 'severity' => 1, 'cve_ids' => 1,
        'raw_json' => 1, 'deleted_at' => 1,
        '_id' => 0,
    ]
]);

if (!$doc) {
    http_response_code(404);
    header('Content-Type: application/json');
    exit(json_encode(['status' => 'error', 'error' => 'News article not found']));
}

// Ensure _id is stringified for client
$doc['_id'] = (string) $doc['_id'];

header('Content-Type: application/json');
echo json_encode(['status' => 'success', 'data' => $doc]);