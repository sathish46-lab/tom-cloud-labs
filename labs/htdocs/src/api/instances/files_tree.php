<?php
require_once __DIR__ . '/../../../src/load.php';

$user = AuthMiddleware::requireAuth();

$userId = (int) $user->getUserId();
$slug = $_GET['slug'] ?? '';

if (empty($slug)) {
api_json(['status' => 'error', 'error' => 'Missing slug'], 400);
}

$db = DatabaseConnection::getClient()->selectDatabase('tom_labs_instances_db');
$instance = $db->instances->findOne(['instance_hash' => $slug]);
if (!$instance) {
    $instance = $db->instances->findOne(['slug' => $slug]);
}

if (!$instance) {
api_json(['status' => 'error', 'error' => 'Instance not found'], 400);
}

// Ownership check
if ((int) ($instance['user_id'] ?? 0) !== $userId) {
api_json(['status' => 'error', 'error' => 'Forbidden'], 403);
}

$instanceHash = $instance['instance_hash'] ?? $slug;
$templateFolder = InstanceFileStore::resolveTemplateFolder($instance);
if (!$templateFolder) {
api_json(['status' => 'success', 'tree' => [], 'template' => null], 200);
}

$tree = InstanceFileStore::getTree($instanceHash, $templateFolder);

$userId = (string)$user->getUserId();
$lastOpenedFiles = (array)($instance['last_file_opened'] ?? []);
$lastOpened = $lastOpenedFiles[$userId] ?? null;

api_json([
    'status' => 'success',
    'tree' => $tree,
    'template' => $templateFolder,
    'last_opened' => $lastOpened,
], 200);
