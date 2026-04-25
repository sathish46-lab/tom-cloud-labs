<?php
require_once __DIR__ . '/../../../src/load.php';

header('Content-Type: application/json');

if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    echo json_encode(['status' => 'error', 'error' => 'Unauthorized']);
    exit;
}

$user = Session::getUser();
$deviceId = $_GET['id'] ?? '';

if (empty($deviceId)) {
    echo json_encode(['status' => 'error', 'error' => 'Missing device ID']);
    exit;
}

try {
    $db = DatabaseConnection::getDefaultDatabase();
    
    // Convert string ID to MongoDB ObjectId if necessary, or use as is if it's a string
    try {
        $oid = new \MongoDB\BSON\ObjectId($deviceId);
        $filter = ['_id' => $oid];
    } catch (Exception $e) {
        $filter = ['_id' => $deviceId];
    }

    $deviceData = $db->devices->findOne($filter);

    if (!$deviceData) {
        throw new Exception('Device not found');
    }

    // Security check: Ensure the device belongs to the user
    if ((string)$deviceData['user_id'] !== (string)$user->getUserId()) {
        throw new Exception('Unauthorized access to device');
    }

    $serverPubKey = get_config('wireguard_public_key');
    $endpoint = "vpn.awshosting.in:51820";
    $assignedIp = $deviceData['assigned_ip'];
    $privKey = $deviceData['private_key'] ?? '';
    
    $displayPrivKey = $privKey ?: "<PASTE_YOUR_PRIVATE_KEY>";
    
    $config = "[Interface]\n";
    $config .= "PrivateKey = $displayPrivKey\n";
    $config .= "Address = $assignedIp/32\n";
    $config .= "DNS = 1.1.1.1\n\n";
    $config .= "[Peer]\n";
    $config .= "PublicKey = $serverPubKey\n";
    $config .= "AllowedIPs = 172.30.0.0/16\n";
    $config .= "Endpoint = $endpoint\n";
    $config .= "PersistentKeepalive = 25";

    echo json_encode([
        'status' => 'success',
        'data'   => [
            'config_raw' => $config,
            'device_name' => $deviceData['device_name'],
            'assigned_ip' => $assignedIp
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => $e->getMessage()]);
}
