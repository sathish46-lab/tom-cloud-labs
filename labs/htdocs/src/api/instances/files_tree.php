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

$db = DatabaseConnection::getClient()->selectDatabase('tom_labs_instances_db');
$instance = $db->instances->findOne(['instance_hash' => $slug]);
if (!$instance) {
    $instance = $db->instances->findOne(['slug' => $slug]);
}

if (!$instance) {
    echo json_encode(['status' => 'error', 'error' => 'Instance not found']);
    exit;
}

// Ownership check
if ((int) ($instance['user_id'] ?? 0) !== $userId) {
    echo json_encode(['status' => 'error', 'error' => 'Forbidden']);
    exit;
}

$instanceHash = $instance['instance_hash'] ?? $slug;
$templateFolder = InstanceFileStore::resolveTemplateFolder($instance);
if (!$templateFolder) {
    echo json_encode(['status' => 'success', 'tree' => [], 'template' => null]);
    exit;
}

$tree = InstanceFileStore::getTree($instanceHash, $templateFolder);

$userId = (string)$user->getUserId();
$lastOpenedFiles = (array)($instance['last_file_opened'] ?? []);
$lastOpened = $lastOpenedFiles[$userId] ?? null;

echo json_encode([
    'status' => 'success',
    'tree' => $tree,
    'template' => $templateFolder,
    'last_opened' => $lastOpened,
]);
