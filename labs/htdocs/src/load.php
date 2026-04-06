<?php
/**
 * Main Loader: Handles environment, vendors, and core libraries.
 */

// 1. Start session first (without using any classes yet)
if (session_status() === PHP_SESSION_NONE) {
    // Set session to last 24 hours (86400 seconds)
    ini_set('session.gc_maxlifetime', 86400);
    ini_set('session.cookie_lifetime', 86400);
    
    // Optional: increase probability to clean up old sessions
    ini_set('session.gc_probability', 1);
    ini_set('session.gc_divisor', 100);
    
    session_start();
}

// 2. Load Composer and Libraries FIRST (before using any classes)
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/utils/common.php'; 
require_once __DIR__ . '/lib/load.php';

// 3. NOW we can use Constants class - regenerate session cookie
if (isset($_SESSION['auth_status']) && $_SESSION['auth_status'] === Constants::STATUS_LOGGEDIN) {
    $cookieParams = session_get_cookie_params();
    setcookie(
        session_name(),
        session_id(),
        time() + 86400,
        $cookieParams['path'],
        $cookieParams['domain'],
        $cookieParams['secure'],
        $cookieParams['httponly']
    );
}

$composerAutoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}
/**
 * Global Helpers
 */
if (!function_exists('require_ui_component')) {
    function require_ui_component($file) {
        $path = __DIR__ . "/ui/" . $file . '.php';
        if (!file_exists($path)) {
            throw new Exception("UI Component not found: " . $file);
        }
        require_once $path;
    }
}
// 3. Sync PHP Session to Session Class (Safe now because Session class is loaded)
if (isset($_SESSION['auth_status'])) {
    Session::$authStatus = $_SESSION['auth_status'];
}



if (!function_exists('cdn')) {
    function cdn($url) {
        return $url;
    }
}