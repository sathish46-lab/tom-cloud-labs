<?php
require_once __DIR__ . '/../../../src/load.php';
require_once __DIR__ . '/../../lib/core/RabbitClient.class.php';

header('Content-Type: application/json');

if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    echo json_encode(['status' => 'error', 'error' => 'Unauthorized']); exit;
}

$user = Session::getUser();
$hash = $_POST['hash'] ?? '';

if (empty($hash)) {
    echo json_encode(['status' => 'error', 'error' => 'Missing hash']); exit;
}

try {
    $instDb = DatabaseConnection::getClient()->selectDatabase('tom_labs_instances_db');
    $instDb->instances->updateOne(
        ['instance_hash' => $hash],
        ['$set' => [
            'deploy.status' => 'starting',
            'status' => 'starting',
            'updated_at' => new MongoDB\BSON\UTCDateTime()
        ]]
    );

    $rabbit = new RabbitClient();
    $rabbit->sendToQueue('labs_jobs', [
        'action' => 'start',
        'lab'    => 'instance',
        'hash'   => $hash,
        'user'   => $user->getUsername()
    ]);

    echo json_encode([
        'status'  => 'success',
        'message' => 'Start queued',
        'hash'    => $hash
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => $e->getMessage()]);
}
