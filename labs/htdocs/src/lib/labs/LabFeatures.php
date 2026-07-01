<?php
namespace TomLabs\Labs;

/**
 * LabFeatures
 *
 * Single source of truth for which features are enabled per lab type.
 *
 * How to use:
 *   - To add a new lab type: add a new entry to LAB_FEATURES below.
 *   - To disable a feature globally for ALL labs: set the master kill switch
 *     in Constants.class.php (e.g. FEATURE_HTTP_PROXIES = false).
 *   - To disable a feature for ONE lab only: remove it from that lab's array here.
 *
 * Available features:
 *   always_on       — Keep instance running permanently (no auto-expire)
 *   http_proxies    — Reverse-proxy ports to custom domains over HTTP
 *   startup_script  — Run a custom init.sh on every (re)deploy
 */
class LabFeatures {

    /**
     * Per-lab supported feature list.
     * Edit this map to control what each lab type can use.
     */
    private const LAB_FEATURES = [
        'essentials' => ['always_on', 'http_proxies', 'startup_script'],
        'minio'      => ['always_on'],
        'n8n'        => [],
        'docker_lab' => ['always_on', 'http_proxies', 'startup_script']
    ];

    /**
     * Default features for unknown/new lab types not listed above.
     * Safe baseline — only always_on until explicitly configured.
     */
    private const DEFAULT_FEATURES = ['always_on'];

    /**
     * Get the supported feature list for a lab type.
     */
    public static function getSupportedFeatures(string $labType): array {
        return self::LAB_FEATURES[$labType] ?? self::DEFAULT_FEATURES;
    }

    /**
     * Check if a lab type supports a specific feature.
     * Respects global master kill switches in Constants first.
     */
    public static function supports(string $labType, string $feature): bool {
        // 1. Global master kill switches (Constants.class.php)
        if ($feature === 'always_on'
            && defined('\Constants::FEATURE_ALWAYS_ON')
            && !\Constants::FEATURE_ALWAYS_ON
        ) {
            return false;
        }
        if ($feature === 'http_proxies'
            && defined('\Constants::FEATURE_HTTP_PROXIES')
            && !\Constants::FEATURE_HTTP_PROXIES
        ) {
            return false;
        }
        if ($feature === 'startup_script'
            && defined('\Constants::FEATURE_STARTUP_SCRIPT')
            && !\Constants::FEATURE_STARTUP_SCRIPT
        ) {
            return false;
        }

        // 2. Per-lab config map above
        return in_array($feature, self::getSupportedFeatures($labType), true);
    }
}
