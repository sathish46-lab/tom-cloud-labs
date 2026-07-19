<?php
// /var/www/labs/htdocs/src/api/labs/ensure_codeserver.php
require_once __DIR__ . '/../../../src/load.php';
require_once __DIR__ . '/../../lib/core/jobs/Process.class.php';
require_once __DIR__ . '/../../lib/core/jobs/Worker.class.php';

header('Content-Type: application/json');

if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    echo json_encode(['status' => 'error', 'error' => 'Unauthorized']); exit;
}

$user = Session::getUser();
$labName = $_POST['lab'] ?? 'essentials'; // Default to essentials if not provided, but usually we just need the hash

try {
    // 1. Get hash
    // If it's the 'essentials' lab, we get the hash nicely. 
    // If it's MinIO or others, we ideally need the hash passed or correct lab type.
    // For now assuming 'essentials' as code-server is primary there.
    $instanceHash = $user->getLabHash($labName); 
    
    // 2. Trigger worker
    $work = [
        'lab'    => $labName, 
        'hash'   => $instanceHash, 
        'user'   => $user->getUsername(),
        'action' => 'ensure-codeserver' // Matches our new CLI command
    ];
    
    // We use DeployLog worker because it already wraps labsctl.py and streams logs
    // which is perfect for "Launching..." UI feedback
    $worker = new Worker('DeployLog', $work);
    $process = $worker->invoke();
    
    if (!$process) { 
        throw new Exception('Failed to start worker'); 
    }
    
    // Fetch the latest URL from DB to be sure
    $latestData = $user->getLabData($labName); // Or query directly
    $freshUrl = $latestData['credentials']['code_server_url'] ?? "";

    echo json_encode([
        'status'  => 'success',
        'message' => 'Startup sequence initiated',
        'hash'    => $instanceHash,
        'url'     => $freshUrl
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => $e->getMessage()]);
}
