<?php
/**
 * Resume Lab API
 * Unfreezes the container - restores CPU, memory, network
 * Instant resume
 *
 * Runs through labsctl, never a bare docker call: the engine also swaps the
 * down-state routing written on pause back to the real lab config.
 */
require_once __DIR__ . '/../../../src/load.php';

header('Content-Type: application/json');

$user = AuthMiddleware::requireAuth();


$labName = $_POST['lab'] ?? 'essentials';

/**
 * Container is present in the database but not actually paused (exited /
 * missing / database status lies). Sits outside the normal exception flow so
 * the user gets an actionable message instead of raw engine internals.
 */
class LabNotUsableException extends Exception {}

try {
    $instanceHash = $user->getLabHash($labName);

    $db = DatabaseConnection::getClient()->selectDatabase('tom_labs_db');
    $col = $db->machine_labs;

    // The stored status can be stale (crash / manual stop outside the UI) —
    // the engine check below is the source of truth.
    $inst = $col->findOne(['instance_hash' => $instanceHash]);
    if (!$inst) {
        throw new LabNotUsableException("Lab not found");
    }

    if (($inst['status'] ?? 'unknown') !== 'paused') {
        throw new LabNotUsableException("Lab must be paused to resume");
    }

    // 1. Unfreeze the container via labsctl (engine owns docker + Traefik)
    $cmd = sprintf(
        'timeout 60 sudo /usr/bin/python3 /opt/labs-control-panel/labsctl.py lab resume --hash=%s 2>&1',
        escapeshellarg($instanceHash)
    );
    $output = [];
    $returnCode = 0;
    exec($cmd, $output, $returnCode);

    if ($returnCode === 3) {
        throw new LabNotUsableException("Lab container is not active");
    }
    if ($returnCode !== 0) {
        throw new Exception("labsctl lab resume failed (rc={$returnCode})");
    }

    // 2. Audit trail only — labsctl owns the status field
    $col->updateOne(
        ['instance_hash' => $instanceHash],
        [
            '$push' => [
                'activity_log' => [
                    '$each' => [
                        [
                            'action' => 'Resumed',
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
        'message' => 'Lab resumed successfully',
        'hash' => $instanceHash,
        'lab' => $labName
    ]);

} catch (LabNotUsableException $e) {
    http_response_code(409);
    error_log('[labs/resume] lab not usable: ' . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'code' => 'lab_broken',
        'message' => 'Somehow the lab is broken. Redeploy it to continue learning.'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'error' => errors_from_exception($e, 'labs/resume')['error']
    ]);
}
