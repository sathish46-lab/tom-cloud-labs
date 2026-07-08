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

$safeUsername = preg_replace('/[^a-zA-Z0-9_-]/', '', $username);
$uploadDir = __DIR__ . '/../../../uploads/users/' . $safeUsername;

$filesData = [];

if (is_dir($uploadDir)) {
    $files = scandir($uploadDir);
    if ($files !== false) {
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            
            $filePath = $uploadDir . '/' . $file;
            if (is_file($filePath)) {
                $size = filesize($filePath);
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                
                if ($size >= 1048576) {
                    $sizeStr = round($size / 1048576, 2) . ' MB';
                } elseif ($size >= 1024) {
                    $sizeStr = round($size / 1024, 0) . ' KB';
                } else {
                    $sizeStr = $size . ' bytes';
                }
                
                $filesData[] = [
                    'name' => $file,
                    'url' => '/uploads/users/' . $safeUsername . '/' . $file,
                    'size_bytes' => $size,
                    'size_formatted' => $sizeStr,
                    'is_image' => $isImage,
                    'ext' => $ext,
                    'modified' => filemtime($filePath)
                ];
            }
        }
    }
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
