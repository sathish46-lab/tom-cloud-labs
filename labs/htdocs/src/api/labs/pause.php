<?php
/**
 * Pause Lab API
 * Freezes the container - zero CPU, memory preserved, network kept
 * Resume is instant
 *
 * Runs through labsctl, never a bare docker call: www-data has no docker
 * socket access here, and the engine owns routing (while paused, lab domains
 * point at the branded down page instead of Traefik's 502).
 */
require_once __DIR__ . '/../../../src/load.php';

header('Content-Type: application/json');

$user = AuthMiddleware::requireAuth();


$labName = $_POST['lab'] ?? 'essentials';

try {
    $instanceHash = $user->getLabHash($labName);

    $db = DatabaseConnection::getClient()->selectDatabase('tom_labs_db');
    $col = $db->machine_labs;

    // Check lab exists and is running
    $inst = $col->findOne(['instance_hash' => $instanceHash]);
    if (!$inst) {
        throw new Exception("Lab not found");
    }

    $currentStatus = $inst['status'] ?? 'unknown';

    if ($currentStatus !== 'running') {
        throw new Exception("Lab must be running to pause. Current status: {$currentStatus}");
    }

    // 1. Freeze the container via labsctl (engine owns docker + Traefik)
    $cmd = sprintf(
        'timeout 60 sudo /usr/bin/python3 /opt/labs-control-panel/labsctl.py lab pause --hash=%s 2>&1',
        escapeshellarg($instanceHash)
    );
    $output = [];
    $returnCode = 0;
    exec($cmd, $output, $returnCode);

    if ($returnCode !== 0) {
        throw new Exception("labsctl lab pause failed (rc={$returnCode}): " . implode("\n", $output));
    }

    // 2. Audit trail only — labsctl owns the status field
    $col->updateOne(
        ['instance_hash' => $instanceHash],
        [
            '$push' => [
                'activity_log' => [
                    '$each' => [
                        [
                            'action' => 'Paused',
                            'user' => $user->getUsername(),
                            'timestamp' => time(),
                            'type' => 'lab'
                        ]
                    ],
                    '$position' => 0,
                    '$slice' => 50
                ]
            ]
        ]
    );

    echo json_encode([
        'status' => 'success',
        'message' => 'Lab paused successfully',
        'hash' => $instanceHash,
        'lab' => $labName
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'error' => errors_from_exception($e, 'labs/pause')['error']
    ]);
}
