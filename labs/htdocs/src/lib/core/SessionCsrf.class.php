<?php
/**
 * SessionCsrf — CSRF token generation and validation.
 * Extracted from Session.class.php to separate concerns.
 *
 * References Session::$csrfToken for backward compatibility.
 */
class SessionCsrf {

    /**
     * Generate or return existing CSRF token for the current session.
     */
    public static function token() {
        if (empty(Session::$csrfToken)) {
            if (empty($_SESSION['csrf_token'])) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }
            Session::$csrfToken = $_SESSION['csrf_token'];
        }
        return Session::$csrfToken;
    }

    /**
     * Validate a submitted CSRF token against the session token.
     */
    public static function validate($token) {
        if (empty($token) || empty($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Output a hidden CSRF input field for forms.
     */
    public static function field() {
        return '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars(self::token()) . '">';
    }
}
