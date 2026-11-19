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

/**
 * Emit a pretty-printed, consistent JSON API response and exit.
 *
 * Every API endpoint should use this instead of echoing json_encode()
 * directly, so all responses share:
 *   - Content-Type: application/json; charset=utf-8
 *   - JSON_PRETTY_PRINT (human-readable multi-line output)
 *   - JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
 *   - An explicit HTTP status code
 *
 * @param array $payload   The response body (must be an array)
 * @param int   $status    HTTP status code (default 200)
 */
function api_json(array $payload, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Emit a standardized JSON error response and exit.
 *
 * Payload shape: { status: "error", error: "<message>", ...$extra }
 *
 * @param string $message  Human-readable error message (non-technical)
 * @param int    $status   HTTP status code (default 400)
 * @param array  $extra    Additional payload keys (e.g. ['ref' => 'ERR-...'])
 */
function api_error(string $message, int $status = 400, array $extra = []): void {
    api_json(array_merge(['status' => 'error', 'error' => $message], $extra), $status);
}


