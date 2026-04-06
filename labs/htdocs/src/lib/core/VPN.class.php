<?php

class VPN {
    
    public static function request($namespace, $method, $params = []) {
        // Fetch config
        $config_json = file_get_contents('/var/www/env.json');
        $config = json_decode($config_json, true);
        
        $api_url = $config['vpn_url'] ?? "https://vpns.tomweb.fun/api";
        $url = $api_url . "/$namespace/$method";
        
        $apiKey = $config['api_secret'] ?? '';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        
        // 3. Add the API Key to the headers for security
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-API-KEY: ' . $apiKey,
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        // Disable SSL verification for self-signed certs
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        
        error_log("VPN Request: URL: $url, Params: " . json_encode($params) . ", HTTP Code: $httpCode, Curl Error: $curlError, Response: $response");

        curl_close($ch);

        return json_decode($response, true);
    }
}