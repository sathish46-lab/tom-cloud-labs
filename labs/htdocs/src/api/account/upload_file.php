<?php
require_once __DIR__ . '/../../../src/load.php';

$user = AuthMiddleware::requireAuth();

$username = $user->getUsername();
if (!$username) {
    api_json(['status' => 'error', 'error' => 'User not found'], 400);
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    api_json(['status' => 'error', 'error' => 'No file uploaded or upload error.'], 400);
}

$file = $_FILES['file'];

// S12: File type allowlist validation
$allowedExtensions = [
    'jpg', 'jpeg', 'png', 'gif', 'webp',  // Images
    'pdf',                                   // Documents
    'txt', 'md', 'csv', 'json', 'xml',     // Text
    'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',  // Office
];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allowedExtensions)) {
    api_json(['status' => 'error', 'error' => 'File type not allowed. Allowed: ' . implode(', ', $allowedExtensions)], 400);
}

// Block double-extension attacks (e.g., shell.php.jpg)
$parts = explode('.', $file['name']);
if (count($parts) > 2) {
    api_json(['status' => 'error', 'error' => 'Files with multiple extensions are not allowed.'], 400);
}

$maxSize = 5 * 1024 * 1024; // 5 MB
if ($file['size'] > $maxSize) {
    api_json(['status' => 'error', 'error' => 'File size exceeds 5MB.'], 400);
}

$userId = $user->getUserId();
if (!$userId) {
    api_json(['status' => 'error', 'error' => 'User ID not found'], 400);
}

// Generate safe filename to prevent overwrites
$originalName = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $file['name']);
$filename = time() . '_' . $originalName;
$s3Path = "labassets/uploads/{$userId}/{$filename}";

if (Storage::upload($file['tmp_name'], $s3Path)) {
    api_json(['status' => 'success'], 200);
} else {
    api_json(['status' => 'error', 'error' => 'Failed to upload file to MinIO.'], 400);
}
