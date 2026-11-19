<?php
require_once __DIR__ . '/../../load.php';

$user = AuthMiddleware::requireAuth();

$challengeId = $_POST['challenge_id'] ?? null;
$instanceHash = $_POST['hash'] ?? null;

if (!$challengeId || !$instanceHash) {
    api_json(['status' => 'error', 'error' => 'Missing parameters'], 400);
}

try {
    $db = DatabaseConnection::getClient()->selectDatabase('tom_labs_db');
    
    // Check if the instance belongs to the user and is running
    $instance = $db->challenge_instances->findOne([
        'instance_hash' => $instanceHash,
        'username' => $user->getUsername()
    ]);
    
    if (!$instance) {
        api_json([
            'status' => 'error', 
            'error' => 'Challenge instance not found. Hash: ' . $instanceHash . ', User: ' . $user->getUsername()
        ], 200);
        exit;
    }
    
    $status = $instance['status'] ?? '';
    if ($status !== 'running' && $status !== 'completed') {
        api_json(['status' => 'error', 'error' => 'Challenge is not currently running. Deploy it first.'], 400);
    }

    // Update mission_started
    $db->challenge_instances->updateOne(
        ['instance_hash' => $instanceHash],
        ['$set' => ['mission_started' => true, 'mission_start_time' => time()]]
    );

    api_json([
        'status' => 'success',
        'message' => 'Mission started successfully.'
    ], 200);

} catch (Exception $e) {
    api_json(['status' => 'error', 'error' => errors_from_exception($e, 'challenges/start_mission')['error']], 500);
}
