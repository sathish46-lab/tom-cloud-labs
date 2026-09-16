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
}
