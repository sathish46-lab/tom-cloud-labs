<?php
require_once __DIR__ . '/../../load.php';

if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');
$user = Session::getUser();
$userId = (string)$user->getUserId();

$input = json_decode(file_get_contents('php://input'), true);
$hash = $input['slug'] ?? '';
$path = $input['path'] ?? '';

if (empty($hash)) {
    echo json_encode(['status' => 'error', 'error' => 'Instance hash is required']);
    exit;
}

$db = DatabaseConnection::getClient()->selectDatabase('tom_labs_instances_db');
$instance = $db->instances->findOne(['instance_hash' => $hash]);
if (!$instance) {
    $instance = $db->instances->findOne(['slug' => $hash]);
}
if (!$instance || (int)($instance['user_id'] ?? 0) !== (int)$user->getUserId()) {
    echo json_encode(['status' => 'error', 'error' => 'Instance not found']);
    exit;
}

$db->instances->updateOne(
    ['_id' => $instance['_id']],
    ['$set' => ['last_file_opened.' . $userId => $path]]
);

echo json_encode(['status' => 'success']);
