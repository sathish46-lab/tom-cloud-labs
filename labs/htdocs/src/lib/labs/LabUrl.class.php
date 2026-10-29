<?php
namespace TomLabs\Labs;

/**
 * LabUrl — single source of truth for the public URLs of a lab instance.
 *
 * Host   : taken from the instance document (code_domain / gui_domain). That
 *          field is exactly what the routing layer (Traefik) routes on, so the
 *          URL always points at the host that is really being served. When the
 *          user attaches a custom domain, it is stored there too.
 *
 * Scheme : "code_url_scheme" (env var CODE_URL_SCHEME, else env.json) wins
 *          when set. Otherwise it follows the current request, so a plain-HTTP
 *          request yields http:// and an HTTPS request yields https://.
 *          Production (HTTPS) therefore stays https with no local override,
 *          while a local dev box can force http with one config key.
 *
 * Nothing here reads absolute URLs from the database as the primary source:
 * stored credentials are only used as a fallback and their scheme is
 * normalised, so a stale stored value can never leak a wrong scheme.
 */
class LabUrl
{
    public const DEFAULT_SCHEME    = 'https';
    public const DEFAULT_BASE_DOMAIN = 'tomweb.shop';

    /**
     * Effective scheme: explicit override > config > request detection.
     */
    public static function scheme(?string $override = null): string
    {
        $candidates = [];

        if ($override !== null && trim($override) !== '') {
            $candidates[] = $override;
        }

        if (function_exists('get_config')) {
            $cfg = get_config('code_url_scheme');
            if (is_string($cfg) && trim($cfg) !== '') {
                $candidates[] = $cfg;
            }
        }

        foreach ($candidates as $candidate) {
            $scheme = strtolower(trim($candidate));
            if ($scheme === 'http' || $scheme === 'https') {
                return $scheme;
            }
        }

        return self::requestIsHttps() ? 'https' : 'http';
    }

    /**
     * Is the current request served over TLS?
     */
    public static function requestIsHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) === 'on') {
            return true;
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])
            && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
            return true;
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_SSL'])
            && strtolower((string)$_SERVER['HTTP_X_FORWARDED_SSL']) === 'on') {
            return true;
        }
        if (isset($_SERVER['SERVER_PORT']) && (string)$_SERVER['SERVER_PORT'] === '443') {
            return true;
        }
        return false;
    }

    /**
     * Base domain the instance hostnames live under.
     *
     * Derived from the instance's own code_domain when possible (a custom
     * domain like "code2.tomweb.shop" still yields "tomweb.shop"), so no
     * deployment-wide constant has to be kept in sync.
     */
    public static function baseDomain(array $labData = []): string
    {
        $codeDomain = isset($labData['code_domain']) ? trim((string)$labData['code_domain']) : '';
        if ($codeDomain !== '' && strpos($codeDomain, '.') !== false) {
            $parts = explode('.', $codeDomain);
            array_shift($parts);
            $base = implode('.', $parts);
            if ($base !== '') {
                return $base;
            }
        }

        if (function_exists('get_config')) {
            $zone = get_config('default_code_server_domain');
            if (!is_string($zone) || trim($zone) === '') {
                $zone = get_config('instance_domain_zone');
            }
            if (is_string($zone) && trim($zone) !== '') {
                return trim($zone);
            }
        }

        return self::DEFAULT_BASE_DOMAIN;
    }

    /**
     * Hostname of the Code IDE service (code-server) for an instance.
     */
    public static function codeHost(array $labData): string
    {
        $chosen = isset($labData['code_domain']) ? trim((string)$labData['code_domain']) : '';
        if ($chosen !== '') {
            return $chosen;
        }

        $hash = self::instanceHash($labData);
        if ($hash === '') {
            return '';
        }
        return "code-{$hash}." . self::baseDomain($labData);
    }

    /**
     * Hostname of the GUI (VNC) service for an instance.
     */
    public static function guiHost(array $labData): string
    {
        $chosen = isset($labData['gui_domain']) ? trim((string)$labData['gui_domain']) : '';
        if ($chosen !== '') {
            return $chosen;
        }

        $hash = self::instanceHash($labData);
        if ($hash === '') {
            return '';
        }
        return "gui-{$hash}." . self::baseDomain($labData);
    }

    /**
     * Hostname of an auxiliary service (n8n-, s3-, api-).
     */
    public static function serviceHost(array $labData, string $prefix): string
    {
        $hash = self::instanceHash($labData);
        if ($hash === '') {
            return '';
        }
        return "{$prefix}{$hash}." . self::baseDomain($labData);
    }

    /**
     * Absolute URL for a host. A host that already carries a scheme keeps it
     * only if the effective scheme is the same one; otherwise it is rewritten.
     */
    public static function url(string $host, ?string $schemeOverride = null): string
    {
        $host = trim($host);
        if ($host === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $host)) {
            return self::withScheme($host, $schemeOverride);
        }
        return self::scheme($schemeOverride) . '://' . $host;
    }

    /**
     * IDE URL for an instance — derived from the instance document.
     */
    public static function ideUrl(array $labData, ?string $schemeOverride = null): string
    {
        $host = self::codeHost($labData);
        if ($host !== '') {
            return self::url($host, $schemeOverride);
        }

        $stored = self::storedCredential($labData, 'code_server_url');
        return $stored !== '' ? self::withScheme($stored, $schemeOverride) : '';
    }

    /**
     * GUI URL for an instance — derived from the instance document.
     */
    public static function guiUrl(array $labData, ?string $schemeOverride = null): string
    {
        $host = self::guiHost($labData);
        if ($host !== '') {
            return self::url($host, $schemeOverride);
        }

        $stored = self::storedCredential($labData, 'gui_url');
        return $stored !== '' ? self::withScheme($stored, $schemeOverride) : '';
    }

    /**
     * Rewrite the scheme of an absolute URL to the effective scheme.
     * Host, port and path are preserved untouched.
     */
    public static function withScheme(string $url, ?string $schemeOverride = null): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (preg_match('#^(https?)://#i', $url, $matches)) {
            return self::scheme($schemeOverride) . '://' . substr($url, strlen($matches[0]));
        }
        return self::url($url, $schemeOverride);
    }

    /**
     * Read a stored credential from an instance document.
     */
    private static function storedCredential(array $labData, string $key): string
    {
        $creds = $labData['credentials'] ?? [];
        if (!is_array($creds)) {
            return '';
        }
        $value = $creds[$key] ?? '';
        return is_string($value) ? trim($value) : '';
    }

    /**
     * Instance hash from an instance document.
     */
    private static function instanceHash(array $labData): string
    {
        $hash = isset($labData['instance_hash']) ? trim((string)$labData['instance_hash']) : '';
        return $hash;
    }
}
