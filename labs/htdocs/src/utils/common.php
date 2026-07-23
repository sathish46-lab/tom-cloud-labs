<?php

/**
 * Takes an iterator or object, and convert it into an array.
 */
function purify_array($obj){
    return json_decode(json_encode($obj), true);
}

/**
 * Base64 URL Encoding
 */
function base64_urlencode($string) {
    return rtrim(strtr(base64_encode($string), '+/', '-_'), '=');
}

/**
 * Standard cURL Helper for API calls
 * Updated to check if cURL is installed
 */
function http($url, $params = []) {
    if (!function_exists('curl_init')) {
        error_log("CRITICAL: cURL extension is not installed.");
        return (object)['error' => 'cURL missing'];
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if ($params) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    }
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response);
}


