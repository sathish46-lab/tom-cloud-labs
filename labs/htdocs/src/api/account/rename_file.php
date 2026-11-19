<?php
require_once __DIR__ . '/../../../src/load.php';

$user = AuthMiddleware::requireAuth();

$username = $user->getUsername();
if (!$username) {
    api_json(['status' => 'error', 'error' => 'User not found'], 400);
}

$data = json_decode(file_get_contents('php://input'), true);
$oldName = trim($data['old_name'] ?? '');
$newName = trim($data['new_name'] ?? '');

if (empty($oldName) || empty($newName)) {
    api_json(['status' => 'error', 'error' => 'Both old and new file names are required.'], 400);
}

// Security: Prevent directory traversal
$oldName = basename($oldName);
$newName = basename($newName);

// Sanitize new name to have safe characters, keeping extension
$ext = pathinfo($newName, PATHINFO_EXTENSION);
$nameWithoutExt = pathinfo($newName, PATHINFO_FILENAME);
$safeNameWithoutExt = preg_replace('/[^a-zA-Z0-9_-]/', '_', $nameWithoutExt);
$newName = $safeNameWithoutExt . ($ext ? '.' . $ext : '');

$userId = $user->getUserId();
if (!$userId) {
    api_json(['status' => 'error', 'error' => 'User ID not found'], 400);
}

try {
    $client = Storage::getClient();
    $config = get_config('s3');
    
    $oldKey = "labassets/uploads/{$userId}/{$oldName}";
    $newKey = "labassets/uploads/{$userId}/{$newName}";
    
    // Copy object
    $client->copyObject([
        'Bucket'     => $config['bucket'],
        'CopySource' => "{$config['bucket']}/{$oldKey}",
        'Key'        => $newKey
    ]);
    
    // Delete old object
    $client->deleteObject([
        'Bucket' => $config['bucket'],
        'Key'    => $oldKey
    ]);
    
    api_json(['status' => 'success', 'new_name' => $newName], 200);
} catch (Exception $e) {
    errors_report(['context' => 'account/rename_file', 'message' => "MinIO rename_file Error: " . $e->getMessage()]);
    api_json(['status' => 'error', 'error' => 'Could not rename the file.'], 400);
}
