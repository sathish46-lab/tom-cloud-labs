<?php

class Cache {
    private static $memory = [];
    private static $memoryExpires = [];
    private static $appCachePath = null;

    private static function getCacheDir() {
        if (self::$appCachePath === null) {
            self::$appCachePath = get_config('app_cache');
        }
        return self::$appCachePath;
    }

    public static function set($key, $val, $ttl = null) {
        if (is_object($val)) {
            throw new ObjectNotSupportedException;
        }

        // Store in static memory for instant retrieval later in the same request
        self::$memory[$key] = $val;
        self::$memoryExpires[$key] = $ttl !== null ? time() + (int)$ttl : null;

        $export_val = var_export($val, true);
        $export_val = str_replace('stdClass::__set_state', '(object)', $export_val);

        $cacheDir = self::getCacheDir();
        if (!$cacheDir) {
            error_log("CACHE ERROR: app_cache path not defined in config.json.");
            return;
        }

        // --- PROFESSIONAL SELF-HEALING LOGIC ---
        // Ensure the directory from config exists and is writable
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0775, true);
            // Change ownership to Apache user if running as root
            chown($cacheDir, 'www-data'); 
        }

        $tmpDir = rtrim($cacheDir, '/') . "/.tmp";
        if (!is_dir($tmpDir)) { mkdir($tmpDir, 0775, true); }

        $expires_php = $ttl !== null ? '$expires = ' . (time() + (int)$ttl) . ';' : '';
        $tmp = "$tmpDir/$key." . md5(uniqid('', true)) . ".tmp";
        
        if (file_put_contents($tmp, '<?php ' . $expires_php . ' $val = ' . $export_val . ';', LOCK_EX)) {
            // Rename is used because it is an atomic operation
            if (!rename($tmp, rtrim($cacheDir, '/') . "/$key")) {
                error_log("CACHE ERROR: Failed to move $tmp to $cacheDir/$key. Check folder permissions.");
            }
        }
    }

    public static function get($key, $default = false) {
        if (array_key_exists($key, self::$memory)) {
            if (isset(self::$memoryExpires[$key]) && self::$memoryExpires[$key] !== null && self::$memoryExpires[$key] < time()) {
                unset(self::$memory[$key], self::$memoryExpires[$key]);
            } else {
                return self::$memory[$key];
            }
        }

        $cachePath = self::getCacheDir() . "/$key";
        if (is_file($cachePath)) {
            $expires = null;
            @include $cachePath;
            if (isset($val)) {
                if (isset($expires) && $expires < time()) {
                    @unlink($cachePath);
                    return $default;
                }
                self::$memory[$key] = $val;
                self::$memoryExpires[$key] = $expires;
                return $val;
            }
        } 
        return $default;
    }

    public static function clear($key) {
        unset(self::$memory[$key], self::$memoryExpires[$key]);

        $cacheDir = self::getCacheDir();
        if (!$cacheDir) {
            return;
        }
        $cachePath = rtrim($cacheDir, '/') . "/$key";
        if (is_file($cachePath)) {
            @unlink($cachePath);
        }
    }
}
