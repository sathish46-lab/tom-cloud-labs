<?php
/**
 * News delete API — POST /api/news/delete
 * Admin-only: soft-delete an article by guid (sets deleted_at timestamp).
 * Requires valid CSRF token.
 * Returns {status:'success', data:{guid, deleted_at}} on success,
 * or {status:'error', error} on failure.
 */

require_once __DIR__ . '/../../../src/load.php';

$auth = AuthMiddleware::requireAdmin(); // 401 if not logged in, 403 if not superuser

// CSRF validation
AuthMiddleware::requireCsrf(); // 403 if invalid

require_once __DIR__ . '/../../../src/lib/labs/NewsFetcher.class.php';

$guid = $_POST['guid'] ?? '';

if (empty($guid)) {
    http_response_code(400);
    header('Content-Type: application/json');
    exit(json_encode(['status' => 'error', 'error' => 'Missing guid parameter']));
}

$fetcher = new \TomLabs\Labs\NewsFetcher();
$success = $fetcher->softDelete($guid);

if (!$success) {
    http_response_code(404);
    header('Content-Type: application/json');
    exit(json_encode(['status' => 'error', 'error' => 'News article not found or already deleted']));
}

header('Content-Type: application/json');
echo json_encode(['status' => 'success', 'data' => ['guid' => $guid, 'deleted_at' => (string) TimeHelper::now()]]);