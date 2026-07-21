<?php
require_once __DIR__ . '/../../../src/load.php';

header('Content-Type: application/json');

if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    echo json_encode(['status' => 'error', 'error' => 'Unauthorized']);
    exit;
}

$user = Session::getUser();
$userId = (int) $user->getUserId();
$username = $user->getUsername();
$email = $user->getEmail();

$slug = $_POST['slug'] ?? '';
$path = $_POST['path'] ?? '';
$content = $_POST['content'] ?? '';

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
$templateFolder = InstanceFileStore::resolveTemplateFolder($instance);
if (!$templateFolder) {
    echo json_encode(['status' => 'error', 'error' => 'No template for instance']);
    exit;
}

try {
    $version = InstanceFileStore::saveFile($instanceId, $templateFolder, $path, $content, $username, $email);
    echo json_encode(['status' => 'success', 'version' => $version, 'modified' => true]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'error' => 'Save failed: ' . $e->getMessage()]);
}
