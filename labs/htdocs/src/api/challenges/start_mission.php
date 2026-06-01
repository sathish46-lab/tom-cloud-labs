<?php
require_once __DIR__ . '/../../load.php';

header('Content-Type: application/json');

if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    echo json_encode(['status' => 'error', 'error' => 'Unauthorized']); exit;
}

$user = Session::getUser();
$challengeId = $_POST['challenge_id'] ?? null;
$instanceHash = $_POST['hash'] ?? null;

if (!$challengeId || !$instanceHash) {
    echo json_encode(['status' => 'error', 'error' => 'Missing parameters']); exit;
}

try {
    $db = DatabaseConnection::getClient()->selectDatabase('tom_labs_db');
    
    // Check if the instance belongs to the user and is running
    $instance = $db->challenge_instances->findOne([
        'instance_hash' => $instanceHash,
        'username' => $user->getUsername()
    ]);
    
    if (!$instance) {
        echo json_encode([
            'status' => 'error', 
            'error' => 'Challenge instance not found. Hash: ' . $instanceHash . ', User: ' . $user->getUsername()
        ]); 
        exit;
    }
    
    $status = $instance['status'] ?? '';
    if ($status !== 'running' && $status !== 'completed') {
        echo json_encode(['status' => 'error', 'error' => 'Challenge is not currently running. Deploy it first.']); exit;
    }

    // Update mission_started
    $db->challenge_instances->updateOne(
        ['instance_hash' => $instanceHash],
        ['$set' => ['mission_started' => true, 'mission_start_time' => time()]]
    );

    echo json_encode([
        'status' => 'success',
        'message' => 'Mission started successfully.'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => $e->getMessage()]);
}
