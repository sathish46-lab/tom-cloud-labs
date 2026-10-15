<?php

/**
 * Site body font (Admin → Appearance → Site font).
 *
 * Deliberately file-based — NO database:
 *   - state:    src/config/site_font.json   (family + Google-validated weights)
 *   - rule:     assets/css/site-font.css    (written on save, linked by _master.php)
 *
 * No JSON file = default font (Rubik) = pristine behaviour.
 */
class SiteFont
{
    public const DEFAULT         = 'Rubik';
    public const DEFAULT_WEIGHTS = '300;400;500;600;700;800;900';
    public const JSON_FILE       = __DIR__ . '/../../config/site_font.json';
    public const CSS_FILE        = __DIR__ . '/../../../assets/css/site-font.css';

    /**
     * Font families offered in the admin picker, grouped by style (all on Google Fonts).
     * Any other family can still be set via "Custom…" — it is validated on save.
     */
    public const CURATED_GROUPS = [
        'Sans serif' => [
            'Inter', 'Roboto', 'Open Sans', 'Lato', 'Montserrat', 'Poppins',
            'Nunito', 'Nunito Sans', 'Raleway', 'Work Sans', 'DM Sans', 'Manrope',
            'Outfit', 'Plus Jakarta Sans', 'Figtree', 'Sora', 'Urbanist', 'Lexend',
            'Public Sans', 'Source Sans 3', 'Noto Sans', 'Rubik', 'Karla', 'Mulish',
            'Barlow', 'Jost', 'Space Grotesk', 'IBM Plex Sans', 'Red Hat Display', 'Ubuntu',
        ],
        'Serif' => [
            'Playfair Display', 'Merriweather', 'Lora', 'Libre Baskerville',
            'EB Garamond', 'Source Serif 4', 'Fraunces', 'PT Serif',
        ],
        'Display' => [
            'Oswald', 'Bebas Neue', 'Anton', 'Archivo Black', 'Abril Fatface',
            'Righteous', 'Baloo 2', 'Fredoka', 'Luckiest Guy', 'Titan One',
        ],
        'Script' => [
            'Pacifico', 'Dancing Script', 'Caveat', 'Great Vibes', 'Satisfy', 'Lobster',
        ],
        'Monospace' => [
            'JetBrains Mono', 'Fira Code', 'Source Code Pro', 'IBM Plex Mono',
            'Space Mono', 'Roboto Mono',
        ],
    ];

    /** Weight lists tried in order when validating a family against Google. */
    private const WEIGHT_CANDIDATES = [
        '300;400;500;600;700;800;900',
        '400;500;600;700',
        '400;700',
        '400',
    ];

    /** True when a non-default font is active → _master.php emits the site-font.css link. */
    public static function isActive(): bool
    {
        return self::family() !== self::DEFAULT;
    }

    /** Selected family; default Rubik when no config exists or the value is invalid. */
    public static function family(): string
    {
        $family = (string)(self::read()['family'] ?? '');
        return self::validName($family) ? $family : self::DEFAULT;
    }

    /** Validated Google weight list for the active family. */
    public static function weights(): string
    {
        $weights = (string)(self::read()['weights'] ?? '');
        return preg_match('/^[0-9]{3}(;[0-9]{3})*$/', $weights) ? $weights : self::DEFAULT_WEIGHTS;
    }

    /**
     * Full Google Fonts css2 URL for the active family + the site's two code fonts
     * (Fira Code, JetBrains Mono — used by every code/terminal block).
     * Built as a string only, never fetched at render time.
     */
    public static function googleUrl(): string
    {
        return self::buildUrl(self::family(), self::weights());
    }

    private static function buildUrl(string $family, string $weights): string
    {
        return 'https://fonts.googleapis.com/css2?family=' . rawurlencode($family)
             . ':wght@' . $weights
             . '&family=Fira+Code:wght@400;500;600;700'
             . '&family=JetBrains+Mono:wght@400;500;600;700'
             . '&display=swap';
    }

    /** Only the default Rubik file is preloaded (one woff2 covers all its weights). */
    public static function preloadUrl(): ?string
    {
        if (self::family() !== self::DEFAULT) {
            return null;
        }
        return 'https://fonts.gstatic.com/s/rubik/v31/iJWKBXyIfDnIV7nBrXyw023e.woff2';
    }

    /**
     * Validate the family against Google Fonts and persist json + css.
     * family === DEFAULT reverts to pristine state (removes both files).
     */
    public static function save(string $family): array
    {
        $family = trim($family);
        if (!self::validName($family)) {
            return ['status' => 'error', 'error' => 'Invalid font name — use letters, digits and spaces only (max 40).'];
        }

        if ($family === self::DEFAULT) {
            @unlink(self::JSON_FILE);
            @unlink(self::CSS_FILE);
            return ['status' => 'success', 'family' => self::DEFAULT, 'default' => true];
        }

        $weights = self::validate($family);
        if ($weights === null) {
            return ['status' => 'error', 'error' => 'Google Fonts does not serve "' . $family . '". Check the family name and try again.'];
        }

        $payload = json_encode(
            ['family' => $family, 'weights' => $weights, 'updated_at' => time()],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );
        if (@file_put_contents(self::JSON_FILE, $payload, LOCK_EX) === false) {
            return ['status' => 'error', 'error' => 'Could not write config/site_font.json'];
        }

        $css = "/* Generated by SiteFont (Admin -> Appearance). Do not edit — overwritten on save. */\n"
             . 'body { font-family: "' . $family . '", sans-serif !important; }' . "\n";
        if (@file_put_contents(self::CSS_FILE, $css, LOCK_EX) === false) {
            @unlink(self::JSON_FILE);
            return ['status' => 'error', 'error' => 'Could not write assets/css/site-font.css'];
        }

        return ['status' => 'success', 'family' => $family, 'weights' => $weights];
    }

    /** Flat list of all curated families (for "is custom?" checks). */
    public static function curated(): array
    {
        return array_merge(...array_values(self::CURATED_GROUPS));
    }

    /** Ask Google Fonts which weight list the family actually serves. */
    private static function validate(string $family): ?string
    {
        foreach (self::WEIGHT_CANDIDATES as $weights) {
            // IMPORTANT: validate the PRIMARY family alone — in a multi-family URL Google
            // silently drops an unknown family and still returns 200, which would let
            // bogus names through. (Verified: fake alone → 400, fake+real → 200.)
            $url = 'https://fonts.googleapis.com/css2?family=' . rawurlencode($family)
                 . ':wght@' . $weights . '&display=swap';
            if (self::googleOk($url)) {
                return $weights;
            }
        }
        return null;
    }

    private static function googleOk(string $url): bool
    {
        $ctx  = stream_context_create(['http' => ['timeout' => 5, 'ignore_errors' => true]]);
        $body = @file_get_contents($url, false, $ctx);
        if ($body === false || $body === '') {
            return false;
        }
        $status = $http_response_header[0] ?? '';
        return strpos($status, ' 200') !== false;
    }

    /** Allow safe names only (also makes CSS/URL injection impossible). */
    private static function validName(string $family): bool
    {
        return (bool)preg_match('/^[A-Za-z][A-Za-z0-9 ]{0,39}$/', $family);
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
