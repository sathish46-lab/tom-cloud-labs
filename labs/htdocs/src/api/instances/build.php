<?php
require_once __DIR__ . '/../../../src/load.php';
require_once __DIR__ . '/../../lib/core/RabbitClient.class.php';

$user = AuthMiddleware::requireAuth();

$hash = $_POST['hash'] ?? '';

if (empty($hash)) {
    api_json(['status' => 'error', 'error' => 'Missing hash'], 400);
}

try {
    $db = DatabaseConnection::getClient()->selectDatabase('tom_labs_instances_db');
    $instance = $db->instances->findOne([
        'instance_hash' => $hash,
        'user_id' => $user->getUserId()
    ]);

    if (!$instance) {
        api_json(['status' => 'error', 'error' => 'Instance not found'], 400);
    }

    $db->instances->updateOne(
        ['instance_hash' => $hash],
        ['$set' => ['status' => 'building', 'updated_at' => new MongoDB\BSON\UTCDateTime()]]
    );

    $rabbit = new RabbitClient();
    $rabbit->sendToQueue('labs_jobs', [
        'action'     => 'build',
        'lab'        => 'instance',
        'hash'       => $hash,
        'user'       => $user->getUsername(),
        'vsc_domain' => $instance['code_domain'] ?? ''
    ]);

    api_json([
        'status'  => 'success',
        'message' => 'Build queued',
        'hash'    => $hash
    ], 200);
} catch (Exception $e) {
    api_json(['status' => 'error', 'error' => errors_from_exception($e, 'instances/build')['error']], 500);
}
