<?php
require_once __DIR__ . '/../../load.php';

header('Content-Type: application/json');

$admin = AuthMiddleware::requireAdmin();
AuthMiddleware::requireCsrf();

$targets = [
    'favicon' => 'favicon.png',
    'logo'    => 'logo.png',
];

// Restore: swap the current image with its .bak (no file upload)
if (isset($_POST['restore']) && $_POST['restore'] === '1') {
    $type = trim($_POST['type'] ?? '');
    if (!isset($targets[$type])) {
        echo json_encode(['status' => 'error', 'error' => 'Unknown image type']);
        exit;
    }

    $logoDir = realpath(__DIR__ . '/../../../assets/logo');
    if ($logoDir === false) {
        echo json_encode(['status' => 'error', 'error' => 'Logo directory not found']);
        exit;
    }

    $target = $logoDir . '/' . $targets[$type];
    $bak    = $target . '.bak';

    if (!file_exists($bak)) {
        echo json_encode(['status' => 'error', 'error' => 'No backup available']);
        exit;
    }

    // Swap: current -> tmp, backup -> current, tmp -> backup
    $tmp = $target . '.swapTmp';
    if (file_exists($target)) {
        if (!@rename($target, $tmp)) {
            echo json_encode(['status' => 'error', 'error' => 'Failed to swap files']);
            exit;
        }
    }
    if (!@rename($bak, $target)) {
        if (file_exists($tmp)) @rename($tmp, $target);
        echo json_encode(['status' => 'error', 'error' => 'Failed to swap files']);
        exit;
    }
    if (file_exists($tmp)) {
        @rename($tmp, $bak);
    }
    @chmod($target, 0644);

    $curV = filemtime($target);
    $bakV = file_exists($bak) ? filemtime($bak) : 0;

    AuditLog::log(
        'update',
        'settings',
        $type,
        ['action' => $type . '_restore'],
        (string)($admin->getUserId() ?? 'unknown')
    );

    echo json_encode([
        'status'    => 'success',
        'type'      => $type,
        'url'       => '/assets/logo/' . $targets[$type] . '?v=' . $curV,
        'bak_url'   => '/assets/logo/' . $targets[$type] . '.bak?v=' . $bakV,
        'cur_date'  => date('d M Y H:i', $curV),
        'bak_date'  => $bakV > 0 ? date('d M Y H:i', $bakV) : '',
    ]);
    exit;
}

// File field name determines the target: 'favicon' → favicon.png, 'logo' → logo.png
if (isset($_FILES['favicon'])) {
    $type = 'favicon';
} elseif (isset($_FILES['logo'])) {
    $type = 'logo';
} else {
    echo json_encode(['status' => 'error', 'error' => 'No file uploaded or the upload failed']);
    exit;
}

$file = $_FILES[$type];

if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['status' => 'error', 'error' => 'Upload failed (code ' . $file['error'] . ')']);
    exit;
}

if ($file['size'] > 2 * 1024 * 1024) {
    echo json_encode(['status' => 'error', 'error' => 'Image must be 2MB or smaller']);
    exit;
}

$info = @getimagesize($file['tmp_name']);
if ($info === false || $info[2] !== IMAGETYPE_PNG) {
    echo json_encode(['status' => 'error', 'error' => 'Image must be a valid PNG']);
    exit;
}

$logoDir = realpath(__DIR__ . '/../../../assets/logo');
if ($logoDir === false) {
    echo json_encode(['status' => 'error', 'error' => 'Logo directory not found']);
    exit;
}

$target = $logoDir . '/' . $targets[$type];

// Single-slot backup of the previous file
if (file_exists($target)) {
    @copy($target, $target . '.bak');
}

if (!move_uploaded_file($file['tmp_name'], $target)) {
    echo json_encode(['status' => 'error', 'error' => 'Failed to write the image file']);
    exit;
}

@chmod($target, 0644);

$v = filemtime($target);

AuditLog::log(
    'update',
    'settings',
    $type,
    ['action' => $type . '_upload', 'size' => $file['size']],
    (string)($admin->getUserId() ?? 'unknown')
);

echo json_encode([
    'status'    => 'success',
    'type'      => $type,
    'url'       => '/assets/logo/' . $targets[$type] . '?v=' . $v,
    'cur_date'  => date('d M Y H:i', $v),
]);
