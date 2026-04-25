<?php
header('Content-Type: application/json');
$labHash = $_GET['hash'] ?? '';

if (!$labHash) {
    echo json_encode(['status' => 'error']); exit;
}

$cacheFile = '/dev/shm/docker_stats.json';

if (file_exists($cacheFile)) {
    $allStats = json_decode(file_get_contents($cacheFile), true);
    
    if (isset($allStats[$labHash])) {
        echo json_encode($allStats[$labHash]);
    } else {
        echo json_encode(['status' => 'offline']);
    }
} else {
    echo json_encode(['status' => 'initializing']);
}