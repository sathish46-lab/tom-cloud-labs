<?php

/**
 * Unified Configuration Loader
 * Handles reading from env.json and session.json with local fallbacks.
 */

function get_config($key) {
    $path = '/var/www/env.json';

    if (!file_exists($path)) {
        // Fallback to local workspace
        $localPath = __DIR__ . '/../../../../env.json';
        if (file_exists($localPath)) $path = $localPath;
        else return null;
    }
    
    $data = file_get_contents($path);
    $array = json_decode($data, true);
    return isset($array[$key]) ? $array[$key] : null;
}

function get_session_config($key) {
    $path = '/var/www/session.json';

    if (!file_exists($path)) {
        // Fallback to local workspace
        $localPath = __DIR__ . '/../../../../session.json';
        if (file_exists($localPath)) $path = $localPath;
        else {
            error_log("CONFIG ERROR: session.json not found at " . $path . " or fallback " . $localPath);
            return null;
        }
    }

    $data = @file_get_contents($path);
    if ($data === false) {
        error_log("CONFIG ERROR: Could not read session.json at " . $path);
        return null;
    }
    $array = json_decode($data, true);
    if ($array === null) {
        error_log("CONFIG ERROR: session.json is not valid JSON at " . $path);
        return null;
    }
    return isset($array[$key]) ? $array[$key] : null;
}

/**
 * Detect if we are in a local development environment
 */
function is_local() {
    $host = explode(':', $_SERVER['HTTP_HOST'] ?? '')[0];
    $local_hosts = ['localhost', '127.0.0.1', 'dev.tomweb.in'];
    return in_array($host, $local_hosts);
}

/**
 * Get the appropriate session lifetime based on environment
 */
function get_session_lifetime() {
    return is_local() ? 
        (get_session_config('lifetime_local') ?? 20) : 
        (get_session_config('lifetime_production') ?? 86400);
}

/**
 * Get the appropriate cookie domain based on environment
 */
function get_session_domain() {
    return is_local() ? 
        (get_session_config('domain_local') ?? '') : 
        (get_session_config('domain_production') ?? '');
}
