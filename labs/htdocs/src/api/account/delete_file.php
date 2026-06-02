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

$safeUsername = preg_replace('/[^a-zA-Z0-9_-]/', '', $username);
$filePath = __DIR__ . '/../../../uploads/users/' . $safeUsername . '/' . $filename;

if (file_exists($filePath) && is_file($filePath)) {
    if (unlink($filePath)) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'error' => 'Could not delete the file.']);
    }
} else {
    echo json_encode(['status' => 'error', 'error' => 'File not found.']);
}
