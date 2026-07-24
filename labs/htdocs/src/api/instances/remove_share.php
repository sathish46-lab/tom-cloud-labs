<?php
require_once __DIR__ . '/../../../src/load.php';

header('Content-Type: application/json');

if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    echo json_encode(['status' => 'error', 'error' => 'Unauthorized']); exit;
}

$user = Session::getUser();
$hash = $_POST['hash'] ?? '';
$shareWith = $_POST['username'] ?? '';

if (empty($hash) || empty($shareWith)) {
    echo json_encode(['status' => 'error', 'error' => 'Missing hash or username']); exit;
}

try {
    $instDb = DatabaseConnection::getClient()->selectDatabase('tom_labs_instances_db');
    $instance = $instDb->instances->findOne([
        'instance_hash' => $hash,
        'user_id' => $user->getUserId()
    ]);

    if (!$instance) {
        echo json_encode(['status' => 'error', 'error' => 'Instance not found or not owner']); exit;
    }

    $result = $instDb->instance_shares->deleteOne([
        'instance_hash' => $hash,
        'shared_with' => $shareWith
    ]);

    if ($result->getDeletedCount() === 0) {
        echo json_encode(['status' => 'error', 'error' => 'Share not found']); exit;
    }

    echo json_encode(['status' => 'success', 'message' => "Access removed for {$shareWith}"]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => $e->getMessage()]);
}
