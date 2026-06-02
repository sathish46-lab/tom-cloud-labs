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

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['status' => 'error', 'error' => 'No file uploaded or upload error.']); exit;
}

$file = $_FILES['file'];

// Exclude ZIP, allow images, pdf, txt, etc.
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if ($ext === 'zip') {
    echo json_encode(['status' => 'error', 'error' => 'ZIP files are not allowed.']); exit;
}

$maxSize = 5 * 1024 * 1024; // 5 MB
if ($file['size'] > $maxSize) {
    echo json_encode(['status' => 'error', 'error' => 'File size exceeds 5MB.']); exit;
}

$safeUsername = preg_replace('/[^a-zA-Z0-9_-]/', '', $username);
$uploadDir = __DIR__ . '/../../../uploads/users/' . $safeUsername;

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Enforce 2GB total storage limit
$totalUsed = 0;
$files = scandir($uploadDir);
if ($files !== false) {
    foreach ($files as $f) {
        if ($f !== '.' && $f !== '..') {
            $totalUsed += filesize($uploadDir . '/' . $f);
        }
    }
}
$maxStorage = 2 * 1024 * 1024 * 1024; // 2 GB
if (($totalUsed + $file['size']) > $maxStorage) {
    echo json_encode(['status' => 'error', 'error' => 'Storage limit of 2GB exceeded.']); exit;
}

// Generate safe filename to prevent overwrites, but keep original name if possible
$originalName = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $file['name']);
$filename = time() . '_' . $originalName;
$destination = $uploadDir . '/' . $filename;

if (move_uploaded_file($file['tmp_name'], $destination)) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'error' => 'Failed to move uploaded file.']);
}
