<?php
/**
 * News item detail API — GET /api/news?id=<guid>
 * Returns {status:'success', data:{...}} single article with raw_json for detail view.
 * Public read — no auth required.
 */

require_once __DIR__ . '/../../../src/load.php';

$id = $_GET['id'] ?? '';

if (empty($id)) {
    api_json(['status' => 'error', 'error' => 'Missing id parameter'], 400);
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
    api_json(['status' => 'error', 'error' => 'News article not found'], 404);
}

// Ensure _id is stringified for client
$doc['_id'] = (string) $doc['_id'];

api_json(['status' => 'success', 'data' => $doc], 200);
