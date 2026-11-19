<?php
require_once __DIR__ . '/../../../src/load.php';

$user = AuthMiddleware::requireAuth();

$deviceId = $_GET['id'] ?? '';

if (empty($deviceId)) {
api_json(['status' => 'error', 'error' => 'Missing device ID'], 400);
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
    $port = get_config('wireguard_endpoint_port') ?? 51820;
    $endpoint = get_config('wireguard_endpoint') ?? "vpn.tomweb.in:$port";
    $assignedIp = $deviceData['assigned_ip'];
    $privKey = $deviceData['private_key'] ?? '';
    
    $displayPrivKey = $privKey ?: "<PASTE_YOUR_PRIVATE_KEY>";
    
    $config = "[Interface]\n";
    $config .= "PrivateKey = $displayPrivKey\n";
    $config .= "Address = $assignedIp/32\n";
    $config .= "DNS = 1.1.1.1\n\n";
    $config .= "[Peer]\n";
    $config .= "PublicKey = $serverPubKey\n";
    $tunnelPrefix = get_config('tunnel_ip');
    $baseSubnet = preg_replace('/\.0\.$/', '.0.0/16', $tunnelPrefix);
    $config .= "AllowedIPs = $baseSubnet\n";
    $config .= "Endpoint = $endpoint\n";
    $config .= "PersistentKeepalive = 25";

    api_json([
        'status' => 'success',
        'data'   => [
            'config_raw' => $config,
            'device_name' => $deviceData['device_name'],
            'assigned_ip' => $assignedIp
        ]
    ], 200);

} catch (Exception $e) {
    api_json(['status' => 'error', 'error' => errors_from_exception($e, 'vpn/connection_info')['error']], 500);
}
