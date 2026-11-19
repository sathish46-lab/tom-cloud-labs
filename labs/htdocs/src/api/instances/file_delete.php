<?php
require_once __DIR__ . '/../../../src/load.php';

$user = AuthMiddleware::requireAuth();

$userId = (int) $user->getUserId();

$slug = $_POST['slug'] ?? '';
$path = $_POST['path'] ?? '';

if (empty($slug) || empty($path)) {
api_json(['status' => 'error', 'error' => 'Missing parameters'], 400);
}

$db = DatabaseConnection::getClient()->selectDatabase('tom_labs_instances_db');
$instance = $db->instances->findOne(['instance_hash' => $slug]);
if (!$instance) {
    $instance = $db->instances->findOne(['slug' => $slug]);
}
if (!$instance || (int) ($instance['user_id'] ?? 0) !== $userId) {
api_json(['status' => 'error', 'error' => 'Instance not found or forbidden'], 400);
}

$instanceHash = $instance['instance_hash'] ?? $slug;
$result = InstanceFileStore::deleteNode($instanceHash, $path);
api_json($result, 200);
