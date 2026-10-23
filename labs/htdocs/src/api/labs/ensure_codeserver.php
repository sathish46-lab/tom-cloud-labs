<?php
// /var/www/labs/htdocs/src/api/labs/ensure_codeserver.php
// Queue the on-demand code-server start on labs_jobs (same funnel as deploy).
// The legacy DeployLog PHP worker spawned here crashed as www-data
// (fopen /var/log/labsctl/labsctl.log → Permission denied → TypeError), so
// labsctl never ran, no run log was created and the UI showed a false success.
require_once __DIR__ . '/../../../src/load.php';
require_once __DIR__ . '/../../lib/core/RabbitClient.class.php';

header('Content-Type: application/json');

$user = AuthMiddleware::requireAuth();

$labName    = $_POST['lab'] ?? 'essentials';
$postedHash = trim($_POST['hash'] ?? '');

try {
    // Prefer the page's SESSION_HASH (validated + ownership-checked below);
    // fall back to the deterministic per-user/per-lab hash.
    $instanceHash = ($postedHash !== '' && preg_match('/^[a-f0-9]{32}$/i', $postedHash))
        ? strtolower($postedHash)
        : $user->getLabHash($labName);
    if (!$instanceHash) {
        throw new Exception('Lab not found');
    }

    $db = DatabaseConnection::getClient()->selectDatabase('tom_labs_db');
    $inst = $db->machine_labs->findOne(
        ['instance_hash' => $instanceHash],
        ['projection' => [
            'user_id' => 1,
            'status' => 1,
            'credentials' => 1,
            'instance_hash' => 1,
            'code_domain' => 1,
            'gui_domain' => 1,
        ]]
    );
    if (!$inst) {
        throw new Exception('Lab not found');
    }
    if (($inst['user_id'] ?? null) !== $user->getUserId()) {
        http_response_code(403);
        exit(json_encode(['status' => 'error', 'error' => 'Access denied']));
    }

    $status = (string)($inst['status'] ?? '');
    if ($status === 'deploying') {
        exit(json_encode([
            'status' => 'error',
            'error'  => 'Lab is being deployed — try again in a moment.',
            'hash'   => $instanceHash,
            'url'    => ''
        ]));
    }
    if ($status !== 'running') {
        exit(json_encode([
            'status' => 'error',
            'error'  => 'Lab is not running. Start it first.',
            'hash'   => $instanceHash,
            'url'    => ''
        ]));
    }

    // Unique per-run ID for THIS launch. /api/labs/logs resolves the run file
    // via machine_labs.deploy_run_id when no run_id is passed, so persist it
    // BEFORE the job can start — otherwise gap-fill replays the previous run.
    $runId = @file_get_contents('/proc/sys/kernel/random/uuid');
    $runId = is_string($runId) ? trim($runId) : '';
    if ($runId === '') {
        $runId = bin2hex(random_bytes(16));
    }
    $db->machine_labs->updateOne(
        ['instance_hash' => $instanceHash],
        ['$set' => ['deploy_run_id' => $runId]]
    );

    $rabbit = new RabbitClient();
    $rabbit->sendToQueue('labs_jobs', [
        'action' => 'ensure-codeserver',
        'lab'    => $labName,
        'hash'   => $instanceHash,
        'user'   => $user->getUsername(),
        'run_id' => $runId,
    ]);

    // Fresh URL from machine_labs — same source labsctl code-status uses.
    $ideUrl = \TomLabs\Labs\LabUrl::ideUrl((array)($inst ?: []));
    if ($ideUrl === '') {
        $ideUrl = \TomLabs\Labs\LabUrl::withScheme((string)($inst['credentials']['code_server_url'] ?? ''));
    }

    echo json_encode([
        'status'  => 'success',
        'message' => 'Code-server startup queued',
        'hash'    => $instanceHash,
        'url'     => $ideUrl,
        'run_id'  => $runId,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => errors_from_exception($e, 'labs/ensure_codeserver')['error']]);
}
