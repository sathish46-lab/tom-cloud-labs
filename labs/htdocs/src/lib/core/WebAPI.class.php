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
        Session::$environment = is_local() ? 'local' : 'beta';

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

    // Prioritize active PHP Session
    $username = $_SESSION['username'] ?? null;
    $sessionToken = $_COOKIE['session_token'] ?? null;

    if ($username) {
        // Already active in current session
        Session::$userSession = new UserSession($username);
        if (Session::getUser() !== null) {
            Session::$authStatus = \Constants::STATUS_LOGGEDIN;
        } else {
            UserSession::logout(); 
        }
    } elseif ($sessionToken) {
        // Attempt Token Auto-Login
        $db = DatabaseConnection::getDefaultDatabase();
        $user = $db->users->findOne(['session_tokens' => $sessionToken]);
        
        if ($user && isset($user['username'])) {
            // Token is valid, rebuild session
            $_SESSION['username'] = $user['username'];
            $_SESSION['auth_status'] = \Constants::STATUS_LOGGEDIN;
            
            Session::$userSession = new UserSession($user['username']);
            Session::$authStatus = \Constants::STATUS_LOGGEDIN;
        } else {
            // Token is invalid or revoked, forcefully log out
            UserSession::logout();
        }
    } else {
        Session::$authStatus = \Constants::STATUS_DEFAULT;
    }
}
}