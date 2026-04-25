<?php
// Define the actual filesystem path to your lib folder
$libPath = "/var/www/vpn-api/api/lib";

require_once($libPath . "/Database.class.php");
require_once($libPath . "/Wireguard.class.php");
require_once($libPath . "/IPNetwork.class.php");
require_once($libPath . "/Signup.class.php");

$interface = "wg0"; 
echo "--- Rebuilding VPN Database Source of Truth ---" . PHP_EOL;

try {
    // Manually initiate connection to ensure connectivity
    $db = Database::getConnection();
    
    // 1. Ensure a default VPN admin user exists for API operations
    $count = $db->auth->countDocuments(['active' => 1]);
    if($count < 1){
        echo "Initializing default VPN auth user..." . PHP_EOL;
        new Signup("sna_vpn_user", "09b693f6-e6b4-4cdc-a7c6-14a337fae61d", "support@selfmade.ninja", 1);
    }

    $wg = new Wireguard($interface);
    $cidr = $wg->getCIDR(); // Extracts from /etc/wireguard/wg0.conf
    
    if (!$cidr) {
        throw new Exception("Unable to find CIDR. Check if $interface.conf exists in /etc/wireguard/");
    }

    $ip = new IPNetwork($cidr, $interface);
    echo "Network detected: $cidr" . PHP_EOL;

    // 2. Create the temporary IP list file via nmap
    echo "Scanning network range (nmap)..." . PHP_EOL;
    $ip->constructNetworkFile();

    // 3. Purge the 'networks' collection and sync fresh nodes
    echo "Syncing fresh IPs to MongoDB..." . PHP_EOL;
    $result = $ip->syncNetworkFile();
    
    echo "SUCCESS: Database recreated." . PHP_EOL;
    echo "Total IP nodes inserted: " . $result->getInsertedCount() . PHP_EOL;

} catch (Exception $e) {
    echo "FATAL ERROR: " . $e->getMessage() . PHP_EOL;
}