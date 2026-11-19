<?php
require_once __DIR__ . '/../../load.php';

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
api_json(['status' => 'error', 'error' => 'Unknown image type'], 400);
    }

    $logoDir = realpath(__DIR__ . '/../../../assets/logo');
    if ($logoDir === false) {
api_json(['status' => 'error', 'error' => 'Logo directory not found'], 400);
    }

    $target = $logoDir . '/' . $targets[$type];
    $bak    = $target . '.bak';

    if (!file_exists($bak)) {
api_json(['status' => 'error', 'error' => 'No backup available'], 400);
    }

    // Swap: current -> tmp, backup -> current, tmp -> backup
    $tmp = $target . '.swapTmp';
    if (file_exists($target)) {
        if (!@rename($target, $tmp)) {
api_json(['status' => 'error', 'error' => 'Failed to swap files'], 400);
        }
    }
    if (!@rename($bak, $target)) {
        if (file_exists($tmp)) @rename($tmp, $target);
api_json(['status' => 'error', 'error' => 'Failed to swap files'], 400);
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

    api_json([
        'status'    => 'success',
        'type'      => $type,
        'url'       => '/assets/logo/' . $targets[$type] . '?v=' . $curV,
        'bak_url'   => '/assets/logo/' . $targets[$type] . '.bak?v=' . $bakV,
        'cur_date'  => date('d M Y H:i', $curV),
        'bak_date'  => $bakV > 0 ? date('d M Y H:i', $bakV) : '',
    ], 200);
    exit;
}

// File field name determines the target: 'favicon' → favicon.png, 'logo' → logo.png
if (isset($_FILES['favicon'])) {
    $type = 'favicon';
} elseif (isset($_FILES['logo'])) {
    $type = 'logo';
} else {
api_json(['status' => 'error', 'error' => 'No file uploaded or the upload failed'], 400);
}

$file = $_FILES[$type];

if ($file['error'] !== UPLOAD_ERR_OK) {
api_json(['status' => 'error', 'error' => 'Upload failed (code ' . $file['error'] . ')'], 400);
}

if ($file['size'] > 2 * 1024 * 1024) {
api_json(['status' => 'error', 'error' => 'Image must be 2MB or smaller'], 400);
}

$info = @getimagesize($file['tmp_name']);
if ($info === false || $info[2] !== IMAGETYPE_PNG) {
api_json(['status' => 'error', 'error' => 'Image must be a valid PNG'], 400);
}

$logoDir = realpath(__DIR__ . '/../../../assets/logo');
if ($logoDir === false) {
api_json(['status' => 'error', 'error' => 'Logo directory not found'], 400);
}

$target = $logoDir . '/' . $targets[$type];

// Single-slot backup of the previous file
if (file_exists($target)) {
    @copy($target, $target . '.bak');
}

if (!move_uploaded_file($file['tmp_name'], $target)) {
api_json(['status' => 'error', 'error' => 'Failed to write the image file'], 400);
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

api_json([
    'status'    => 'success',
    'type'      => $type,
    'url'       => '/assets/logo/' . $targets[$type] . '?v=' . $v,
    'cur_date'  => date('d M Y H:i', $v),
], 200);
