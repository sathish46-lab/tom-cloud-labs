<?php
require_once __DIR__ . '/../../../src/load.php';

$user = AuthMiddleware::requireAuth();

$data = json_decode(file_get_contents('php://input'), true);
$keyId = $data['id'] ?? null;

$db = DatabaseConnection::getDefaultDatabase();

if ($keyId) {
    // 1. Soft-delete from MongoDB (keep record for audit trail)
    $db->ssh_keys->updateOne([
        '_id' => new MongoDB\BSON\ObjectId($keyId),
        'user_id' => $user->getUserId()
    ], [
        '$set' => [
            'status' => 'deleted',
            'deleted_at' => new MongoDB\BSON\UTCDateTime(),
            'deleted_by' => $user->getEmail(),
        ]
    ]);

    // 2. Re-Sync Container: This wipes the deleted key from the container
    shell_exec("sudo labsctl syncuser " . escapeshellarg($user->getUsername()));

    api_json(['status' => 'success'], 200);
} else {
    api_json(['status' => 'error', 'error' => 'Invalid ID'], 400);
}
