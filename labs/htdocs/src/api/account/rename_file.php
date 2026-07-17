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
$oldName = trim($data['old_name'] ?? '');
$newName = trim($data['new_name'] ?? '');

if (empty($oldName) || empty($newName)) {
    echo json_encode(['status' => 'error', 'error' => 'Both old and new file names are required.']); exit;
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
    echo json_encode(['status' => 'error', 'error' => 'User ID not found']); exit;
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
    
    echo json_encode(['status' => 'success', 'new_name' => $newName]);
} catch (Exception $e) {
    error_log("MinIO rename_file Error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'error' => 'Could not rename the file.']);
}
