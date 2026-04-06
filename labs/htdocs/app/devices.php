<?php
require_once __DIR__ . '/../src/load.php';
require_once __DIR__ . '/../src/lib/core/VPN.class.php';

if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    header("Location: /auth/signin.php"); exit;
}

$user = Session::getUser();
$db = DatabaseConnection::getDefaultDatabase();

// 1. Fetch live kernel data
$kernelData = VPN::request('wg', 'get_peers', ['device' => 'wg0']);
$livePeers = $kernelData['peers'] ?? [];

// 2. Fetch User reserved IPs from tom_labs_vpn
$dbResources = VPN::request('ip', 'all', ['device' => 'wg0']);
$allNodes = $dbResources['nodes'] ?? [];

$reservedIps = [];
foreach ($allNodes as $node) {
    if (isset($node['email']) && $node['email'] === $user->getEmail() && $node['allocated'] == false) {
        $reservedIps[] = $node;
    }
}

// 3. Fetch active device metadata from tom_labs_db
$activeMetadata = $db->devices->find(['user_id' => $user->getUserId()])->toArray();

$activeDevices = [];
foreach ($activeMetadata as $doc) {
    $device = json_decode(json_encode($doc), true);
    $device['status'] = 'offline';
    $device['origin_ip'] = 'No Connection';
    $device['rx'] = '0 KiB'; $device['tx'] = '0 KiB';

    foreach ($livePeers as $p) {
        if ($p['peer'] === $device['public_key']) {
            $handshake = $p['latest handshake'] ?? '';
            if (!empty($handshake) && (str_contains($handshake, 'second') || str_contains($handshake, 'minute'))) {
                $device['status'] = 'online';
            }
            $device['origin_ip'] = explode(':', $p['endpoint'] ?? 'No Connection')[0];
            $transfer = explode(',', $p['transfer'] ?? '0 B received, 0 B sent');
            $device['rx'] = trim(str_replace('received', '', $transfer[0]));
            $device['tx'] = isset($transfer[1]) ? trim(str_replace('sent', '', $transfer[1])) : '0 B';
            break;
        }
    }
    $activeDevices[] = $device;
}

Session::$pageTitle = "Devices";
Session::set('devices', $activeDevices);
Session::set('network_resources', $reservedIps);
Session::loadMaster();