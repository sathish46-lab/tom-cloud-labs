<?php
require_once __DIR__ . '/../../../src/load.php';

if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    die("Unauthorized");
}

$db = DatabaseConnection::getDefaultDatabase();
$rawId = $_GET['id']; 

// Professional ID extraction
$deviceId = is_array($rawId) ? ($rawId['$oid'] ?? null) : $rawId;

if (!$deviceId) {
    die("Error: Invalid ID format.");
}

try {
    $device = $db->devices->findOne(['_id' => new MongoDB\BSON\ObjectId((string)$deviceId)]);
} catch (Exception $e) {
    die("Error: Invalid Device ID - " . $e->getMessage());
}

if (!$device || (string)$device['user_id'] !== (string)Session::getUser()->getUserId()) {
    die("Error: Device not found or access denied.");
}

// Build the Professional WireGuard Config
$serverPubKey = get_config('wireguard_public_key');
$port = get_config('wireguard_endpoint_port') ?? 51820;
$endpoint = "vpn.awshosting.in:$port";

$config = "[Interface]\n";
$config .= "PrivateKey = " . ($device['private_key'] ?: '<PASTE_PRIVATE_KEY>') . "\n";
// The user gets an IP in the 172.30 range
$config .= "Address = " . $device['assigned_ip'] . "/32\n"; 
$config .= "DNS = 1.1.1.1\n\n";

$config .= "[Peer]\n";
$config .= "PublicKey = $serverPubKey\n";
$config .= "Endpoint = $endpoint\n";

// IMPORTANT: route the whole VPN range
$config .= "AllowedIPs = 172.30.0.0/16\n";
$config .= "PersistentKeepalive = 25\n";

// Clear output buffers to prevent corruption
if (ob_get_length()) ob_end_clean();

header('Content-Type: application/config'); // Professional WireGuard MIME
header('Content-Disposition: attachment; filename="' . str_replace(' ', '_', $device['device_name']) . '.conf"');
header('Pragma: no-cache');
header('Expires: 0');

echo $config;
exit;
