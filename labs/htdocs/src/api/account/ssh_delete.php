<?php
require_once __DIR__ . '/../../../src/load.php';

header('Content-Type: application/json');
if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    echo json_encode(['status' => 'error', 'error' => 'Unauthorized']); exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$keyId = $data['id'] ?? null;
$user = Session::getUser();
$db = DatabaseConnection::getDefaultDatabase();

if ($keyId) {
    // 1. Remove from MongoDB
    $db->ssh_keys->deleteOne([
        '_id' => new MongoDB\BSON\ObjectId($keyId),
        'user_id' => $user->getUserId()
    ]);

    // 2. Re-Sync Container: This wipes the deleted key from the container
    shell_exec("sudo labsctl syncuser " . escapeshellarg($user->getUsername()));

    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'error' => 'Invalid ID']);
}
