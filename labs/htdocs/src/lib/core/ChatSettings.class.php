<?php

/**
 * Global chat settings (Admin → Settings → Chat Settings).
 *
 * Stored as JSON in `src/config/chat.json` — NOT in Mongo. This is a single,
 * rarely-changed value that is read on every page render; a static JSON file
 * avoids a database round-trip, stays versionable next to the other config
 * files in src/config, and keeps working even if the DB is unreachable.
 *
 * Path resolution is relative (__DIR__-based), so the same file works in
 * local XAMPP (htdocs/src/config/chat.json) and Docker
 * (/var/www/labs/htdocs/src/config/chat.json) without special-casing.
 *
 * Access mode semantics (video_call_mode):
 *   - "off"    → video calls fully disabled: call UI is not rendered, the
 *                call JS bundle is not loaded, and call-signal.php rejects
 *                every signal type with 403 (kills in-progress calls too).
 *   - "admin"  → DEFAULT (hardcoded). Only GROUP_ADMIN / GROUP_SUPERUSER may
 *                start calls; any logged-in user may still answer.
 *   - "public" → any logged-in user may start and answer calls. Guests are
 *                never allowed (all chat APIs require a session anyway).
 *
 * A missing or corrupt file always falls back to the hardcoded default so
 * calls never break because of a bad write.
 */
class ChatSettings {
    public const MODE_OFF    = 'off';
    public const MODE_ADMIN  = 'admin';
    public const MODE_PUBLIC = 'public';
    public const MODES       = [self::MODE_OFF, self::MODE_ADMIN, self::MODE_PUBLIC];

    private static $cache = null;

    public static function defaults(): array {
        return [
            'video_call_mode' => self::MODE_ADMIN,
            'updated_at'      => 0,
            'updated_by'      => '',
        ];
    }

    /** Absolute path of the settings file (relative to this class file). */
    public static function filePath(): string {
        return __DIR__ . '/../../config/chat.json';
    }

    public static function get(): array {
        if (self::$cache !== null) return self::$cache;

        $cfg = self::defaults();
        $path = self::filePath();
        if (is_readable($path)) {
            $raw  = file_get_contents($path);
            $data = $raw !== false ? json_decode($raw, true) : null;
            if (is_array($data)) {
                $mode = (string)($data['video_call_mode'] ?? self::MODE_ADMIN);
                $cfg['video_call_mode'] = in_array($mode, self::MODES, true) ? $mode : self::MODE_ADMIN;
                if (isset($data['updated_at'])) $cfg['updated_at'] = (int)$data['updated_at'];
                if (isset($data['updated_by'])) $cfg['updated_by'] = (string)$data['updated_by'];
            }
        }
        self::$cache = $cfg;
        return $cfg;
    }

    /** Resolve the current call access mode with defaults applied. */
    public static function callMode(): string {
        return self::get()['video_call_mode'];
    }

    /**
     * Persist the mode atomically: write to a temp file first, then rename
     * over the target so a crash mid-write can never leave a half-written
     * JSON behind (get() would still fall back to defaults, but atomic keeps
     * the admin's last saved value intact).
     */
    public static function save(string $mode, string $updatedBy = ''): bool {
        if (!in_array($mode, self::MODES, true)) return false;

        $path = self::filePath();
        $dir  = dirname($path);
        if (!is_dir($dir)) return false;

        $payload = [
            'video_call_mode' => $mode,
            'updated_at'      => time(),
            'updated_by'      => $updatedBy,
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
