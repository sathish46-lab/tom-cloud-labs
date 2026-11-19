<?php
require_once __DIR__ . '/../../../src/load.php';

$user = AuthMiddleware::requireAuth();

$username = $user->getUsername();
if (!$username) {
    api_json(['status' => 'error', 'error' => 'User not found'], 400);
}

$data = json_decode(file_get_contents('php://input'), true);
$filename = trim($data['filename'] ?? '');

if (empty($filename)) {
    api_json(['status' => 'error', 'error' => 'Filename is required.'], 400);
}

// Security: Prevent directory traversal
$filename = basename($filename);

$userId = $user->getUserId();
if (!$userId) {
    api_json(['status' => 'error', 'error' => 'User ID not found'], 400);
}

try {
    $client = Storage::getClient();
    $config = get_config('s3');
    
    $s3Path = "labassets/uploads/{$userId}/{$filename}";
    
    $client->deleteObject([
        'Bucket' => $config['bucket'],
        'Key'    => $s3Path
    ]);
    
    api_json(['status' => 'success'], 200);
} catch (Exception $e) {
    errors_report(['context' => 'account/delete_file', 'message' => "MinIO delete_file Error: " . $e->getMessage()]);
    api_json(['status' => 'error', 'error' => 'Could not delete the file.'], 400);
}
