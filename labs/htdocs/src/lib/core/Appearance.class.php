<?php

/**
 * Global appearance settings (Admin → Settings → Appearance).
 * Stored in global_settings._id = 'appearance'.
 */
class Appearance {
    public const DOC_ID = 'appearance';

    /**
     * `public_bg_mode` sentinel: use the profile owner's own saved background
     * for their signed-out profile page instead of one fixed wallpaper.
     */
    public const PUBLIC_OWNER = 'owner';

    private static $cache = null;

    public static function defaults(): array {
        return [
            'default_mode'     => 'spiderman',
            'force_mode'       => '',
            'disabled_modes'   => [],
            'force_color_mode' => '',
            // Signed-out profile pages get one wallpaper for everybody by
            // default (War); admins can override it or follow the owner.
            'public_bg_mode'   => 'ninja',
            'updated_at'       => 0,
            'updated_by'       => '',
        ];
    }

    public static function get(): array {
        if (self::$cache !== null) return self::$cache;

        $cfg = self::defaults();
        try {
            $db  = DatabaseConnection::getDefaultDatabase();
            $doc = $db->global_settings->findOne(['_id' => self::DOC_ID]);
            if ($doc) {
                $doc = (array)$doc;
                foreach (['default_mode', 'force_mode', 'force_color_mode', 'updated_at', 'updated_by', 'public_bg_mode'] as $k) {
                    if (array_key_exists($k, $doc)) $cfg[$k] = $doc[$k];
                }
                $cfg['public_bg_mode'] = (string)$cfg['public_bg_mode'];
                $disabled = $doc['disabled_modes'] ?? [];
                if ($disabled instanceof Traversable) $disabled = iterator_to_array($disabled, false);
                if (is_array($disabled)) {
                    $cfg['disabled_modes'] = array_values(array_map('strval', $disabled));
                }
            }
        } catch (Throwable $e) {
            error_log('Appearance::get: ' . $e->getMessage());
        }

        self::$cache = $cfg;
        return $cfg;
    }

    public static function forget(): void {
        self::$cache = null;
    }

    /**
     * All selectable background modes: plain + every configured image theme.
     */
    public static function modes(): array {
        static $modes = null;
        if ($modes !== null) return $modes;

        $tomThemes = [];
        require __DIR__ . '/../../config/themes.php';
        $modes = array_merge(['plain'], array_keys($tomThemes));
        return $modes;
    }

    public static function label(string $mode): string {
        $labels = [
            'plain'      => 'Plain (solid color)',
            'robo'       => 'Lab',
            'ninja'      => 'War',
            'robotower'  => 'Tower',
            'spiderman'  => 'Spidey',
            'ironman'    => 'Iron Man',
        ];
        return $labels[$mode] ?? ucfirst($mode);
    }

    /**
     * The background mode a user should actually see:
     *   locked mode → their own (non-hidden) mode → the default → first usable mode.
     */
    public static function resolveMode(?string $userMode): string {
        $cfg      = self::get();
        $disabled = $cfg['disabled_modes'];
        $force    = (string)$cfg['force_mode'];

        if ($force !== '') return $force;
        if ($userMode !== null && $userMode !== '' && !in_array($userMode, $disabled, true)) {
            return $userMode;
        }

        foreach (array_merge([(string)$cfg['default_mode']], self::modes()) as $candidate) {
            if (!in_array($candidate, $disabled, true)) return $candidate;
        }
        return 'spiderman';
    }

    public static function lockedMode(): string {
        return (string)self::get()['force_mode'];
    }

    /**
     * Background for a signed-out profile page.
     *
     * Admin lock wins (same as everywhere else). Otherwise `public_bg_mode` decides:
     *   'owner'        → whatever wallpaper the profile owner saved
     *   '<theme id>'   → one fixed wallpaper for every public profile URL
     *   ''             → the site default, via resolveMode()
     *
     * A fixed choice deliberately bypasses `disabled_modes`: hidden themes are a
     * picker concern, and the admin picked this one on purpose for public pages.
     */
    public static function resolvePublicMode(?string $ownerEmail): string {
        $cfg   = self::get();
        $force = (string)$cfg['force_mode'];
        if ($force !== '') {
            return $force;
        }

        $choice = (string)($cfg['public_bg_mode'] ?? '');

        if ($choice === self::PUBLIC_OWNER) {
            $ownerMode = self::ownerMode($ownerEmail);
            return $ownerMode !== '' ? $ownerMode : (string)$cfg['default_mode'];
        }

        if ($choice !== '') {
            return $choice;
        }

        return self::resolveMode(null);
    }

    /** The background a user saved in their profile, or '' when unknown. */
    public static function ownerMode(?string $ownerEmail): string {
        if ($ownerEmail === null || $ownerEmail === '') {
            return '';
        }
        try {
            $db   = DatabaseConnection::getDefaultDatabase();
            $doc  = $db->users->findOne(['email' => $ownerEmail], ['projection' => ['theme_preferences.mode' => 1]]);
            if ($doc) {
                $mode = trim((string)($doc['theme_preferences']['mode'] ?? ''));
                if ($mode !== '') {
                    return $mode;
                }
            }
        } catch (Throwable $e) {
            error_log('Appearance::ownerMode: ' . $e->getMessage());
        }
        return '';
    }

    public static function lockedColorMode(): string {
        return (string)self::get()['force_color_mode'];
    }
}
