<?php

/**
 * Library Loader
 * This file is responsible for including all the core classes.
 */

// 1. Load Constants (Session depends on this)
require_once __DIR__ . '/core/Constants.class.php';

// 2. Load Exceptions (Cache depends on this)
require_once __DIR__ . '/exceptions/ObjectNotSupportedException.class.php';
require_once __DIR__ . '/exceptions/TemplateUnavailableException.class.php';

// 3. Load Core Utilities
require_once __DIR__ . '/core/Cache.class.php';
require_once __DIR__ . '/core/DatabaseConnection.class.php';

// 4. Load Session (The Main Controller)
require_once __DIR__ . '/core/Session.class.php';
require_once __DIR__ . '/core/UserSession.class.php';
require_once __DIR__ . '/core/WebAPI.class.php';
require_once __DIR__ . '/labs/LabTemplateConfig.php';

# Git version detection
$repo_root = '/var/www/labs'; 
$version_file = $repo_root . '/.version';

global $git_version;

if (file_exists($version_file)) {
    // Priority 1: .version file (Created by CI/CD or locally)
    $git_version = trim(file_get_contents($version_file));
} else {
    // Priority 2: Try running git command (Local fallback)
    $git_bin = '/usr/bin/git'; 
    $cmd = "$git_bin -C " . escapeshellarg($repo_root) . " describe --always 2>&1";
    exec($cmd, $version_mini_hash, $return_var);
    
    if ($return_var === 0 && !empty($version_mini_hash[0])) {
        $git_version = trim($version_mini_hash[0]);
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
    // Only log if in local mode or it's a fatal error
    if (class_exists('Session') && (Session::$environment == "local" || $tag == 'fatal')) {
        
        $config_log = get_config('app_log');
        
        // Ensure the directory exists (Safety check)
        $log_dir = dirname($config_log);
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0775, true);
        }

        // Get Caller Information
        $bt = debug_backtrace();
        $caller = array_shift($bt);
        $file = basename($caller['file']);
        $line = $caller['line'];

        $dateStr = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
        $uri = $_SERVER['REQUEST_URI'] ?? 'N/A';

        // Format: [DATE] [TAG] [IP] [URI] [FILE:LINE] Message
        $message = "[$dateStr] [$tag] [$ip] [$uri] [$file:$line] \n Message: $log \n" . str_repeat("-", 50) . "\n";
        
        // Write to the file defined in config.json
        error_log($message, 3, $config_log);
    }
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

// CENTRALIZED LOGOUT HANDLER
if (isset($_GET['logout']) && $_GET['logout'] == 1) {
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
    // Convert namespace to file path
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