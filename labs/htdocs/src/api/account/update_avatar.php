<?php
require_once __DIR__ . '/../../../src/load.php';

$user = AuthMiddleware::requireAuth();

$db = DatabaseConnection::getDefaultDatabase();

if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
    api_json(['status' => 'error', 'error' => 'No file uploaded or upload error.'], 400);
}

$file = $_FILES['avatar'];
$maxSize = 800 * 1024; // 800 KB
if ($file['size'] > $maxSize) {
    api_json(['status' => 'error', 'error' => 'File size exceeds 800KB.'], 400);
}

$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$mimeType = mime_content_type($file['tmp_name']);
if (!in_array($mimeType, $allowedTypes)) {
    api_json(['status' => 'error', 'error' => 'Invalid file format. Only JPG, PNG, GIF, WEBP allowed.'], 400);
}

$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
if (!$extension) {
    $map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    $extension = $map[$mimeType];
}

$userId = $user->getUserId();
if (!$userId) {
    api_json(['status' => 'error', 'error' => 'User ID not found'], 400);
}

$filename = 'avatar_' . time() . '.' . $extension;
$s3Path = "avatars/{$userId}/{$filename}";

if (Storage::upload($file['tmp_name'], $s3Path)) {
    $avatarUrl = "/system/user/avatar/{$userId}/{$filename}";

    try {
        $db->users->updateOne(
            ['email' => $user->getEmail()],
            ['$set' => ['avatar_url' => $avatarUrl]]
        );
        
        // Update session so it reflects globally
        $_SESSION['user_avatar'] = $avatarUrl;
        
        api_json(['status' => 'success', 'avatar_url' => $avatarUrl], 200);
    } catch (Exception $e) {
        api_json(['status' => 'error', 'error' => 'Database update failed.'], 400);
    }
} else {
    api_json(['status' => 'error', 'error' => 'Failed to upload file to MinIO.'], 400);
}
