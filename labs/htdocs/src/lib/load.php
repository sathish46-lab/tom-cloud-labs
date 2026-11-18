<?php

/**
 * Library Loader
 * This file is responsible for including all the core classes.
 */

// 1. Load Constants and Env
require_once __DIR__ . '/core/Env.class.php';
require_once __DIR__ . '/core/Constants.class.php';
require_once __DIR__ . '/core/Logger.class.php';

// 2. Load Exceptions (Cache depends on this)
require_once __DIR__ . '/exceptions/ObjectNotSupportedException.class.php';
require_once __DIR__ . '/exceptions/TemplateUnavailableException.class.php';

// 3. Load Core Utilities
require_once __DIR__ . '/core/Cache.class.php';
require_once __DIR__ . '/core/DatabaseConnection.class.php';

// 4. Load Session (The Main Controller)
require_once __DIR__ . '/core/Session.class.php';
require_once __DIR__ . '/core/SessionAuth.class.php';
require_once __DIR__ . '/core/SessionCsrf.class.php';
require_once __DIR__ . '/core/SessionEnv.class.php';
require_once __DIR__ . '/core/SessionRenderer.class.php';
require_once __DIR__ . '/core/UserSession.class.php';
require_once __DIR__ . '/core/WebAPI.class.php';
require_once __DIR__ . '/core/CsrfProtection.class.php';
require_once __DIR__ . '/core/AuthMiddleware.class.php';
require_once __DIR__ . '/core/ChatSettings.class.php';
require_once __DIR__ . '/core/NotificationSettings.class.php';
require_once __DIR__ . '/core/Notification.class.php';
require_once __DIR__ . '/core/UserNotificationSettings.class.php';
require_once __DIR__ . '/labs/LabFeatures.php';
require_once __DIR__ . '/labs/LabTemplateConfig.php';
require_once __DIR__ . '/services/LearnAIOrchestrator.class.php';

# Git version detection
$possible_roots = [
    realpath(__DIR__ . '/../../../..'), // Dev_lab environment
    realpath(__DIR__ . '/../../..'),    // Typical production environment
    '/var/www/labs'                     // Fallback
];

$repo_root = '/var/www/labs';
foreach ($possible_roots as $root) {
    if ($root && (is_dir($root . '/.git') || file_exists($root . '/.version'))) {
        $repo_root = $root;
        break;
    }
}
$version_file = $repo_root . '/.version';

global $git_version;

if (file_exists($version_file)) {
    // Priority 1: .version file (Created by CI/CD or locally)
    $git_version = trim(file_get_contents($version_file));
} else {
    // Priority 2: Try running git command (Local fallback)
    $git_bin = '/usr/bin/git'; 
    
    // Get total commit count
    $count_cmd = "$git_bin -C " . escapeshellarg($repo_root) . " rev-list HEAD --count 2>&1";
    exec($count_cmd, $count_output, $count_var);
    $total_commits = ($count_var === 0 && !empty($count_output[0])) ? trim($count_output[0]) : '0';
    
    // Get short hash
    $hash_cmd = "$git_bin -C " . escapeshellarg($repo_root) . " rev-parse --short HEAD 2>&1";
    exec($hash_cmd, $hash_output, $hash_var);
    $short_hash = ($hash_var === 0 && !empty($hash_output[0])) ? trim($hash_output[0]) : '';
    
    // Get latest tag (if any)
    $tag_cmd = "$git_bin -C " . escapeshellarg($repo_root) . " describe --tags --abbrev=0 2>&1";
    exec($tag_cmd, $tag_output, $tag_var);
    $latest_tag = ($tag_var === 0 && !empty($tag_output[0])) ? trim($tag_output[0]) : '';
    
    if (!empty($latest_tag) && !empty($short_hash)) {
        $git_version = "$latest_tag-$total_commits-g$short_hash";
    } elseif (!empty($short_hash)) {
        $git_version = "v1.0.0-$total_commits-g$short_hash";
    } else {
        $git_version = '1.0.0'; 
    }
}

require_from_json();

// Fix Git Versioning
$git_dir = __DIR__;
if(file_exists($git_dir)){
    $git_dir = '../';
}


$time = microtime();
$time = explode(' ', $time);
$time = $time[1] + $time[0];
global $__start;
$__start = $time;

date_default_timezone_set('Asia/Kolkata');

$_SERVER['UNIQUE_ID'] = uniqid();

/**
 * Scans for a directory and includes all the php files in it.
 */
function render_requires($path, $generateRequires = true) {
    global $git_version; // Use consistent underscore name
    $requires = array("version" => $git_version);
    $paths = array();
    
    $dir = new RecursiveDirectoryIterator($path);
    $iterator = new RecursiveIteratorIterator($dir);
    
    foreach ($iterator as $file) {
        $fname = $file->getFilename();
        if (preg_match('/\.php$/', $fname)) {
            require_once $file->getPathname();
            array_push($paths, $file->getPathname());
        }
    }
    
    $requires["path"] = $paths;
    
    if ($generateRequires) {
        Cache::set('includes.cache', $requires);
    }
}

function moment($time){
    return (new \Moment\Moment($time))->FromNow()->getRelative();
}

function require_from_json(){
    global $git_version;
    $data = Cache::get('includes.cache');
    
    if(empty($data)){
        render_requires(__DIR__);
    } else {
        // logit("Trying to include from cache...", "init");
        if(isset($data['version']) and $data['version'] == $git_version){
            foreach ($data["path"] as $path){
                if(file_exists($path)){
                    require_once $path;
                }
            }
        } else {
            render_requires(__DIR__);
        }
    }
}


function logit($log, $tag = "system") {
    // Map old tags to log levels
    $levelMap = [
        'fatal'   => Logger::LEVEL_CRITICAL,
        'error'   => Logger::LEVEL_ERROR,
        'warning' => Logger::LEVEL_WARNING,
        'debug'   => Logger::LEVEL_DEBUG,
    ];
    $level = $levelMap[$tag] ?? Logger::LEVEL_INFO;
    
    Logger::log($level, $log, ['tag' => $tag]);
}

/**
 * Indents a given string by specified number of spaces.
 * @pram string $string The input string to be indented.
 * @param int $indent Number of spaces to indent. Default is 4.
 * @return string The indented string.
 */

/**
 * Indents a given string by specified number of spaces.
 *
 */
function indent($string, $indent = 4) {
    $lines = explode(PHP_EOL, $string);
    $newline = array();
    $s = str_repeat(' ', $indent); // Simplified space generation
    
    foreach ($lines as $line) {
        array_push($newline, $s . $line); // Fixed typo from $arra_push
    }
    return implode(PHP_EOL, $newline);
}

/**
 * Parse User-Agent string into browser name + OS.
 */
function parse_user_agent($ua = null) {
    $ua = $ua ?? ($_SERVER['HTTP_USER_AGENT'] ?? '');
    $browser = 'Unknown';
    $os = 'Unknown';

    // Browser detection
    if (preg_match('/Edge|Edg\//i', $ua)) {
        $browser = 'Edge';
    } elseif (preg_match('/OPR|Opera/i', $ua)) {
        $browser = 'Opera';
    } elseif (preg_match('/Chrome/i', $ua) && !preg_match('/Edg|Edge|OPR/i', $ua)) {
        $browser = 'Chrome';
    } elseif (preg_match('/Firefox/i', $ua)) {
        $browser = 'Firefox';
    } elseif (preg_match('/Safari/i', $ua) && !preg_match('/Chrome|Chromium/i', $ua)) {
        $browser = 'Safari';
    } elseif (preg_match('/MSIE|Trident/i', $ua)) {
        $browser = 'Internet Explorer';
    }

    // OS detection
    if (preg_match('/Windows/i', $ua)) {
        $os = 'Windows';
    } elseif (preg_match('/Mac OS X/i', $ua)) {
        $os = 'macOS';
    } elseif (preg_match('/iPhone|iPad/i', $ua)) {
        $os = preg_match('/iPad/i', $ua) ? 'iPad' : 'iPhone';
    } elseif (preg_match('/Android/i', $ua)) {
        $os = 'Android';
    } elseif (preg_match('/Linux/i', $ua)) {
        $os = 'Linux';
    } elseif (preg_match('/CrOS/i', $ua)) {
        $os = 'Chrome OS';
    }

    // Mobile detection
    $mobile = (bool) preg_match('/Mobile|Android|iPhone|iPad/i', $ua);

    return compact('browser', 'os', 'mobile');
}

/**
 * Get real client IP behind proxies.
 */
function get_client_ip() {
    $headers = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_REAL_IP',
        'HTTP_X_FORWARDED_FOR',
        'REMOTE_ADDR'
    ];
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = explode(',', $_SERVER[$header])[0];
            $ip = trim($ip);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return '0.0.0.0';
}

/**
 * User-document IP fields: overall last IP plus per-family last IP.
 * Only the family of the current request is written, so a known IPv4
 * is never clobbered by an IPv6 visit (and vice-versa).
 */
function get_client_ip_fields($ip = null) {
    $ip = $ip ?: get_client_ip();
    $fields = ['ip_address' => $ip];
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $fields['ip_address_v6'] = $ip;
    } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $fields['ip_address_v4'] = $ip;
    }
    return $fields;
}

/**
 * New-device login detection + notification.
 *
 * Called from every login path (password, 2FA, Google OAuth) after computing
 * the device fingerprint. Compares (browser, os, mobile, ip) against the
 * user's existing session_tokens. If no match exists, it's a new device:
 *   1. Creates a system notification (owned by the user).
 *   2. Sends a security alert email (if user has security+email on).
 *
 * Silent on failure — a login must never fail because notification logic
 * threw an exception.
 */
function notifyNewDeviceLogin($userDoc, array $deviceInfo, string $clientIp): void {
    try {
        $userDoc = (array) $userDoc;
        $email = (string)($userDoc['email'] ?? '');
        if ($email === '') return;

        // Global gate: notifications fully suppressed.
        if (NotificationSettings::mode() === NotificationSettings::MODE_OFF) return;

        // Per-user gate: security alerts must be enabled.
        if (!UserNotificationSettings::securityEnabled($email)) return;

        // Compare fingerprint against existing session tokens.
        $existingTokens = $userDoc['session_tokens'] ?? [];
        if (!is_array($existingTokens)) $existingTokens = [];

        $isNewDevice = true;
        foreach ($existingTokens as $token) {
            if (!is_array($token)) continue;
            if (
                (string)($token['browser'] ?? '') === $deviceInfo['browser']
                && (string)($token['os'] ?? '') === $deviceInfo['os']
                && (bool)($token['mobile'] ?? false) === (bool)$deviceInfo['mobile']
                && (string)($token['ip'] ?? '') === $clientIp
            ) {
                $isNewDevice = false;
                break;
            }
        }
        if (!$isNewDevice) return;

        // Resolve user id for notification ownership.
        $userId = isset($userDoc['user_id']) ? (string)$userDoc['user_id']
            : (isset($userDoc['_id']) ? (string)$userDoc['_id'] : '');
        $username = (string)($userDoc['username'] ?? 'User');

        $deviceDesc = $deviceInfo['browser'] . ' on ' . $deviceInfo['os'];
        if ($deviceInfo['mobile']) $deviceDesc .= ' (mobile)';
        $deviceDesc .= ' • ' . $clientIp;

        // Create the notification (non-fatal if it fails).
        if ($userId !== '') {
            Notification::create(
                $userId,
                'system',
                'new_device',
                'New device login',
                htmlspecialchars_decode("{$username}, you signed in from a new device: {$deviceDesc}."),
                ['url' => '/account/settings'],
                false,
                null
            );
        }

        // Send security alert email (non-fatal if it fails).
        if (UserNotificationSettings::emailEnabled($email)) {
            $config = get_config('smtp');
            $appUrl = rtrim($config['app_url'] ?? 'https://labs.tomweb.fun', '/');
            \Auth\Mailer::sendNewDeviceAlert($email, $username, $deviceDesc, $clientIp, $appUrl);
        }
    } catch (\Throwable $e) {
        error_log('notifyNewDeviceLogin failed: ' . $e->getMessage());
    }
}

// CENTRALIZED LOGOUT HANDLER (POST only — prevents CSRF logout via img tags)
if (isset($_POST['logout']) && $_POST['logout'] == 1) {
    UserSession::logout(); 
    header("Location: /");
    exit;
}

/**
 * Global helper to retrieve headers accurately
 */
function get_header($name) {
    $name = str_replace("-", "_", strtoupper($name));
    $name = "HTTP_" . $name;
    return isset($_SERVER[$name]) ? $_SERVER[$name] : null;
}

spl_autoload_register(function ($class) {
    // Handle Auth\ namespace -> src/lib/core/Auth/
    if (strpos($class, 'Auth\\') === 0) {
        $relative = str_replace('\\', '/', substr($class, 5));
        $file = __DIR__ . '/core/Auth/' . $relative . '.class.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }

    // TomLabs\Labs\ namespace -> src/lib/labs/
    $path = str_replace(['TomLabs\\Labs\\', '\\'], ['', '/'], $class);
    $file = __DIR__ . "/labs/" . $path . ".class.php";
    
    if (file_exists($file)) {
        require_once $file;
    }
});

// Set the environment to local to enable the timestamp cache-buster
Session::$environment = 'beta'; 

$webAPI = new WebAPI();
$webAPI->initSession();