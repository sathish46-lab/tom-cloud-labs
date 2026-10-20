<?php
/**
 * SessionAuth — Authentication and user session logic.
 * Extracted from Session.class.php to separate concerns.
 *
 * References Session's static properties ($userSession, $authStatus)
 * for backward compatibility.
 */
class SessionAuth {

    /**
     * Get stored user session object.
     */
    public static function getUserSession() {
        return Session::$userSession;
    }

    /**
     * Get the user model from UserSession.
     */
    public static function getUser() {
        if (!Session::$userSession) {
            return null;
        }
        if (method_exists(Session::$userSession, 'getUser')) {
            return Session::$userSession->getUser();
        }
        return null;
    }

    /**
     * Return current auth status.
     */
    public static function getAuthStatus() {
        return Session::$authStatus;
    }

    /**
     * Check if user is authenticated.
     */
    public static function isAuthenticated() {
        return Session::$authStatus === Constants::STATUS_LOGGEDIN;
    }

    /**
     * Get avatar for the current user with fallback system.
     */
    public static function getAvatar() {
        $user = self::getUser();

        $defaultAvatars = [
            Session::cdn3('avatars/avatar1.png'), Session::cdn3('avatars/avatar2.png'),
            Session::cdn3('avatars/avatar3.png'), Session::cdn3('avatars/avatar4.png'),
            Session::cdn3('avatars/avatar5.png'), Session::cdn3('avatars/avatar6.png'),
            Session::cdn3('avatars/avatar7.png'), Session::cdn3('avatars/avatar8.png'),
            Session::cdn3('avatars/avatar9.png'), Session::cdn3('avatars/avatar10.png')
        ];

        $avatarUrl = $user?->getAvatarUrl() ?? $_SESSION['user_avatar'] ?? null;

        if (!empty($avatarUrl)) {
            if (strpos($avatarUrl, 'http') === 0 || strpos($avatarUrl, '/system/') === 0) {
                return htmlspecialchars($avatarUrl);
            }
            if (file_exists($_SERVER['DOCUMENT_ROOT'] . $avatarUrl)) {
                return htmlspecialchars($avatarUrl);
            }
        }

        $seedValue = $user ? $user->getUserId() : session_id();
        $index = abs(crc32($seedValue)) % count($defaultAvatars);
        return $defaultAvatars[$index];
    }

    /**
     * Get avatar for any specific username.
     */
    public static function getAvatarForUsername($username) {
        $currentUser = self::getUser();
        $currentUsername = $currentUser ? $currentUser->getUsername() : null;
        if (empty($username) || ($currentUsername && $username === $currentUsername)) {
            return self::getAvatar();
        }

        static $avatarCache = [];
        if (isset($avatarCache[$username])) {
            return $avatarCache[$username];
        }

        $defaultAvatars = [
            Session::cdn3('avatars/avatar1.png'), Session::cdn3('avatars/avatar2.png'),
            Session::cdn3('avatars/avatar3.png'), Session::cdn3('avatars/avatar4.png'),
            Session::cdn3('avatars/avatar5.png'), Session::cdn3('avatars/avatar6.png'),
            Session::cdn3('avatars/avatar7.png'), Session::cdn3('avatars/avatar8.png'),
            Session::cdn3('avatars/avatar9.png'), Session::cdn3('avatars/avatar10.png')
        ];

        try {
            $db = DatabaseConnection::getDefaultDatabase();
            $userDoc = $db->users->findOne(['username' => $username]);
            if (!$userDoc && strpos($username, '@') !== false) {
                $userDoc = $db->users->findOne(['email' => $username]);
            }

            if ($userDoc && !empty($userDoc['avatar_url'])) {
                $avatarUrl = $userDoc['avatar_url'];
                if (strpos($avatarUrl, 'http') === 0 || strpos($avatarUrl, '/system/') === 0 || file_exists($_SERVER['DOCUMENT_ROOT'] . $avatarUrl)) {
                    $avatarCache[$username] = htmlspecialchars($avatarUrl);
                    return $avatarCache[$username];
                }
            }

            $seedValue = $userDoc ? ($userDoc['user_id'] ?? $userDoc['_id'] ?? $username) : $username;
            $index = abs(crc32((string)$seedValue)) % count($defaultAvatars);
            $avatarCache[$username] = $defaultAvatars[$index];
            return $avatarCache[$username];
        } catch (\Exception $e) {
            $index = abs(crc32((string)$username)) % count($defaultAvatars);
            return $defaultAvatars[$index];
        }
    }

    /**
     * Generate a unique CSS hue-rotate for default avatars.
     */
    public static function getAvatarStyle() {
        $user = self::getUser();

        if ($user && $user->getAvatarUrl()) {
            $url = $user->getAvatarUrl();
            if (strpos($url, 'http') === 0 || strpos($url, '/system/') === 0 || strpos($url, '/uploads/') === 0) {
                return "";
            }
        }

        $seed = $user ? $user->getUserId() : session_id();
        $hue = abs(crc32($seed)) % 360;
        return "filter: hue-rotate({$hue}deg);";
    }

    /**
     * Refresh auth cookies for the logged-in session — called once per request
     * from load.php, after WebAPI::initSession() has resolved authentication.
     *
     * Owns the "/home resets my session" behavior: at login,
     * session_regenerate_id() emits PHP's own host-only Set-Cookie carrying the
     * NEW session id, while the old bootstrap re-issued a Domain cookie still
     * carrying the OLD id — two same-named cookies with different values, so
     * PHP flipped between them on every request depending on which the browser
     * sent first (session appears to reset on each page).
     *
     * Single source of truth:
     *  - writes BOTH PHPSESSID variants (host-only + Domain) with the SAME
     *    current id every request — they converge immediately and can never
     *    diverge again;
     *  - slides the session_token ("remember me") expiry while active;
     *  - throttled to one write per 5 minutes, refreshes that token's
     *    last_activity server-side — a proper in-place update instead of ever
     *    pushing duplicate token entries.
     *
     * Server-side policy (enforced in WebAPI::initSession): 24 h idle timeout
     * and a 30-day absolute lifetime for both the PHP session and the token.
     */
    public static function refreshAuthCookies(): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        if (($_SESSION['auth_status'] ?? null) !== Constants::STATUS_LOGGEDIN) {
            return;
        }

        $lifetime = get_session_lifetime();
        $params   = session_get_cookie_params();
        $expires  = time() + $lifetime;
        $secure   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
                    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
                    || !empty($params['secure']);
        $domain   = get_session_domain();
        $common   = [
            'expires'  => $expires,
            'path'     => $params['path'] ?: '/',
            'secure'   => $secure,
            'httponly' => !empty($params['httponly']),
            'samesite' => $params['samesite'] ?: 'Lax',
        ];

        // session_token — slide expiry while the user is active. Emitted
        // BEFORE the PHPSESSID lines: existing callers parse the LAST
        // Set-Cookie line of a response for the session id, so a trailing
        // session_token emission would hide PHPSESSID from them (browsers
        // are order-independent per name+domain+path).
        $token = (string) ($_COOKIE['session_token'] ?? '');
        if ($token !== '') {
            setcookie('session_token', $token, [
                'expires'  => $expires,
                'path'     => '/',
                'domain'   => $domain,
                'secure'   => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        // PHPSESSID — identical value in both variants (host-only + Domain):
        // whichever cookie the browser holds, they always carry the same id.
        // The Domain variant is emitted last so it is the final Set-Cookie
        // line in the response (kept for last-line parsers; same value).
        setcookie(session_name(), session_id(), $common);
        if ($domain !== '') {
            setcookie(session_name(), session_id(), ['domain' => $domain] + $common);
        }

        if ($token === '') {
            return;
        }

        // Throttled last_activity touch: one DB write per 5 min per session.
        if ((int) ($_SESSION['token_touch'] ?? 0) > time() - 300) {
            return;
        }
        $_SESSION['token_touch'] = time();
        try {
            DatabaseConnection::getDefaultDatabase()->users->updateOne(
                ['session_tokens.token_id' => hash('sha256', $token)],
                ['$set' => ['session_tokens.$.last_activity' => time()]]
            );
        } catch (\Throwable $e) {
            error_log('refreshAuthCookies touch failed: ' . $e->getMessage());
        }
    }

    /**
     * Rotate the session id while keeping the session data (anti-hijacking):
     * the old id is destroyed server-side, $_SESSION (auth, CSRF, …) carries
     * over to the new id, and both PHPSESSID cookie variants are re-emitted
     * converged — the user stays logged in, just under a fresh id.
     *
     * Must run before any output (headers).
     */
    public static function rotateSessionId(): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        if (($_SESSION['auth_status'] ?? null) !== Constants::STATUS_LOGGEDIN) {
            return;
        }
        session_regenerate_id(true);
        self::refreshAuthCookies();
    }
}
