<?php
require_once __DIR__ . '/../../../src/load.php';

header('Content-Type: application/json');
if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    echo json_encode(['status' => 'error', 'error' => 'Unauthorized']); exit;
}

$user = Session::getUser();
$db = DatabaseConnection::getDefaultDatabase();

if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['status' => 'error', 'error' => 'No file uploaded or upload error.']); exit;
}

$file = $_FILES['avatar'];
$maxSize = 800 * 1024; // 800 KB
if ($file['size'] > $maxSize) {
    echo json_encode(['status' => 'error', 'error' => 'File size exceeds 800KB.']); exit;
}

$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$mimeType = mime_content_type($file['tmp_name']);
if (!in_array($mimeType, $allowedTypes)) {
    echo json_encode(['status' => 'error', 'error' => 'Invalid file format. Only JPG, PNG, GIF, WEBP allowed.']); exit;
}

$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
if (!$extension) {
    $map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    $extension = $map[$mimeType];
}

$username = $user->getUsername();
if (!$username) {
    echo json_encode(['status' => 'error', 'error' => 'User not found']); exit;
}

// User-specific directory in htdocs/uploads/users/{username}
$safeUsername = preg_replace('/[^a-zA-Z0-9_-]/', '', $username);
$uploadDir = __DIR__ . '/../../../uploads/users/' . $safeUsername;

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$filename = 'avatar_' . time() . '.' . $extension;
$destination = $uploadDir . '/' . $filename;

if (move_uploaded_file($file['tmp_name'], $destination)) {
    $avatarUrl = '/uploads/users/' . $safeUsername . '/' . $filename;
    
    try {
        $db->users->updateOne(
            ['email' => $user->getEmail()],
            ['$set' => ['avatar_url' => $avatarUrl]]
        );
        
        // Update session so it reflects globally
        $_SESSION['user_avatar'] = $avatarUrl;
        
        echo json_encode(['status' => 'success', 'avatar_url' => $avatarUrl]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'error' => 'Database update failed.']);
    }
} else {
    echo json_encode(['status' => 'error', 'error' => 'Failed to move uploaded file.']);
}
