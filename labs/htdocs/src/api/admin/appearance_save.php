<?php
require_once __DIR__ . '/../../load.php';
require_once __DIR__ . '/../../lib/core/Appearance.class.php';

header('Content-Type: application/json');

$admin = AuthMiddleware::requireAdmin();
AuthMiddleware::requireCsrf();

$modes = Appearance::modes();

if (isset($_POST['reset']) && $_POST['reset'] !== '' && $_POST['reset'] !== '0') {
    $before     = Appearance::get();
    $next       = Appearance::defaults();
    $next['updated_at'] = time();
    $next['updated_by'] = (string)$admin->getEmail();
    try {
        $db = DatabaseConnection::getDefaultDatabase();
        $db->global_settings->updateOne(
            ['_id' => Appearance::DOC_ID],
            ['$set' => $next],
            ['upsert' => true]
        );
        Appearance::forget();
        AuditLog::log('update', 'settings', Appearance::DOC_ID, ['action' => 'reset_defaults', 'from' => $before], (string)($admin->getUserId() ?? 'unknown'));
        echo json_encode(['status' => 'success', 'appearance' => $next]);
    } catch (Throwable $e) {
        error_log('appearance_save reset: ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'error' => 'Failed to reset appearance settings']);
    }
    exit;
}

$defaultMode   = trim($_POST['default_mode'] ?? '');
$lockBg        = isset($_POST['lock_bg']) && $_POST['lock_bg'] !== '' && $_POST['lock_bg'] !== '0';
$forceMode     = $lockBg ? trim($_POST['force_mode'] ?? '') : '';
$forceColor    = trim($_POST['force_color_mode'] ?? '');
// Signed-out profile pages: '' = site default, 'owner' = profile owner's choice.
$publicBg      = array_key_exists('public_bg_mode', $_POST)
    ? trim((string)$_POST['public_bg_mode'])
    : (string)Appearance::get()['public_bg_mode'];
$disabledRaw   = $_POST['disabled_modes'] ?? [];
if (!is_array($disabledRaw)) $disabledRaw = $disabledRaw === '' ? [] : [$disabledRaw];

if (!in_array($defaultMode, $modes, true)) {
    echo json_encode(['status' => 'error', 'error' => 'Unknown default background']); exit;
}
if ($forceMode !== '' && !in_array($forceMode, $modes, true)) {
    echo json_encode(['status' => 'error', 'error' => 'Unknown locked background']); exit;
}
if (!in_array($forceColor, ['', 'light', 'dark'], true)) {
    echo json_encode(['status' => 'error', 'error' => 'Color mode must be users choice, light or dark']); exit;
}
if ($publicBg !== '' && $publicBg !== Appearance::PUBLIC_OWNER && !in_array($publicBg, $modes, true)) {
    echo json_encode(['status' => 'error', 'error' => 'Unknown public profile background']); exit;
}

$disabled = [];
foreach ($disabledRaw as $mode) {
    $mode = trim((string)$mode);
    if ($mode === '' || !in_array($mode, $modes, true)) continue;
    if ($mode === $defaultMode) {
        echo json_encode(['status' => 'error', 'error' => 'The default background cannot be hidden']); exit;
    }
    if ($mode === $forceMode) continue; // the locked background always stays available
    $disabled[] = $mode;
}
$disabled = array_values(array_unique($disabled));

$before = Appearance::get();
$next   = Appearance::defaults();
$next['default_mode']     = $defaultMode;
$next['force_mode']       = $forceMode;
$next['disabled_modes']   = $disabled;
$next['force_color_mode'] = $forceColor;
$next['public_bg_mode']   = $publicBg;
$next['updated_at']       = time();
$next['updated_by']       = (string)$admin->getEmail();

try {
    $db = DatabaseConnection::getDefaultDatabase();
    $db->global_settings->updateOne(
        ['_id' => Appearance::DOC_ID],
        ['$set' => $next],
        ['upsert' => true]
    );
    Appearance::forget();

    AuditLog::log(
        'update',
        'settings',
        Appearance::DOC_ID,
        [
            'from' => [
                'default_mode'     => $before['default_mode'],
                'force_mode'       => $before['force_mode'],
                'disabled_modes'   => $before['disabled_modes'],
                'force_color_mode' => $before['force_color_mode'],
                'public_bg_mode'   => $before['public_bg_mode'],
            ],
            'to' => [
                'default_mode'     => $next['default_mode'],
                'force_mode'       => $next['force_mode'],
                'disabled_modes'   => $next['disabled_modes'],
                'force_color_mode' => $next['force_color_mode'],
                'public_bg_mode'   => $next['public_bg_mode'],
            ],
        ],
        (string)($admin->getUserId() ?? 'unknown')
    );

    echo json_encode(['status' => 'success', 'appearance' => $next]);
} catch (Throwable $e) {
    error_log('appearance_save: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'error' => 'Failed to save appearance settings']);
}
