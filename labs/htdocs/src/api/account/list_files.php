<?php
require_once __DIR__ . '/../../../src/load.php';

header('Content-Type: application/json');
if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    echo json_encode(['status' => 'error', 'error' => 'Unauthorized']); exit;
}

$user = Session::getUser();
$username = $user->getUsername();
if (!$username) {
    echo json_encode(['status' => 'error', 'error' => 'User not found']); exit;
}

$userId = $user->getUserId();
if (!$userId) {
    echo json_encode(['status' => 'error', 'error' => 'User ID not found']); exit;
}

$filesData = [];

try {
    $client = Storage::getClient();
    $config = get_config('s3');
    
    $prefix = "labassets/uploads/{$userId}/";
    $results = $client->listObjectsV2([
        'Bucket' => $config['bucket'],
        'Prefix' => $prefix
    ]);
    
    if (!empty($results['Contents'])) {
        foreach ($results['Contents'] as $item) {
            $file = basename($item['Key']);
            if (empty($file) || $file === '/') continue;
            
            $size = (int) $item['Size'];
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);
            
            if ($size >= 1048576) {
                $sizeStr = round($size / 1048576, 2) . ' MB';
            } elseif ($size >= 1024) {
                $sizeStr = round($size / 1024, 0) . ' KB';
            } else {
                $sizeStr = $size . ' bytes';
            }
            
            $modifiedTime = is_object($item['LastModified']) ? $item['LastModified']->getTimestamp() : strtotime($item['LastModified']);
            
            $filesData[] = [
                'name' => $file,
                'url' => "/system/user/private/{$userId}/{$file}",
                'size_bytes' => $size,
                'size_formatted' => $sizeStr,
                'is_image' => $isImage,
                'ext' => $ext,
                'modified' => $modifiedTime
            ];
        }
    }
} catch (Exception $e) {
    error_log("MinIO list_files Error: " . $e->getMessage());
}

usort($filesData, function($a, $b) {
    return $b['modified'] - $a['modified'];
});

$totalBytes = array_sum(array_column($filesData, 'size_bytes'));
$maxBytes = 2 * 1024 * 1024 * 1024; // 2 GB
$percent = min(100, round(($totalBytes / $maxBytes) * 100, 1));

if ($totalBytes >= 1073741824) {
    $totalFormatted = round($totalBytes / 1073741824, 2) . ' GB';
} elseif ($totalBytes >= 1048576) {
    $totalFormatted = round($totalBytes / 1048576, 2) . ' MB';
} else {
    $totalFormatted = round($totalBytes / 1024, 0) . ' KB';
}

$totalCount = count($filesData);
$imagesCount = 0;
$othersCount = 0;
foreach ($filesData as $f) {
    if (!empty($f['is_image'])) {
        $imagesCount++;
    } else {
        $othersCount++;
    }
}

$filter = $_GET['filter'] ?? 'all';
$filteredFiles = [];
foreach ($filesData as $f) {
    if ($filter === 'images' && empty($f['is_image'])) continue;
    if ($filter === 'others' && !empty($f['is_image'])) continue;
    $filteredFiles[] = $f;
}
$filteredCount = count($filteredFiles);

$limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 6;
$offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;

$pagedFiles = array_slice($filteredFiles, $offset, $limit);
$hasMore = ($offset + count($pagedFiles)) < $filteredCount;

echo json_encode([
    'status' => 'success', 
    'files' => $pagedFiles,
    'total_count' => $totalCount,
    'images_count' => $imagesCount,
    'others_count' => $othersCount,
    'filtered_count' => $filteredCount,
    'has_more' => $hasMore,
    'storage' => [
        'used_formatted' => $totalFormatted,
        'percent' => $percent
    ]
]);
