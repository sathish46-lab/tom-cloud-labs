<?php

namespace TomLabs\Services;

/**
 * Admin-controlled service availability flags.
 *
 * Stored in MongoDB: global_settings._id = 'service_flags'
 * e.g. { mysql: true, postgresql: false, ... }
 *
 * Semantics: a missing document or missing key means ENABLED, so existing
 * deployments keep every service on until an admin turns one off.
 * Read results are memoised per request; toggles take effect on the next
 * request (no restart, no cache rebuild needed).
 */
class ServiceFlags
{
    public const DOC_ID = 'service_flags';

    /** @var array<string, bool>|null */
    private static ?array $cache = null;

    public static function isEnabled(string $service): bool
    {
        $flags = self::all();
        return $flags[$service] ?? true;
    }

    /** @return array<string, bool> slug => enabled, for every configured service */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $flags = [];
        foreach (array_keys(\TomLabs\Services\ServiceConfig::all()) as $slug) {
            $flags[$slug] = true;
        }

        try {
            $doc = \DatabaseConnection::getDefaultDatabase()
                ->global_settings
                ->findOne(['_id' => self::DOC_ID]);
            if ($doc) {
                $stored = (is_object($doc) && method_exists($doc, 'getArrayCopy'))
                    ? $doc->getArrayCopy()
                    : ((array)$doc ?: []);
                foreach ($flags as $slug => $default) {
                    if (array_key_exists($slug, $stored)) {
                        $flags[$slug] = (bool)$stored[$slug];
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log('ServiceFlags read failed: ' . $e->getMessage());
        }

        self::$cache = $flags;
        return self::$cache;
    }

    /** Persist one flag. Returns true when the stored value changed. */
    public static function set(string $service, bool $enabled): bool
    {
        if (!isset(self::all()[$service])) {
            throw new \InvalidArgumentException("Unknown service: {$service}");
        }

        \DatabaseConnection::getDefaultDatabase()
            ->global_settings
            ->updateOne(
                ['_id' => self::DOC_ID],
                ['$set' => [$service => $enabled]],
                ['upsert' => true]
            );

        self::$cache[$service] = $enabled;
        return $enabled;
    }
}
