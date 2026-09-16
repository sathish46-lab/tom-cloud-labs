<?php
/**
 * SessionEnv — Environment, versioning, CDN, meta tags, and utility helpers.
 * Extracted from Session.class.php to separate concerns.
 */
class SessionEnv {

    /**
     * Get the configured environment string.
     */
    public static function getEnvironment() {
        return Session::$environment;
    }

    public static function isBeta() {
        return Session::$environment === 'beta';
    }

    public static function isProd() {
        return Session::$environment === 'prod';
    }

    public static function isAlpha() {
        return Session::$environment === 'alpha';
    }

    /**
     * Get the app version (git hash, manual override, or fallback).
     */
    public static function getVersion() {
        global $git_version;

        $manual_version = get_config('asset_version');
        if (!empty($manual_version)) {
            return $manual_version;
        }

        if (!empty($git_version) && $git_version !== '1.0.0') {
            return $git_version;
        }

        $basePath = realpath(__DIR__ . '/../../');
        $file = $basePath . '/htdocs/js/app.js';
        if (file_exists($file)) {
            return filemtime($file);
        }

        return $git_version ?? '1.0.0';
    }

    /**
     * Build a cache-busted URL using the real Git Hash.
     */
    public static function cacheCDN($url) {
        $version = self::getVersion();
        $separator = (strpos($url, '?') === false) ? '?' : '&';
        return $url . $separator . 'v=' . $version;
    }

    /**
     * Global CDN helper for MinIO S3 assets.
     */
    public static function cdn3($path) {
        $config = get_config('s3');
        if (!$config) {
            return "/assets/" . ltrim($path, '/');
        }
        return rtrim($config['endpoint'], '/') . '/' . $config['bucket'] . '/' . ltrim($path, '/');
    }

    /**
     * Calculate the time taken to render the page.
     */
    public static function getRenderTime() {
        if (!defined('PAGE_START_TIME')) {
            return '0ms';
        }
        $endTime = microtime(true);
        $duration = $endTime - PAGE_START_TIME;
        return number_format($duration * 1000, 2) . ' ms';
    }

    /**
     * Add a meta tag (string or array).
     */
    public static function addMetaTag($tag) {
        if (!Session::get('meta-processed')) {
            if (is_array($tag)) {
                foreach ($tag as $t) {
                    Session::$meta[] = $t;
                }
            } else {
                Session::$meta[] = $tag;
            }
        } else {
            trigger_error(
                'Unable to add meta tags after Session::loadMaster() has been called',
                E_USER_WARNING
            );
        }
    }

    /**
     * Generate a pseudo-random hash (Base64 encoded).
     */
    public static function generatePseudoRandomHash($length = 10) {
        $bytes = openssl_random_pseudo_bytes($length);
        return base64_encode($bytes);
    }

    /**
     * Get processor count (cached).
     */
    public static function getProcessorCount() {
        $ncpu = Cache::get('processor_count');
        if ($ncpu) {
            return $ncpu;
        }
        $cpus = @file_get_contents('/sys/devices/system/cpu/online');
        if (!$cpus) return 1;

        $parts = explode('-', trim($cpus));
        $ncpu = isset($parts[1]) ? (int)$parts[1] + 1 : 1;

        Cache::set('processor_count', $ncpu);
        return $ncpu;
    }

    /**
     * Add a custom CSS file to the page.
     */
    public static function addCustomCss($css) {
        Session::$customCss[] = $css;
    }

    /**
     * Add a custom JS file to the page.
     */
    public static function addCustomJs($js) {
        Session::$customJs[] = $js;
    }

    /**
     * Route URL helper.
     */
    public static function url($route) {
        $routes = [
            'signin'    => '/signin',
            'signup'    => '/signup',
            'logout'    => '/logout',
            'home'      => '/home',
            'quiz'      => '/quiz',
            'quiz_view' => '/quiz/%',
            'dashboard' => '/dashboard',
            'challenges' => '/challenges',
            'verify' => '/verify'
        ];
        return $routes[$route] ?? '/';
    }
}
