<?php
/**
 * POST /api/instance/preferences_save
 * Save preferences (http_proxies, always_on, init_script) to MongoDB without triggering a redeploy.
 */
require_once __DIR__ . '/../../../src/load.php';

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

// Validate the hash matches
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

    // Sanitize always_on
    $alwaysOn = !empty($input['always_on']);

    // Sanitize init_script
    $initScript = isset($input['init_script']) ? (string)$input['init_script'] : '#!/bin/bash';

    $col->updateOne(
        ['instance_hash' => $instanceHash],
        ['$set' => [
            'http_proxies' => $httpProxies,
            'always_on'    => $alwaysOn,
            'init_script'  => $initScript,
            'prefs_updated_at' => time()
        ]]
    );

    echo json_encode([
        'status' => 'success',
        'message' => 'Preferences saved',
        'hash' => $instanceHash
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => $e->getMessage()]);
}
