<?php
require_once __DIR__ . '/../src/load.php';
require_once __DIR__ . '/../src/lib/core/VPN.class.php';

$user = Session::getUser();
$db = DatabaseConnection::getClient()->selectDatabase('tom_labs_db');

// 1. Fetch Standard VPN nodes
$apiResponse = VPN::request('ip', 'all', ['device' => 'wg0']); 
$allVpnResources = $apiResponse['nodes'] ?? [];

// Filter VPN resources by user email
$vpnResources = array_filter($allVpnResources, function($node) use ($user) {
    return isset($node['email']) && $node['email'] === $user->getEmail();
});

// Normalize VPN resources to ensure they have a 'service_type'
$normalizedVpn = array_map(function($item) {
    if (!isset($item['service_type'])) {
        $item['service_type'] = 'vpn_device';
    }
    return $item;
}, (array)$vpnResources);

// 2. Fetch Essential Lab IPs from our new inventory
$labIps = $db->lab_ips->find([
    'email' => $user->getEmail(),
    'status' => 'allocated'
]);
$myLabResources = iterator_to_array($labIps);

// 3. Fetch User Devices to get their assigned IPs
$devices = $db->devices->find(['user_id' => $user->getUserId()])->toArray();
$deviceResources = array_map(function($dev) {
    return [
        'ip_addr' => $dev['assigned_ip'],
        'service_type' => 'vpn_device',
        'label' => 'VPN Device: ' . $dev['device_name'],
        'allocated' => true
    ];
}, $devices);

// 4. Merge them into one list
$allResources = array_merge((array)$normalizedVpn, $myLabResources, $deviceResources);

// Final de-duplication by IP (preferring device entries)
$finalResources = [];
foreach ($allResources as $res) {
    if (isset($res['ip_addr'])) {
        $finalResources[$res['ip_addr']] = $res;
    }
}
$allResources = array_values($finalResources);

Session::$pageTitle = "My Network"; 
Session::set('network_resources', array_values($allResources));
Session::loadMaster();