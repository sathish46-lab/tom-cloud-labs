<?php

/**
 * Unified Configuration Loader
 * Handles reading from env.json and session.json with local fallbacks.
 */

function get_config($key) {
    static $config_cache = null;
    
    if ($config_cache === null) {
        $path = '/var/www/env.json';
        if (!file_exists($path)) {
            // Fallback to local workspace
            $localPath = __DIR__ . '/../../../../env.json';
            if (file_exists($localPath)) $path = $localPath;
            else {
                $config_cache = [];
                return null;
            }
        }
        $data = file_get_contents($path);
        $config_cache = json_decode($data, true) ?: [];
    }
    
    return isset($config_cache[$key]) ? $config_cache[$key] : null;
}

function get_session_config($key) {
    static $session_cache = null;

    if ($session_cache === null) {
        $path = '/var/www/session.json';
        if (!file_exists($path)) {
            // Fallback to local workspace
            $localPath = __DIR__ . '/../../../../session.json';
            if (file_exists($localPath)) $path = $localPath;
            else {
                $session_cache = [];
                return null;
            }
        }
        $data = @file_get_contents($path);
        if ($data === false) {
            error_log("CONFIG ERROR: Could not read session.json at " . $path);
            $session_cache = [];
        } else {
            $session_cache = json_decode($data, true) ?: [];
        }
    }
    
    return isset($session_cache[$key]) ? $session_cache[$key] : null;
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
