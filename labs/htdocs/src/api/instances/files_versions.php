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
$path = $_GET['path'] ?? '';

if (empty($slug) || empty($path)) {
    echo json_encode(['status' => 'error', 'error' => 'Missing parameters']);
    exit;
}

$db = DatabaseConnection::getClient()->selectDatabase('tom_labs_db');
$instance = $db->instances->findOne(['slug' => $slug]);
if (!$instance || (int) ($instance['user_id'] ?? 0) !== $userId) {
    echo json_encode(['status' => 'error', 'error' => 'Instance not found or forbidden']);
    exit;
}

$instanceId = $instance['_id'];
$versions = InstanceFileStore::getVersions($instanceId, $path);
echo json_encode(['status' => 'success', 'versions' => $versions]);
