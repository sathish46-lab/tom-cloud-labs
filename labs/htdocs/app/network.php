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

// 2. Fetch Essential Lab IPs from our new inventory
$labIps = $db->lab_ips->find([
    'email' => $user->getEmail(),
    'status' => 'allocated'
]);

$myLabResources = iterator_to_array($labIps);

// 3. Merge them into one list
$allResources = array_merge((array)$vpnResources, $myLabResources);


// Normalize VPN resources to ensure they have a 'service_type'
$normalizedVpn = array_map(function($item) {
    if (!isset($item['service_type'])) {
        $item['service_type'] = 'vpn_device';
    }
    return $item;
}, (array)$vpnResources);

// Merge the normalized list with your lab resources
$allResources = array_merge($normalizedVpn, $myLabResources);

Session::$pageTitle = "My Network"; 
Session::set('network_resources', array_values($allResources));
Session::loadMaster();