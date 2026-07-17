<?php
require_once __DIR__ . '/../../../src/load.php';

header('Content-Type: application/json');
if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    echo json_encode(['status' => 'error', 'error' => 'Unauthorized']); exit;
}

$user = Session::getUser();
$username = $user->getUsername();
if (!$username) {
    echo json_encode(['status' => 'error', 'error' => 'User not found']); exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$filename = trim($data['filename'] ?? '');

if (empty($filename)) {
    echo json_encode(['status' => 'error', 'error' => 'Filename is required.']); exit;
}

// Security: Prevent directory traversal
$filename = basename($filename);

$userId = $user->getUserId();
if (!$userId) {
    echo json_encode(['status' => 'error', 'error' => 'User ID not found']); exit;
}

try {
    $client = Storage::getClient();
    $config = get_config('s3');
    
    $s3Path = "labassets/uploads/{$userId}/{$filename}";
    
    $client->deleteObject([
        'Bucket' => $config['bucket'],
        'Key'    => $s3Path
    ]);
    
    echo json_encode(['status' => 'success']);
} catch (Exception $e) {
    error_log("MinIO delete_file Error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'error' => 'Could not delete the file.']);
}
