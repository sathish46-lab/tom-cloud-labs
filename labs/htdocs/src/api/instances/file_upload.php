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

if (empty($slug) || empty($path)) {
    echo json_encode(['status' => 'error', 'error' => 'Missing parameters']);
    exit;
}

if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['status' => 'error', 'error' => 'No file uploaded']);
    exit;
}

$db = DatabaseConnection::getClient()->selectDatabase('tom_labs_instances_db');
$instance = $db->instances->findOne(['instance_hash' => $slug]);
if (!$instance) {
    $instance = $db->instances->findOne(['slug' => $slug]);
}
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

$tmpPath = $_FILES['file']['tmp_name'];
$s3Key = 'labassets/instances/' . $instanceId . '/' . ltrim($path, '/');

$uploaded = Storage::upload($tmpPath, $s3Key);
if (!$uploaded) {
    echo json_encode(['status' => 'error', 'error' => 'MinIO upload failed']);
    exit;
}

// Remove any existing user-layer doc for this path, then store binary metadata
InstanceFileStore::deleteNode($instanceId, $path);
InstanceFileStore::collection()->insertOne([
    'layer' => 'user',
    'instance_id' => $instanceId,
    'template' => $templateFolder,
    'base_path' => ltrim($path, '/'),
    'name' => basename($path),
    'is_dir' => false,
    'size' => $_FILES['file']['size'],
    'mime' => mime_content_type($tmpPath) ?: 'application/octet-stream',
    'content' => null,
    's3_key' => $s3Key,
    'username' => $username,
    'email' => $email,
    'version' => 1,
    'created_at' => new MongoDB\BSON\UTCDateTime(),
    'updated_at' => new MongoDB\BSON\UTCDateTime(),
]);

echo json_encode(['status' => 'success', 's3_key' => $s3Key]);
