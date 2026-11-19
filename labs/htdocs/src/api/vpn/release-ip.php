<?php
require_once __DIR__ . '/../../../src/load.php';
require_once __DIR__ . '/../../../src/lib/core/VPN.class.php';
require_once __DIR__ . '/../../../src/lib/labs/IPManager.class.php';

$user = AuthMiddleware::requireAuth();

$data = json_decode(file_get_contents('php://input'), true);
$ipAddr = $data['ip'] ?? null;
$type = $data['type'] ?? 'vpn';

$ipManager = new \TomLabs\Labs\IPManager();

if ($type === 'essential_lab') {
    // Release lab IP
    $ipManager->release($ipAddr, $user->getEmail());
    
    // Clean up machine_labs
    $db = DatabaseConnection::getClient()->selectDatabase('tom_labs_db');
    $db->machine_labs->deleteMany([
        'deploy.internal_ip' => $ipAddr, 
        'deploy.email' => $user->getEmail()
    ]);

    api_json(['success' => true], 200);
} else {
    // VPN device release
    $db = DatabaseConnection::getDefaultDatabase();
    
    // Check if IP is used by a device
    $deviceExists = $db->devices->findOne(['assigned_ip' => $ipAddr, 'user_id' => $user->getUserId()]);
    if ($deviceExists) {
api_json(['success' => false, 'error' => 'Delete the device first.'], 400);
    }

    // Release from WireGuard kernel
    $response = VPN::request('ip', 'unreserve', ['ip' => $ipAddr, 'email' => $user->getEmail()]);
    
    if (!$response || empty($response['result'])) {
        $response = VPN::request('ip', 'unreserve', ['ip' => $ipAddr, 'email' => $user->getUsername()]);
    }

    if ($response && $response['result']) {
        $ipManager->release($ipAddr, $user->getEmail());
        $db->devices->deleteMany(['assigned_ip' => $ipAddr, 'user_id' => $user->getUserId()]);
        api_json(['success' => true], 200);
    } else {
        api_json(['success' => false, 'error' => 'Failed to release VPN IP'], 400);
    }
}
