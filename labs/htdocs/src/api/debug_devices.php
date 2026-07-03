<?php
require_once '../load.php';
require_once '../lib/core/VPN.class.php';

$user = Session::getUser();
if (!$user) { die("Not logged in"); }

$dbResources = VPN::request('ip', 'all', ['device' => 'wg0']);
$allNodes = $dbResources['nodes'] ?? [];

$reservedIps = [];
foreach ($allNodes as $node) {
    if (isset($node['email']) && $node['email'] === $user->getEmail() && $node['allocated'] == false) {
        $reservedIps[] = $node;
    }
}

echo "Reserved IPs count: " . count($reservedIps) . "\n";
print_r($reservedIps);
