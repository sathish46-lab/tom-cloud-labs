<?php

require_once __DIR__ .'/api/lib/Wireguard.class.php';
require_once __DIR__ .'/api/lib/IPNetwork.class.php';
require_once __DIR__ .'/api/lib/Signup.class.php';

// TERMINAL FIX: Allow running from CLI without ?if=wg0
$interface = $_GET['if'] ?? $argv[1] ?? 'wg0';
$token = $_GET['token'] ?? $argv[2] ?? '09b693f6-e6b4-4cdc-a7c6-14a337fae61d';

if(!$interface){
    die("Usage: php syncnetwork.php <interface_name> [token]\n");
}

if($token != '09b693f6-e6b4-4cdc-a7c6-14a337fae61d'){
    die("Not authorized\n");
}

$db = Database::getConnection();

// Check if vpn auth exists
$count = $db->auth->countDocuments(['active' => 1]);
if($count < 1){
    new Signup("tom_labs_vpn", $token, "sathishp4223@gmail.com", 1);
}

$wg = new Wireguard($interface);
echo "Network CIDR: " . $wg->getCIDR() . "\n";

$ip = new IPNetwork($wg->getCIDR(), $wg->device);
echo "Generating IP Table for " . $wg->device . "...\n";

// This generates the actual Mongo documents in tom_labs_vpn/networks
$ip->constructNetworkFile($wg->device);

try {
    $result = $ip->syncNetworkFile($wg->device);
    print_r($result);
    echo "\n[✓] Network table generated and synced successfully.\n";
} catch (Exception $e) {
    echo "\n[!] Network already synced or Error: " . $e->getMessage() . "\n";
}