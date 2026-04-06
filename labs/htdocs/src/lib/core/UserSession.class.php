<?php

class UserSession {
    private $database = null;
    private $usersCollection = null;
    public $user = null;

    private function __init() {
        $this->database = DatabaseConnection::getDefaultDatabase();
        $this->usersCollection = $this->database->users;
    }


public function __construct($username = null, $sessionHash = null) {
    $this->__init();
    
    if ($username !== null) {
        // Find the email for the user
        $userDoc = $this->usersCollection->findOne([
            '$or' => [['username' => $username], ['email' => $username]]
        ]);
        
        if ($userDoc) {
            // Pass the email to the User constructor as per your screenshot
            $this->user = new User($userDoc['email']);
            
            // Now you can call ANYTHING!
            $log = "User: " . $this->user->getUsername() . "\n";
            $log .= "Bio: " . $this->user->getBio() . "\n"; 
            $log .= "Avatar: " . $this->user->getAvatarUrl();

            // Console::log(indent($log, 8)); 
        }
    }
}

/**
 * Required for Session::getUser()
 */
public function getUser() {
    return $this->user;
}

    /**
     * Authenticate local users and set recovery cookies.
     */
    public static function authenticate($email, $password) {
    $instance = new self();
    try {
        $user = $instance->usersCollection->findOne(['email' => $email]);

        if ($user && isset($user['password']) && password_verify($password, $user['password'])) {
            if (!isset($user['is_verified']) || $user['is_verified'] === false) {
                Session::set('login_error', "Please verify your email.");
                return false;
            }
            
            $username = $user['username']; 
            $_SESSION['auth_status'] = \Constants::STATUS_LOGGEDIN;
            $_SESSION['username']    = $username;
            
            // Set cookies with 24-hour expiration to match session lifetime
            $isSecure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
            setcookie('username', $username, [
                'expires'  => time() + 86400, // 24 hours
                'path'     => '/',
                'domain'   => '', 
                'secure'   => $isSecure,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            
            return true;
        }
    } catch (Exception $e) {
        error_log("Auth Error: " . $e->getMessage());
    }
    return false;
}
    public static function logout() {
        Session::$authStatus = null;
        $_SESSION = [];
        // Clear all cookies
        setcookie('username', '', time() - 3600, "/");
        setcookie('sessionHash', '', time() - 3600, "/");
        setcookie('sessionID', '', time() - 3600, "/");
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
}