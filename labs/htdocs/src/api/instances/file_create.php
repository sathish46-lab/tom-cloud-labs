<?php
require_once __DIR__ . '/../../../src/load.php';

$user = AuthMiddleware::requireAuth();

$userId = (int) $user->getUserId();
$username = $user->getUsername();
$email = $user->getEmail();

$slug = $_POST['slug'] ?? '';
$path = $_POST['path'] ?? '';
$isDir = isset($_POST['is_dir']) && $_POST['is_dir'] === '1';
$content = $_POST['content'] ?? '';

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
$templateFolder = InstanceFileStore::resolveTemplateFolder($instance);
if (!$templateFolder) {
api_json(['status' => 'error', 'error' => 'No template for instance'], 400);
}

$result = InstanceFileStore::createNode($instanceHash, $templateFolder, $path, $isDir, $username, $email, $content);
api_json($result, 200);
