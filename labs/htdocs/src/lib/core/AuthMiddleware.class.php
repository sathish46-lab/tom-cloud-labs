<?php
/**
 * Centralized Authentication Middleware for API routes.
 *
 * Replaces the copy-pasted auth check pattern found in 155+ API files:
 *   if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) { ... exit; }
 *
 * Usage:
 *   $user = AuthMiddleware::requireAuth();        // 401 if not logged in
 *   $user = AuthMiddleware::requireAdmin();       // 403 if not superuser
 *       AuthMiddleware::requireCsrf();            // 403 if CSRF invalid
 *       AuthMiddleware::requireInternalToken();   // 401 if Bearer token invalid
 */
class AuthMiddleware {

    /**
     * Require authenticated session. Returns UserSession or sends 401 and exits.
     *
     * @return UserSession
     */
    public static function requireAuth(): UserSession {
        if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }
        return Session::getUser();
    }

    /**
     * Require superuser role. Returns UserSession or sends 403 and exits.
     * Also calls requireAuth() internally, so no need to call both.
     *
     * @return UserSession
     */
    public static function requireAdmin(): UserSession {
        $user = self::requireAuth();
        if ($user->getRole() !== Constants::GROUP_SUPERUSER) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Forbidden']);
            exit;
        }
        return $user;
    }

    /**
     * Require valid CSRF token for state-changing operations.
     * Sends 403 and exits if token is missing or invalid.
     */
    public static function requireCsrf(): void {
        CsrfProtection::require();
    }

    /**
     * Require internal Bearer token for service-to-service auth (AI worker, etc.).
     * Reads token from env.json ai_internal_token field.
     */
    public static function requireInternalToken(): void {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $authHeader = $headers['Authorization'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? '');

        $internalToken = self::getInternalToken();

        if (!$internalToken || $authHeader !== "Bearer $internalToken") {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }
    }

    /**
     * Check if user is authenticated (non-destructive, returns bool).
     */
    public static function isAuthenticated(): bool {
        return Session::getAuthStatus() === Constants::STATUS_LOGGEDIN;
    }

    /**
     * Get current user if authenticated, or null.
     */
    public static function getUser(): ?UserSession {
        return self::isAuthenticated() ? Session::getUser() : null;
    }

    /**
     * Read the internal API token from env.json (cached per request).
     */
    private static function getInternalToken(): ?string {
        static $token = null;
        static $loaded = false;

        if (!$loaded) {
            $loaded = true;
            $envPath = __DIR__ . '/../../../../env.json';
            if (file_exists($envPath)) {
                $env = json_decode(file_get_contents($envPath), true);
                $token = $env['ai_internal_token'] ?? null;
            }
        }

        return $token;
    }
}
