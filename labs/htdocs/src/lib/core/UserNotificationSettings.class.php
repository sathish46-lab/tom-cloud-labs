<?php

/**
 * Per-user notification settings — stored on the MongoDB `users` document
 * field `notification_settings`.
 *
 * Defaults:
 *   - email:    false (Gmail delivery OFF by default — user must opt in;
 *                     in-app notifications still show in the Account tab)
 *   - security: true  (new-device in-app alerts ON by default)
 *   - mcp_live: true  (MCP connect live-data notifications)
 *   - categories: every category on by default
 *
 * Admin can still gate everything globally via src/config/notification.json
 * (NotificationSettings::mode() == 'off' suppresses all creation).
 */
class UserNotificationSettings {
    public const CATEGORIES = [
        'system', 'mcp', 'clan', 'clubs', 'code_arena', 'discussions',
        'quiz', 'score', 'streak', 'account', 'academy', 'report',
    ];

    public static function defaults(): array {
        return [
            'email'      => false,
            'security'   => true,
            'mcp_live'   => true,
            'categories' => array_fill_keys(self::CATEGORIES, true),
        ];
    }

    private static function collection(): MongoDB\Collection {
        return DatabaseConnection::getDefaultDatabase()->users;
    }

    /**
     * Get effective settings for a user (by email). Merges defaults with
     * whatever is stored on the user doc. Never throws.
     */
    public static function getForUser(string $email): array {
        $cfg = self::defaults();
        try {
            if ($email === '') {
                return $cfg;
            }
            $doc = self::collection()->findOne(
                ['email' => $email],
                ['projection' => ['notification_settings' => 1]]
            );
            if (!$doc) {
                return $cfg;
            }
            $stored = self::normalizeDoc($doc['notification_settings'] ?? null);
            if ($stored === null) {
                return $cfg;
            }
            foreach (self::defaults() as $k => $v) {
                if (array_key_exists($k, $stored)) {
                    $cfg[$k] = $stored[$k];
                }
            }
            $cats = self::normalizeDoc($stored['categories'] ?? null);
            if ($cats !== null) {
                foreach (self::CATEGORIES as $cat) {
                    $cfg['categories'][$cat] = self::toBool($cats[$cat] ?? true, true);
                }
            }
            // Ensure critical keys are always proper bools (Mongo may return other scalars)
            $cfg['email'] = self::toBool($cfg['email'] ?? false, false);
            $cfg['security'] = self::toBool($cfg['security'] ?? true, true);
            $cfg['mcp_live'] = self::toBool($cfg['mcp_live'] ?? true, true);
        } catch (\Throwable $e) {
            error_log('UserNotificationSettings::getForUser failed: ' . $e->getMessage());
        }
        return $cfg;
    }

    /**
     * Normalize a MongoDB sub-document / BSONDocument / stdClass / array to a PHP array.
     * Returns null when the value is missing or not a document.
     */
    private static function normalizeDoc($value): ?array {
        if ($value === null) {
            return null;
        }
        if (is_array($value)) {
            return $value;
        }
        if ($value instanceof \ArrayObject) {
            return $value->getArrayCopy();
        }
        if (is_object($value)) {
            $vars = get_object_vars($value);
            return $vars ?: [];
        }
        return null;
    }

    /**
     * Save settings for a user (by email). Atomic $set on the users doc.
     * Returns false if no user document matched (so callers don't report a fake success).
     */
    public static function saveForUser(string $email, array $settings): bool {
        try {
            if ($email === '') {
                error_log('UserNotificationSettings::saveForUser: empty email');
                return false;
            }
            $clean = [
                'email'      => self::toBool($settings['email'] ?? false, false),
                'security'   => self::toBool($settings['security'] ?? true, true),
                'mcp_live'   => self::toBool($settings['mcp_live'] ?? true, true),
                'categories' => [],
            ];
            $cats = (isset($settings['categories']) && is_array($settings['categories']))
                ? $settings['categories'] : [];
            foreach (self::CATEGORIES as $cat) {
                $clean['categories'][$cat] = self::toBool($cats[$cat] ?? true, true);
            }

            $result = self::collection()->updateOne(
                ['email' => $email],
                ['$set' => ['notification_settings' => $clean]],
                ['upsert' => false]
            );

            $matched = (int) $result->getMatchedCount();
            $modified = (int) $result->getModifiedCount();
            if ($matched < 1) {
                error_log("UserNotificationSettings::saveForUser: no user matched email='{$email}' matched={$matched}");
                return false;
            }

            // Read back and confirm the critical flag stuck.
            $check = self::getForUser($email);
            $storedEmail = (bool) $check['email'];
            if ($storedEmail !== (bool) $clean['email']) {
                error_log(
                    "UserNotificationSettings::saveForUser: read-back mismatch for '{$email}' " .
                    "expected=" . var_export($clean['email'], true) .
                    " got=" . var_export($storedEmail, true) .
                    " matched={$matched} modified={$modified}"
                );
                return false;
            }
            return true;
        } catch (\Throwable $e) {
            error_log('UserNotificationSettings::saveForUser failed: ' . $e->getMessage());
            return false;
        }
    }

    /** Coerce JSON/form values to bool with a default. */
    private static function toBool($value, bool $default): bool {
        if (is_bool($value)) return $value;
        if (is_int($value) || is_float($value)) return (bool)$value;
        if (is_string($value)) {
            $v = strtolower(trim($value));
            if (in_array($v, ['1', 'true', 'on', 'yes'], true)) return true;
            if (in_array($v, ['0', 'false', 'off', 'no', ''], true)) return false;
        }
        return $default;
    }

    /** Is a category enabled for this user? */
    public static function categoryEnabled(string $email, string $category): bool {
        $cfg = self::getForUser($email);
        return (bool)($cfg['categories'][$category] ?? true);
    }

    /** Is email delivery enabled for this user? */
    public static function emailEnabled(string $email): bool {
        return (bool) self::getForUser($email)['email'];
    }

    /** Are security (new-device) alerts enabled for this user? */
    public static function securityEnabled(string $email): bool {
        return (bool) self::getForUser($email)['security'];
    }

    /** Are MCP live notifications enabled for this user? */
    public static function mcpLiveEnabled(string $email): bool {
        return (bool) self::getForUser($email)['mcp_live'];
    }
}
