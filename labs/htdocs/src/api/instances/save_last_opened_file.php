<?php
require_once __DIR__ . '/../../load.php';

$user = AuthMiddleware::requireAuth();

$userId = (string)$user->getUserId();

$input = json_decode(file_get_contents('php://input'), true);
$hash = $input['slug'] ?? '';
$path = $input['path'] ?? '';

if (empty($hash)) {
api_json(['status' => 'error', 'error' => 'Instance hash is required'], 400);
}

$db = DatabaseConnection::getClient()->selectDatabase('tom_labs_instances_db');
$instance = $db->instances->findOne(['instance_hash' => $hash]);
if (!$instance) {
    $instance = $db->instances->findOne(['slug' => $hash]);
}
if (!$instance || (int)($instance['user_id'] ?? 0) !== (int)$user->getUserId()) {
api_json(['status' => 'error', 'error' => 'Instance not found'], 400);
}

$db->instances->updateOne(
    ['_id' => $instance['_id']],
    ['$set' => ['last_file_opened.' . $userId => $path]]
);

api_json(['status' => 'success'], 200);
