<?php
/**
 * Save Custom Error Page
 * POST: hash, custom_error_page (HTML content)
 * GET: hash (returns current custom_error_page)
 */
require_once __DIR__ . '/../../../src/load.php';

$user = AuthMiddleware::requireAuth();

$db = DatabaseConnection::getDefaultDatabase();
$hash = $_GET['hash'] ?? $_POST['hash'] ?? '';

if (empty($hash)) {
    api_json(['error' => 'Missing hash'], 400);
}

$inst = $db->machine_labs->findOne(['instance_hash' => $hash]);
// String compare across numeric/ObjectId user_id storage kinds (see logs.php).
$ownerId = $inst ? (string)($inst['user_id'] ?? '') : '';
if (!$inst || $ownerId === '' || $ownerId !== (string)$user->getUserId()) {
    api_json(['error' => 'Lab not found'], 404);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    api_json([
        'custom_error_page' => $inst['custom_error_page'] ?? ''
    ], 200);
    exit;
}

// POST: Save
$content = $_POST['custom_error_page'] ?? '';

if (strlen($content) > 65536) {
    api_json(['error' => 'Content exceeds 64 KB limit'], 400);
}

$db->machine_labs->updateOne(
    ['instance_hash' => $hash],
    ['$set' => ['custom_error_page' => $content]]
);

api_json(['status' => 'ok'], 200);
