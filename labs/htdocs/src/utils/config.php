<?php

/**
 * Unified Configuration Loader
 * Handles reading from env.json and session.json with local fallbacks.
 */

/**
 * Secret-to-env-var mapping.
 * Keys are config paths (dot notation), values are env var names.
 * This allows env.json secrets to be replaced by environment variables.
 */
function get_config($key) {
    static $config_cache = null;

    if ($config_cache === null) {
        // Secret-to-env-var mapping: config key → env var name
        $secretEnvMap = [
            'database_file'           => 'DATABASE_FILE',
            'mongo_pass'              => 'MONGO_PASS',
            'amqp_pass'               => 'RABBITMQ_PASS',
            'api_secret'              => 'API_SECRET',
            'ai_api_key'              => 'AI_API_KEY',
            'ai_internal_token'       => 'AI_INTERNAL_TOKEN',
            'smtp.pass'               => 'SMTP_PASS',
            's3.access_key'           => 'S3_ACCESS_KEY',
            's3.secret_key'           => 'S3_SECRET_KEY',
            'google_oauth.client_id'  => 'GOOGLE_CLIENT_ID',
            'google_oauth.client_secret' => 'GOOGLE_CLIENT_SECRET',
            'gitlab_oauth.client_id'  => 'GITLAB_CLIENT_ID',
            'gitlab_oauth.client_secret' => 'GITLAB_CLIENT_SECRET',
            'wireguard_public_key'    => 'WIREGUARD_PUBLIC_KEY',
        ];

        // Priority 1: Direct env var (flat keys like 'amqp_host')
        $envValue = getenv($key);
        if ($envValue !== false) {
            $config_cache[$key] = $envValue;
            $config_cache[$key . '__env_source'] = true;
        }

        // Priority 2: Mapped env vars for secrets (e.g., 'smtp.pass' → SMTP_PASS)
        foreach ($secretEnvMap as $configKey => $envVar) {
            if (!isset($config_cache[$configKey])) {
                $envVal = getenv($envVar);
                if ($envVal !== false) {
                    $config_cache[$configKey] = $envVal;
                    $config_cache[$configKey . '__env_source'] = true;
                }
            }
        }

        // Priority 3: env.json file (legacy, deprecated — secrets should be in env vars)
        $path = '/var/www/env.json';
        if (!file_exists($path)) {
            $localPath = __DIR__ . '/../../../../env.json';
            if (file_exists($localPath)) $path = $localPath;
        }

        if (file_exists($path)) {
            $data = file_get_contents($path);
            $fileConfig = json_decode($data, true) ?: [];

            // Flatten nested config for merge (e.g., google_oauth.client_id)
            $flat = [];
            foreach ($fileConfig as $k => $v) {
                if (is_array($v)) {
                    foreach ($v as $sk => $sv) {
                        $flat["$k.$sk"] = $sv;
                    }
                } else {
                    $flat[$k] = $v;
                }
            }

            // Merge — env vars (direct + mapped) take precedence over file
            foreach ($flat as $k => $v) {
                if (!isset($config_cache[$k]) && !isset($config_cache[$k . '__env_source'])) {
                    $config_cache[$k] = $v;
                }
            }

            // Warn if env.json still contains secrets
            if (!is_local()) {
                $leakedSecrets = [];
                foreach ($secretEnvMap as $ck => $ev) {
                    if (isset($flat[$ck]) && !isset($config_cache[$ck . '__env_source'])) {
                        $leakedSecrets[] = $ck;
                    }
                }
                if (!empty($leakedSecrets)) {
                    error_log("SECURITY: env.json still contains secrets: " . implode(', ', $leakedSecrets) . ". Move to env vars.");
                }
            }
        }
    }

    return isset($config_cache[$key]) ? $config_cache[$key] : null;
}

function get_session_config($key) {
    static $session_cache = null;

    if ($session_cache === null) {
        $path = '/var/www/session.json';
        if (!file_exists($path)) {
            // Fallback to local workspace
            $localPath = __DIR__ . '/../../../../session.json';
            if (file_exists($localPath)) $path = $localPath;
            else {
                $session_cache = [];
                return null;
            }
        }
        $data = @file_get_contents($path);
        if ($data === false) {
            error_log("CONFIG ERROR: Could not read session.json at " . $path);
            $session_cache = [];
        } else {
            $session_cache = json_decode($data, true) ?: [];
        }
    }
    
    return isset($session_cache[$key]) ? $session_cache[$key] : null;
}

/**
 * Detect if we are in a local development environment
 */
function is_local() {
    $host = explode(':', $_SERVER['HTTP_HOST'] ?? '')[0];
    $local_hosts = ['localhost', '127.0.0.1', 'dev.tomweb.in'];
    return in_array($host, $local_hosts);
}

/**
 * Get the appropriate session lifetime based on environment
 */
function get_session_lifetime() {
    return is_local() ? 
        (get_session_config('lifetime_local') ?? 20) : 
        (get_session_config('lifetime_production') ?? 86400);
}

/**
 * Get the appropriate cookie domain based on environment
 */
function get_session_domain() {
    return is_local() ? 
        (get_session_config('domain_local') ?? '') : 
        (get_session_config('domain_production') ?? '');
}
