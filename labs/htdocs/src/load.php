<?php
/**
 * Main Loader: Handles environment, vendors, and core libraries.
 */
require_once __DIR__ . '/utils/config.php';

// 1. Start session first (without using any classes yet)
if (session_status() === PHP_SESSION_NONE) {
    $lifetime = get_session_lifetime();
    ini_set('session.gc_maxlifetime', $lifetime);
    ini_set('session.cookie_lifetime', $lifetime);
    
    // Fix Ubuntu Cron Job Session Deletion Bug
    $sessionPath = '/var/cache/labs/sessions';
    if (!is_dir($sessionPath)) {
        @mkdir($sessionPath, 0755, true);
    }
    ini_set('session.save_path', $sessionPath);
    
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
    $lifetime = get_session_lifetime();
    $domain = get_session_domain();

    setcookie(
        session_name(),
        session_id(),
        [
            'expires'  => time() + $lifetime,
            'path'     => $cookieParams['path'],
            'domain'   => $domain,
            'secure'   => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || 
                          (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https'),
            'httponly' => true,
            'samesite' => 'Lax'
        ]
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