<?php
require_once __DIR__ . '/../../../src/load.php';

$user = AuthMiddleware::requireAuth();
$userId = (int)$user->getUserId();

$labHash = $_GET['hash'] ?? '';

if (!$labHash) {
    api_json(['status' => 'error'], 400);
}

// Check lab status from MongoDB first
try {
    $db = DatabaseConnection::getClient()->selectDatabase('tom_labs_db');
    $col = $db->machine_labs;
    $inst = $col->findOne(['instance_hash' => $labHash], ['projection' => ['status' => 1, 'user_id' => 1]]);
    
    if ($inst) {
        // Ownership check: only the lab owner can view stats
        if ((int)($inst['user_id'] ?? 0) !== $userId) {
api_json(['status' => 'error', 'error' => 'Forbidden'], 403);
        }
        
        $labStatus = $inst['status'] ?? 'unknown';
        
        if ($labStatus === 'paused') {
api_json(['status' => 'paused'], 200);
        }
        
        if ($labStatus !== 'running') {
api_json(['status' => 'offline'], 200);
        }
    }
} catch (Exception $e) {
    // MongoDB unavailable, continue to stats file check
}

// Lab is running — try the Redis-backed live stats store first
$redisKeys = [$labHash, 'ctf-' . $labHash];
try {
    $redis = new Redis();
    if (@$redis->connect('127.0.0.1', 6379, 0.5)) {
        if (function_exists('get_config') ? $redis->auth(get_config('redis_admin_pass')) : false) {
            foreach ($redisKeys as $rk) {
                $raw = $redis->get('tomlabs:stats:' . $rk);
                if (is_string($raw) && $raw !== '') {
                    echo $raw;
                    exit;
                }
            }
        }
        $redis->close();
    }
} catch (Throwable $redisError) {
    // Redis unavailable — fall back to the file cache below
}

// Fallback — get live stats from docker_stats.json
$cacheFile = '/dev/shm/docker_stats.json';
$retryCount = 0;
$maxRetries = 3;

while ($retryCount < $maxRetries) {
    if (file_exists($cacheFile)) {
        $content = file_get_contents($cacheFile);
        
        if ($content === false || $content === '') {
            $retryCount++;
            usleep(100000);
            continue;
        }
        
        $allStats = json_decode($content, true);
        
        if ($allStats === null) {
            $retryCount++;
            usleep(100000);
            continue;
        }
        
        if (isset($allStats[$labHash])) {
api_json($allStats[$labHash], 200);
        } else if (isset($allStats['ctf-' . $labHash])) {
api_json($allStats['ctf-' . $labHash], 200);
        } else {
api_json(['status' => 'offline'], 200);
        }
    } else {
api_json(['status' => 'initializing'], 200);
    }
}

api_json(['status' => 'offline'], 200);
