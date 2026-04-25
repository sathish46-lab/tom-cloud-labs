<?php
require_once __DIR__ . '/../../../src/load.php';
require_once __DIR__ . '/../../../src/lib/core/VPN.class.php';

header('Content-Type: application/json');
if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    echo json_encode(['status' => 'error', 'error' => 'Unauthorized']); exit;
}

$user = Session::getUser();
$db = DatabaseConnection::getDefaultDatabase();

$publicKey = $_POST['public_key'] ?? null;
$privateKey = $_POST['private_key'] ?? '';
$deviceName = $_POST['device_name'] ?? 'Unnamed Device';
$deviceType = $_POST['device_type'] ?? 'Mobile';
$selectedIp = $_POST['reallocate_ip'] ?? null;

if (empty($publicKey)) {
    echo json_encode(['status' => 'error', 'error' => 'Public Key is required.']); exit;
}

// 1. Provision the peer in the WireGuard Kernel
$response = VPN::request('wg', 'add_peer', [
    'public_key' => $publicKey,
    'email'      => $user->getEmail(),
    'ip'         => $selectedIp,
    'reserved'   => 'true', 
    'device'     => 'wg0'
]);

if (isset($response['result']) && $response['result'] !== false) {
    $assignedIp = $response['result'];
    
    // 2. Professional Upsert: Prevents duplicate cards
    $db->devices->updateOne(
        ['user_id' => $user->getUserId(), 'assigned_ip' => $assignedIp],
        ['$set' => [
            'email'       => $user->getEmail(),
            'device_name' => $deviceName,
            'device_type' => $deviceType,
            'public_key'  => $publicKey,
            'private_key' => $privateKey,
            'assigned_ip' => $assignedIp,
            'created_at'  => time(),
            'is_reserved' => true
        ]],
        ['upsert' => true]
    );

    echo json_encode(['status' => 'success', 'ip' => $assignedIp]);
} else {
    echo json_encode(['status' => 'error', 'error' => $response['error'] ?? 'Kernel Error']);
}
