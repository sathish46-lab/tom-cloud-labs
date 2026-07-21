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

$instanceHash = $instance['instance_hash'] ?? $slug;
$templateFolder = InstanceFileStore::resolveTemplateFolder($instance);
if (!$templateFolder) {
    echo json_encode(['status' => 'error', 'error' => 'No template for instance']);
    exit;
}

$tmpPath = $_FILES['file']['tmp_name'];
$s3Key = 'labassets/instances/' . $instanceHash . '/' . ltrim($path, '/');

$uploaded = Storage::upload($tmpPath, $s3Key);
if (!$uploaded) {
    echo json_encode(['status' => 'error', 'error' => 'MinIO upload failed']);
    exit;
}

// Store binary file in user layer via single-doc model
InstanceFileStore::deleteNode($instanceHash, $path);
InstanceFileStore::collection()->updateOne(
    ['instance_id' => $instanceHash],
    ['$set' => [
        'files.' . ltrim($path, '/') => [
            'content' => null,
            'size' => $_FILES['file']['size'],
            's3_key' => $s3Key,
        ],
        'username' => $username,
        'email' => $email,
        'updated_at' => new MongoDB\BSON\UTCDateTime(),
    ]]
);

echo json_encode(['status' => 'success', 's3_key' => $s3Key]);
