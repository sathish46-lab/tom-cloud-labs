<?php
require_once __DIR__ . '/../../load.php';
require_once __DIR__ . '/../../lib/core/jobs/Worker.class.php';
require_once __DIR__ . '/../../lib/core/RabbitClient.class.php';

header('Content-Type: application/json');

if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    echo json_encode(['status' => 'error', 'error' => 'Unauthorized']); exit;
}

$user = Session::getUser();
$email = $user->getEmail();
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
    
    // Trigger the Python Orchestrator via QUEUE (Scalable)
    $work = [
        'action' => 'deploy',
        'is_challenge' => true,
        'challenge_id' => $templateName, 
        'hash' => $instanceHash, 
        'user' => $user->getUsername()
    ];

    $rabbit->sendToQueue('labs_jobs', $work);
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Deployment queued', 
        'hash'   => $instanceHash,
        'queued' => true
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => $e->getMessage()]);
}
