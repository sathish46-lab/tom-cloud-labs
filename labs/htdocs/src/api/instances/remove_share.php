<?php
require_once __DIR__ . '/../../../src/load.php';

$user = AuthMiddleware::requireAuth();

$hash = $_POST['hash'] ?? '';
$shareWith = $_POST['username'] ?? '';

if (empty($hash) || empty($shareWith)) {
    api_json(['status' => 'error', 'error' => 'Missing hash or username'], 400);
}

try {
    $instDb = DatabaseConnection::getClient()->selectDatabase('tom_labs_instances_db');
    $instance = $instDb->instances->findOne([
        'instance_hash' => $hash,
        'user_id' => $user->getUserId()
    ]);

    if (!$instance) {
        api_json(['status' => 'error', 'error' => 'Instance not found or not owner'], 400);
    }

    $result = $instDb->instance_shares->deleteOne([
        'instance_hash' => $hash,
        'shared_with' => $shareWith
    ]);

    if ($result->getDeletedCount() === 0) {
        api_json(['status' => 'error', 'error' => 'Share not found'], 400);
    }

    api_json(['status' => 'success', 'message' => "Access removed for {$shareWith}"], 200);
} catch (Exception $e) {
    api_json(['status' => 'error', 'error' => errors_from_exception($e, 'instances/remove_share')['error']], 500);
}
