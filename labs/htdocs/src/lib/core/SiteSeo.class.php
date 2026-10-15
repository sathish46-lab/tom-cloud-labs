<?php

/**
 * Site-wide SEO defaults (Admin → Appearance → SEO).
 *
 * Deliberately file-based — NO database, same scheme as SiteFont/SiteBrand:
 *   - state: src/config/site_seo.json   {"title","description","keywords","author"}
 * No JSON file = pristine defaults (derived from the site name).
 *
 * Pages can still override per-request via Session::$pageTitle /
 * Session::set('seo_description'|'seo_keywords', ...) — these are the fallbacks.
 */
class SiteSeo
{
    public const JSON_FILE     = __DIR__ . '/../../config/site_seo.json';
    public const MAX_TITLE     = 70;
    public const MAX_DESC      = 300;
    public const MAX_KEYWORDS  = 250;
    public const MAX_AUTHOR    = 60;

    public const DEFAULT_TITLE    = '{name} - Advanced Development Environment';
    public const DEFAULT_DESC     = 'Experience the ultimate cloud development environment. {name} provides a highly secure, fast, and feature-rich development workspace, VPS, and VPN tailored for professionals.';
    public const DEFAULT_KEYWORDS = 'Advanced Development Environment, Cloud IDE, VPS Hosting, Secure VPN, Docker Apps, {name}, Coding Workspace';
    public const DEFAULT_AUTHOR   = 'Sathish';

    /** Effective defaults with {name} substituted from SiteBrand. */
    public static function defaults(): array
    {
        require_once __DIR__ . '/SiteBrand.class.php';
        $name = SiteBrand::name();
        $cfg  = self::read();

        $title  = trim((string)($cfg['title'] ?? ''));
        $desc   = trim((string)($cfg['description'] ?? ''));
        $keys   = trim((string)($cfg['keywords'] ?? ''));
        $author = trim((string)($cfg['author'] ?? ''));

        return [
            'title'       => self::fill($title !== '' ? $title : self::DEFAULT_TITLE, $name),
            'description' => self::fill($desc !== '' ? $desc : self::DEFAULT_DESC, $name),
            'keywords'    => self::fill($keys !== '' ? $keys : self::DEFAULT_KEYWORDS, $name),
            'author'      => $author !== '' ? $author : self::DEFAULT_AUTHOR,
        ];
    }

    public static function title(): string       { return self::defaults()['title']; }
    public static function description(): string { return self::defaults()['description']; }
    public static function keywords(): string    { return self::defaults()['keywords']; }
    public static function author(): string      { return self::defaults()['author']; }

    /** Validate + persist. Empty field = back to the built-in default. */
    public static function save(array $in): array
    {
        $title  = trim((string)($in['title'] ?? ''));
        $desc   = trim((string)($in['description'] ?? ''));
        $keys   = trim((string)($in['keywords'] ?? ''));
        $author = trim((string)($in['author'] ?? ''));

        if (mb_strlen($title) > self::MAX_TITLE) {
            return ['status' => 'error', 'error' => 'Title must be ' . self::MAX_TITLE . ' characters or fewer.'];
        }
        if (mb_strlen($desc) > self::MAX_DESC) {
            return ['status' => 'error', 'error' => 'Description must be ' . self::MAX_DESC . ' characters or fewer.'];
        }
        if (mb_strlen($keys) > self::MAX_KEYWORDS) {
            return ['status' => 'error', 'error' => 'Keywords must be ' . self::MAX_KEYWORDS . ' characters or fewer.'];
        }
        if (mb_strlen($author) > self::MAX_AUTHOR) {
            return ['status' => 'error', 'error' => 'Author must be ' . self::MAX_AUTHOR . ' characters or fewer.'];
        }

        // All empty = pristine state (remove the config file)
        if ($title === '' && $desc === '' && $keys === '' && $author === '') {
            @unlink(self::JSON_FILE);
            return ['status' => 'success', 'default' => true] + self::defaults();
        }

        $payload = json_encode([
            'title'       => $title,
            'description' => $desc,
            'keywords'    => $keys,
            'author'      => $author,
            'updated_at'  => time(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (@file_put_contents(self::JSON_FILE, $payload, LOCK_EX) === false) {
            return ['status' => 'error', 'error' => 'Could not write config/site_seo.json'];
        }
        return ['status' => 'success'] + self::defaults();
    }

    /** Stored values (raw, for the admin form); empty string = using the default. */
    public static function stored(): array
    {
        $cfg = self::read();
        return [
            'title'       => (string)($cfg['title'] ?? ''),
            'description' => (string)($cfg['description'] ?? ''),
            'keywords'    => (string)($cfg['keywords'] ?? ''),
            'author'      => (string)($cfg['author'] ?? ''),
        ];
    }

    private static function fill(string $tpl, string $name): string
    {
        return str_replace('{name}', $name, $tpl);
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
