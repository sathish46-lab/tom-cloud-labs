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

$file = InstanceFileStore::getFile($instanceId, $templateFolder, $path);
if (!$file) {
    echo json_encode(['status' => 'error', 'error' => 'File not found']);
    exit;
}

if ($file['is_binary'] && !empty($file['s3_key'])) {
    // Stream binary from MinIO
    try {
        $client = Storage::getClient();
        $config = get_config('s3');
        $result = $client->getObject([
            'Bucket' => $config['bucket'],
            'Key' => $file['s3_key'],
        ]);
        header('Content-Type: ' . ($file['mime'] ?? 'application/octet-stream'));
        echo $result['Body'];
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'error' => 'Failed to load binary: ' . $e->getMessage()]);
        exit;
    }
}

echo json_encode([
    'status' => 'success',
    'path' => $file['base_path'],
    'name' => $file['name'],
    'content' => $file['content'],
    'is_binary' => !empty($file['is_binary']),
    'modified' => $file['modified'],
    'version' => $file['version'],
]);
