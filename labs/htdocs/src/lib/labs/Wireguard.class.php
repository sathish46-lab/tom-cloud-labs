<?php
namespace TomLabs\Labs;

class Wireguard {
    /**
     * Generates a standard WireGuard config for the container peer
     */
    public static function generateConfig($clientIP, $privateKey, $serverPubKey) {
        $conf = "[Interface]\n";
        $conf .= "PrivateKey = $privateKey\n";
        $conf .= "Address = $clientIP/32\n";
        $conf .= "MTU = 1420\n\n";
        $conf .= "[Peer]\n";
        $conf .= "PublicKey = $serverPubKey\n";
        $conf .= "Endpoint = 172.30.0.1:51820\n";
        $conf .= "AllowedIPs = 172.30.0.0/16, 10.30.0.0/16\n";
        $conf .= "PersistentKeepalive = 25\n";
        return $conf;
    }
}