<?php
/**
 * POST /api/instance/preferences_apply
 * Save preferences AND queue an apply-preferences job via RabbitMQ.
 * This updates Traefik routing and runs init.sh without a full container redeploy.
 */
require_once __DIR__ . '/../../../src/load.php';
require_once __DIR__ . '/../../lib/core/jobs/Process.class.php';
require_once __DIR__ . '/../../lib/core/jobs/Worker.class.php';
require_once __DIR__ . '/../../lib/core/RabbitClient.class.php';

header('Content-Type: application/json');

if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    echo json_encode(['status' => 'error', 'error' => 'Unauthorized']); exit;
}

$user = Session::getUser();
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['hash'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'error' => 'Missing required fields']); exit;
}

$labName = $input['lab'] ?? 'essentials';
$instanceHash = $user->getLabHash($labName);

if ($instanceHash !== $input['hash']) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'error' => 'Hash mismatch']); exit;
}

try {
    $db = DatabaseConnection::getClient()->selectDatabase('tom_labs_db');
    $col = $db->deployed_labs;

    $existing = $col->findOne(['instance_hash' => $instanceHash]);
    if (!$existing) {
        throw new Exception('Lab not found. Please deploy first.');
    }

    // Check if the lab is actually running
    $status = $existing['status'] ?? 'offline';
    if ($status !== 'running') {
        throw new Exception('Lab is not running. Start or redeploy your lab first.');
    }

    // Sanitize HTTP proxies
    $httpProxies = [];
    if (isset($input['http_proxies']) && is_array($input['http_proxies'])) {
        foreach ($input['http_proxies'] as $proxy) {
            $port = isset($proxy['port']) ? (int)$proxy['port'] : 0;
            $domain = isset($proxy['domain']) ? trim((string)$proxy['domain']) : '';
            if ($port > 0 && $port <= 65535 && !empty($domain)) {
                $httpProxies[] = [
                    'port' => $port,
                    'domain' => $domain
                ];
            }
        }
    }

    $alwaysOn = !empty($input['always_on']);
    $initScript = isset($input['init_script']) ? (string)$input['init_script'] : '#!/bin/bash';

    // 1. Save to DB first
    $col->updateOne(
        ['instance_hash' => $instanceHash],
        ['$set' => [
            'http_proxies' => $httpProxies,
            'always_on'    => $alwaysOn,
            'init_script'  => $initScript,
            'prefs_updated_at' => time()
        ]]
    );

    // 2. Queue an apply-preferences job via RabbitMQ
    $work = [
        'action' => 'apply-preferences',
        'lab'    => $labName,
        'hash'   => $instanceHash,
        'user'   => $user->getUsername()
    ];

    $rabbit = new RabbitClient();
    $rabbit->sendToQueue('labs_jobs', $work);

    echo json_encode([
        'status'  => 'success',
        'message' => 'Apply job queued',
        'hash'    => $instanceHash,
        'queued'  => true
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => $e->getMessage()]);
}
