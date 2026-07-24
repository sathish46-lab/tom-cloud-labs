<?php
require_once __DIR__ . '/../../../src/load.php';

header('Content-Type: application/json');

if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    echo json_encode(['status' => 'error', 'error' => 'Unauthorized']); exit;
}

$hash = $_GET['hash'] ?? '';

if (empty($hash)) {
    echo json_encode(['status' => 'error', 'error' => 'Missing hash']); exit;
}

try {
    $instDb = DatabaseConnection::getClient()->selectDatabase('tom_labs_instances_db');
    $instance = $instDb->instances->findOne(['instance_hash' => $hash]);

    $deploy = $instance['deploy'] ?? [];
    $cfg = $instance['config'] ?? [];
    $buildInfo = $instance['build'] ?? [];

    $rawSize = $buildInfo['image_size'] ?? null;
    $imageTag = $buildInfo['image_tag'] ?? null;
    if (!$rawSize && $imageTag) {
        $sizeCmd = sprintf('docker image inspect %s --format "{{.Size}}" 2>/dev/null', escapeshellarg($imageTag));
        $sizeOutput = shell_exec($sizeCmd);
        if ($sizeOutput && is_numeric(trim($sizeOutput))) {
            $rawSize = (int) trim($sizeOutput);
        }
    }
    $formattedSize = null;
    if ($rawSize && is_numeric($rawSize)) {
        $bytes = (int) $rawSize;
        $formattedSize = [
            'bytes' => $bytes,
            'human' => $bytes >= 1073741824
                ? round($bytes / 1073741824, 2) . ' GB'
                : ($bytes >= 1048576
                    ? round($bytes / 1048576, 1) . ' MB'
                    : round($bytes / 1024, 1) . ' KB')
        ];
    }

    echo json_encode([
        'status'          => 'success',
        'instance_status' => $instance['status'] ?? 'unknown',
        'deployed_status' => $deploy['status'] ?? 'none',
        'credentials'     => $deploy['credentials'] ?? null,
        'build'           => [
            'image_tag'  => $buildInfo['image_tag']  ?? null,
            'built_at'   => $buildInfo['built_at']   ?? null,
            'template'   => $buildInfo['template']   ?? $instance['template'] ?? 'essentials',
            'image_size' => $formattedSize,
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => $e->getMessage()]);
}
