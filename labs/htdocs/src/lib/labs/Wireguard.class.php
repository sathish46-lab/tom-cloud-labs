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
        $port = get_config('wireguard_endpoint_port') ?? 51820;
        $conf .= "PublicKey = $serverPubKey\n";
        $tunnelPrefix = get_config('tunnel_ip');
        $conf .= "Endpoint = {$tunnelPrefix}1:$port\n";
        $conf .= "AllowedIPs = 172.30.0.0/16, 10.30.0.0/16\n";
        $conf .= "PersistentKeepalive = 25\n";
        return $conf;
    }
}