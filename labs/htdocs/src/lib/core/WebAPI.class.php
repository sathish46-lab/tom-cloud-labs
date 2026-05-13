<?php

class WebAPI {
    // public function __construct() {
    //     if (System::getOS() <= 2) { throw new UnsupportedEnvironmentException(); }
    //     if (!extension_loaded('mongodb')) { die("Unable to load mongodb.so"); }

    //     $build = 'beta'; 
    //     if (isset($_SERVER['HTTP_HOST']) && in_array($_SERVER['HTTP_HOST'], get_config('allowed_hosts'))) {
    //         Session::set('php', '/usr/bin/php');
    //     }
    //     Session::$environment = $build;
    //     DatabaseConnection::getClient(); 
    // }
    public function __construct() {
        if (System::getOS() <= 2) { throw new UnsupportedEnvironmentException(); }
        if (!extension_loaded('mongodb')) { die("Unable to load mongodb.so"); }

        // DYNAMIC ENVIRONMENT DETECTION
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($host === 'labslocal.tomweb.fun' || $host === 'localhost' || $host === 'dev.awshosting.in') {
            Session::$environment = 'local';
        } else {
            Session::$environment = 'beta';
        }

        DatabaseConnection::getClient(); 
    }

    public function initSession() {
    global $__start;

    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) { 
        session_start(); 
    }

    // Manual Session Expiration Check (Crucial for Production GC reliability)
    $lifetime = get_session_lifetime();

    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $lifetime)) {
        UserSession::logout();
        return; // Stop processing to ensure the user stays logged out for this request
    }
    $_SESSION['last_activity'] = time();

    // Prioritize active PHP Session, fallback to persistent Cookie
    $username = $_SESSION['username'] ?? $_COOKIE['username'] ?? null;

    if ($username) {
        Session::$userSession = new UserSession($username);
        if (Session::getUser() !== null) {
            Session::$authStatus = \Constants::STATUS_LOGGEDIN;
        } else {
            UserSession::logout(); 
        }
    } else {
        Session::$authStatus = \Constants::STATUS_DEFAULT;
    }
}
}