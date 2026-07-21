<?php
require_once __DIR__ . '/../../load.php';

if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    http_response_code(401);
    echo 'Unauthorized';
    exit;
}

$user = Session::getUser();
$userId = (int)$user->getUserId();
$username = $user->getUsername();
$email = $user->getEmail();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$slug = trim($input['slug'] ?? $_POST['slug'] ?? '');

if (empty($slug)) {
    http_response_code(400);
    echo 'Missing slug';
    exit;
}

$db = DatabaseConnection::getClient()->selectDatabase('tom_labs_instances_db');
$instance = $db->instances->findOne(['instance_hash' => $slug]);
if (!$instance) {
    $instance = $db->instances->findOne(['slug' => $slug]);
}

if (!$instance || (int)($instance['user_id'] ?? 0) !== $userId) {
    http_response_code(404);
    echo 'Instance not found or forbidden';
    exit;
}

$instance['trashed_at'] = new MongoDB\BSON\UTCDateTime();
$instance['trashed_by'] = $username;

$result = $db->instance_trash->insertOne($instance);

if ($result->getInsertedCount() > 0) {
    $db->instances->deleteOne(['_id' => $instance['_id']]);
    echo json_encode(['status' => 'success']);
} else {
    http_response_code(500);
    echo 'Failed to trash instance';
}
