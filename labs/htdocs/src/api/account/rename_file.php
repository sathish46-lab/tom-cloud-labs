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

$safeUsername = preg_replace('/[^a-zA-Z0-9_-]/', '', $username);
$userDir = __DIR__ . '/../../../uploads/users/' . $safeUsername . '/';
$oldPath = $userDir . $oldName;
$newPath = $userDir . $newName;

if (!file_exists($oldPath) || !is_file($oldPath)) {
    echo json_encode(['status' => 'error', 'error' => 'Original file not found.']); exit;
}

if (file_exists($newPath)) {
    echo json_encode(['status' => 'error', 'error' => 'A file with this name already exists.']); exit;
}

if (rename($oldPath, $newPath)) {
    echo json_encode(['status' => 'success', 'new_name' => $newName]);
} else {
    echo json_encode(['status' => 'error', 'error' => 'Could not rename the file.']);
}
