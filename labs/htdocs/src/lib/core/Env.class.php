<?php
namespace TomLabs\Core;

/**
 * Env Helper Class
 * 
 * Simple loader for the .env file in the root directory.
 * Falls back to explicitly provided defaults if missing.
 */
class Env {
    private static $variables = null;

    /**
     * Load the env.json file if it hasn't been loaded yet
     */
    private static function load() {
        if (self::$variables === null) {
            $envPath = __DIR__ . '/../../../../env.json'; // From src/lib/core/ to htdocs/
            if (file_exists($envPath)) {
                self::$variables = json_decode(file_get_contents($envPath), true);
            } else {
                self::$variables = [];
            }
        }
    }

    /**
     * Get an environment variable by key
     * 
     * @param string $key The environment variable key
     * @param mixed $default The default value if not found
     * @return mixed
     */
    public static function get(string $key, $default = null) {
        self::load();
        
        // Also check actual system environment variables
        $sysEnv = getenv($key);
        if ($sysEnv !== false) {
            return $sysEnv;
        }

        return self::$variables[$key] ?? $default;
    }
}
