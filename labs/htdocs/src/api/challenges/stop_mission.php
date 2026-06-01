<?php
require_once __DIR__ . '/../../load.php';
require_once __DIR__ . '/../../lib/core/jobs/Worker.class.php';
require_once __DIR__ . '/../../lib/core/RabbitClient.class.php';

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
    $rabbit = new RabbitClient(); 
    
    // Resolve the actual backend template folder name from challenges.json
    $challengesJson = file_get_contents(__DIR__ . '/../../config/challenges.json');
    $challengesData = json_decode($challengesJson, true);
    $templateName = $challengeId;
    foreach ($challengesData as $c) {
        if ($c['lab_id'] === $challengeId && !empty($c['challenge_template'])) {
            $templateName = $c['challenge_template'];
            break;
        }
    }
    
    // Trigger the Python Orchestrator via QUEUE to STOP the challenge
    $work = [
        'action' => 'stop',
        'is_challenge' => true,
        'challenge_id' => $templateName, 
        'hash' => $instanceHash, 
        'user' => $user->getUsername()
    ];

    $rabbit->sendToQueue('labs_jobs', $work);
    
    // Also reset mission_started just in case
    $db->challenge_instances->updateOne(
        ['instance_hash' => $instanceHash],
        ['$set' => ['mission_started' => false]]
    );
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Stop task queued', 
        'hash'   => $instanceHash,
        'queued' => true
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => $e->getMessage()]);
}
