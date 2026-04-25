<?php

class Cache {
    public static function set($key, $val) {
        if (is_object($val)) {
            throw new ObjectNotSupportedException;
        }

        $val = var_export($val, true);
        $val = str_replace('stdClass::__set_state', '(object)', $val);

        $cacheDir = get_config('app_cache');
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

        $tmpDir = "/tmp/labs";
        if (!is_dir($tmpDir)) { mkdir($tmpDir, 0775, true); }

        $tmp = "$tmpDir/$key." . md5(uniqid('', true)) . ".tmp";
        
        if (file_put_contents($tmp, '<?php $val = ' . $val . ';', LOCK_EX)) {
            // Rename is used because it is an atomic operation
            if (!rename($tmp, rtrim($cacheDir, '/') . "/$key")) {
                error_log("CACHE ERROR: Failed to move $tmp to $cacheDir/$key. Check folder permissions.");
            }
        }
    }

    public static function get($key, $default = false) {
        $cachePath = get_config('app_cache') . "/$key";
        if (is_file($cachePath)) {
            @include $cachePath;
            return isset($val) ? $val : $default;
        } 
        return $default;
    }
}