<?php

/**
 * Global notification settings (Admin → Settings → Notification Settings).
 *
 * Stored as JSON in `src/config/notification.json` — NOT in Mongo. A single,
 * rarely-changed value read on every page render; a static JSON file avoids a
 * database round-trip, stays versionable next to the other config files in
 * src/config, and keeps working even if the DB is unreachable.
 *
 * Path resolution is relative (__DIR__-based), so the same file works in
 * local XAMPP (htdocs/src/config/notification.json) and Docker
 * (/var/www/labs/htdocs/src/config/notification.json) without special-casing.
 *
 * Access mode semantics (notification_mode):
 *   - "off"      → notifications fully suppressed: no new-device emails, no
 *                  admin broadcasts, no MCP connect notifications. The bell
 *                  still renders but nothing new is ever created.
 *   - "admin"    → DEFAULT (hardcoded). Admin can broadcast to all users;
 *                  system/security/MCP notifications are created normally.
 *
 * A missing or corrupt file always falls back to the hardcoded default so
 * notifications never break because of a bad write.
 */
class NotificationSettings {
    public const MODE_OFF   = 'off';
    public const MODE_ADMIN = 'admin';
    public const MODES      = [self::MODE_OFF, self::MODE_ADMIN];

    private static $cache = null;

    public static function defaults(): array {
        return [
            'notification_mode' => self::MODE_ADMIN,
            'broadcast_enabled' => true,
            'updated_at'        => 0,
            'updated_by'        => '',
        ];
    }

    /** Absolute path of the settings file (relative to this class file). */
    public static function filePath(): string {
        return __DIR__ . '/../../config/notification.json';
    }

    public static function get(): array {
        if (self::$cache !== null) return self::$cache;

        $cfg = self::defaults();
        $path = self::filePath();
        if (is_readable($path)) {
            $raw  = file_get_contents($path);
            $data = $raw !== false ? json_decode($raw, true) : null;
            if (is_array($data)) {
                $mode = (string)($data['notification_mode'] ?? self::MODE_ADMIN);
                $cfg['notification_mode'] = in_array($mode, self::MODES, true) ? $mode : self::MODE_ADMIN;
                $cfg['broadcast_enabled'] = (bool)($data['broadcast_enabled'] ?? true);
                if (isset($data['updated_at'])) $cfg['updated_at'] = (int)$data['updated_at'];
                if (isset($data['updated_by'])) $cfg['updated_by'] = (string)$data['updated_by'];
            }
        }
        self::$cache = $cfg;
        return $cfg;
    }

    /** Resolve the current notification access mode with defaults applied. */
    public static function mode(): string {
        return self::get()['notification_mode'];
    }

    /** Whether admin broadcasts are currently enabled. */
    public static function broadcastEnabled(): bool {
        return (bool) self::get()['broadcast_enabled'];
    }

    /**
     * Persist the mode atomically: write to a temp file first, then rename
     * over the target so a crash mid-write can never leave a half-written
     * JSON behind.
     */
    public static function save(string $mode, bool $broadcastEnabled, string $updatedBy = ''): bool {
        if (!in_array($mode, self::MODES, true)) return false;

        $path = self::filePath();
        $dir  = dirname($path);
        if (!is_dir($dir)) return false;

        $payload = [
            'notification_mode' => $mode,
            'broadcast_enabled' => (bool) $broadcastEnabled,
            'updated_at'        => time(),
            'updated_by'        => $updatedBy,
        ];
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) return false;

        $tmp = $path . '.tmp.' . getmypid();
        if (@file_put_contents($tmp, $json . "\n", LOCK_EX) === false) {
            @unlink($tmp);
            return false;
        }
        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            return false;
        }
        @chmod($path, 0644);

        self::$cache = null;
        return true;
    }

    /** Drop the per-request cache (used after external edits / tests). */
    public static function forget(): void {
        self::$cache = null;
    }
}
