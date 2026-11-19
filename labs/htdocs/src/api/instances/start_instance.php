<?php
require_once __DIR__ . '/../../../src/load.php';
require_once __DIR__ . '/../../lib/core/RabbitClient.class.php';

$user = AuthMiddleware::requireAuth();

$hash = $_POST['hash'] ?? '';

if (empty($hash)) {
    api_json(['status' => 'error', 'error' => 'Missing hash'], 400);
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

    api_json([
        'status'  => 'success',
        'message' => 'Start queued',
        'hash'    => $hash
    ], 200);
} catch (Exception $e) {
    api_json(['status' => 'error', 'error' => errors_from_exception($e, 'instances/start_instance')['error']], 500);
}
