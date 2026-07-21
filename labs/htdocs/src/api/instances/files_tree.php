<?php
require_once __DIR__ . '/../../../src/load.php';

header('Content-Type: application/json');

if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    echo json_encode(['status' => 'error', 'error' => 'Unauthorized']);
    exit;
}

$user = Session::getUser();
$userId = (int) $user->getUserId();
$slug = $_GET['slug'] ?? '';

if (empty($slug)) {
    echo json_encode(['status' => 'error', 'error' => 'Missing slug']);
    exit;
}

$db = DatabaseConnection::getClient()->selectDatabase('tom_labs_db');
$instance = $db->instances->findOne(['slug' => $slug]);

if (!$instance) {
    echo json_encode(['status' => 'error', 'error' => 'Instance not found']);
    exit;
}

// Ownership check
if ((int) ($instance['user_id'] ?? 0) !== $userId) {
    echo json_encode(['status' => 'error', 'error' => 'Forbidden']);
    exit;
}

$instanceId = $instance['_id'];
$templateFolder = InstanceFileStore::resolveTemplateFolder($instance);
if (!$templateFolder) {
    echo json_encode(['status' => 'success', 'tree' => [], 'template' => null]);
    exit;
}

// Ensure base layer exists (lazy seed if missed at creation)
InstanceFileStore::seedBaseLayer($templateFolder);

$tree = InstanceFileStore::getTree($instanceId, $templateFolder);

echo json_encode([
    'status' => 'success',
    'tree' => $tree,
    'template' => $templateFolder,
]);
