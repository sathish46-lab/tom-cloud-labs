<?php
require_once __DIR__ . '/../../../src/load.php';

header('Content-Type: application/json');

$user = AuthMiddleware::requireAuth();
$userId = (int)$user->getUserId();

$labHash = $_GET['hash'] ?? '';

if (!$labHash) {
    echo json_encode(['status' => 'error']); exit;
}

// Check lab status from MongoDB first
try {
    $db = DatabaseConnection::getClient()->selectDatabase('tom_labs_db');
    $col = $db->machine_labs;
    $inst = $col->findOne(['instance_hash' => $labHash], ['projection' => ['status' => 1, 'user_id' => 1]]);
    
    if ($inst) {
        // Ownership check: only the lab owner can view stats
        if ((int)($inst['user_id'] ?? 0) !== $userId) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'error' => 'Forbidden']);
            exit;
        }
        
        $labStatus = $inst['status'] ?? 'unknown';
        
        if ($labStatus === 'paused') {
            echo json_encode(['status' => 'paused']);
            exit;
        }
        
        if ($labStatus !== 'running') {
            echo json_encode(['status' => 'offline']);
            exit;
        }
    }
} catch (Exception $e) {
    // MongoDB unavailable, continue to stats file check
}

// Lab is running — get live stats from docker_stats.json
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
            echo json_encode($allStats[$labHash]);
            exit;
        } else if (isset($allStats['ctf-' . $labHash])) {
            echo json_encode($allStats['ctf-' . $labHash]);
            exit;
        } else {
            echo json_encode(['status' => 'offline']);
            exit;
        }
    } else {
        echo json_encode(['status' => 'initializing']);
        exit;
    }
}

echo json_encode(['status' => 'offline']);
