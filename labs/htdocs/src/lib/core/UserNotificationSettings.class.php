<?php

/**
 * Per-user notification settings — stored on the MongoDB `users` document
 * field `notification_settings`.
 *
 * Defaults mirror the Image 1 design:
 *   - email:    true  (master switch — off means no email deliveries at all)
 *   - security: true  (new-device alerts ON by default, per Image 1 copy:
 *                     "Security alerts are on by default — leaving them on is
 *                     how you find out about a sign-in you did not make.")
 *   - mcp_live: true  (MCP connect live-data notifications)
 *   - categories: every category on by default
 *
 * Admin can still gate everything globally via src/config/notification.json
 * (NotificationSettings::mode() == 'off' suppresses all creation).
 */
class UserNotificationSettings {
    public const CATEGORIES = [
        'system', 'mcp', 'clan', 'clubs', 'code_arena', 'discussions',
        'quiz', 'score', 'streak', 'academy', 'report',
    ];

    public static function defaults(): array {
        return [
            'email'      => true,
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
            $doc = self::collection()->findOne(
                ['email' => $email],
                ['projection' => ['notification_settings' => 1]]
            );
            if ($doc && isset($doc['notification_settings']) && is_array($doc['notification_settings'])) {
                $stored = (array) $doc['notification_settings'];
                foreach (self::defaults() as $k => $v) {
                    if (array_key_exists($k, $stored)) {
                        $cfg[$k] = $stored[$k];
                    }
                }
                if (isset($stored['categories']) && is_array($stored['categories'])) {
                    foreach (self::CATEGORIES as $cat) {
                        $cfg['categories'][$cat] = (bool)($stored['categories'][$cat] ?? true);
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log('UserNotificationSettings::getForUser failed: ' . $e->getMessage());
        }
        return $cfg;
    }

    /**
     * Save settings for a user (by email). Atomic $set on the users doc.
     */
    public static function saveForUser(string $email, array $settings): bool {
        try {
            $clean = [
                'email'      => isset($settings['email'])      ? (bool) $settings['email'] : true,
                'security'   => isset($settings['security'])   ? (bool) $settings['security'] : true,
                'mcp_live'   => isset($settings['mcp_live'])   ? (bool) $settings['mcp_live'] : true,
                'categories' => [],
            ];
            foreach (self::CATEGORIES as $cat) {
                $clean['categories'][$cat] = isset($settings['categories'][$cat])
                    ? (bool) $settings['categories'][$cat] : true;
            }

            self::collection()->updateOne(
                ['email' => $email],
                ['$set' => ['notification_settings' => $clean]],
                ['upsert' => false]
            );
            return true;
        } catch (\Throwable $e) {
            error_log('UserNotificationSettings::saveForUser failed: ' . $e->getMessage());
            return false;
        }
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
