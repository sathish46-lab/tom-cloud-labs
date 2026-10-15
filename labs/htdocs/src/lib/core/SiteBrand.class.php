<?php

/**
 * Site display name (Admin → Appearance → Brand name).
 *
 * Deliberately file-based — NO database, same scheme as SiteFont:
 *   - state: src/config/site_brand.json   {"name": "..."}
 * No JSON file = default name = pristine behaviour.
 */
class SiteBrand
{
    public const DEFAULT    = 'Tom Labs';
    public const MAX_LENGTH = 30;
    public const JSON_FILE  = __DIR__ . '/../../config/site_brand.json';

    /** Configured site name; default when no config exists or the value is invalid. */
    public static function name(): string
    {
        $name = trim((string)(self::read()['name'] ?? ''));
        return self::validName($name) ? $name : self::DEFAULT;
    }

    /**
     * Validate and persist the name. Saving the default removes the config
     * file so the site returns to pristine behaviour.
     */
    public static function save(string $name): array
    {
        $name = trim($name);
        if ($name === self::DEFAULT) {
            @unlink(self::JSON_FILE);
            return ['status' => 'success', 'name' => self::DEFAULT, 'default' => true];
        }
        if (!self::validName($name)) {
            return ['status' => 'error', 'error' => 'Use 1-' . self::MAX_LENGTH . ' letters, digits, spaces or basic punctuation.'];
        }

        $payload = json_encode(['name' => $name, 'updated_at' => time()], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (@file_put_contents(self::JSON_FILE, $payload, LOCK_EX) === false) {
            return ['status' => 'error', 'error' => 'Could not write config/site_brand.json'];
        }
        return ['status' => 'success', 'name' => $name];
    }

    /**
     * Letters/digits/spaces plus a few safe marks. No HTML/JS is possible,
     * so every render site can safely htmlspecialchars() this.
     */
    private static function validName(string $name): bool
    {
        return $name !== ''
            && mb_strlen($name) <= self::MAX_LENGTH
            && (bool)preg_match("/^[\p{L}\p{N} .&'-]+$/u", $name);
    }

    private static function read(): array
    {
        if (!is_file(self::JSON_FILE)) {
            return [];
        }
        $data = json_decode((string)@file_get_contents(self::JSON_FILE), true);
        return is_array($data) ? $data : [];
    }
}
