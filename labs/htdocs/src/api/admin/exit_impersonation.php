<?php
require_once __DIR__ . '/../../load.php';

header('Content-Type: application/json');

// Target identity has no admin rights, so resolve the original superuser.
$admin = AuthMiddleware::requireAdmin();
AuthMiddleware::requireCsrf();

$imp = $_SESSION['impersonator'] ?? null;
if (empty($imp['username'])) {
    echo json_encode(['status' => 'error', 'error' => 'Not currently impersonating anyone']); exit;
}

try {
    $_SESSION['username'] = (string) $imp['username'];
    if (isset($imp['email']))    $_SESSION['user_email'] = $imp['email'];
    if (isset($imp['user_id']))  $_SESSION['user_id']    = $imp['user_id'];
    unset($_SESSION['user_avatar']);

    AuditLog::log(
        'stop_impersonating',
        'user',
        (string)($imp['user_id'] ?? $imp['email'] ?? 'unknown'),
        [
            'restored_to'  => $imp['username'],
            'was_viewing'  => $imp['target_email'] ?? null,
            'duration_sec' => isset($imp['started_at']) ? time() - (int)$imp['started_at'] : null,
        ],
        (string)($admin->getUserId() ?? 'unknown')
    );

    unset($_SESSION['impersonator']);

    echo json_encode(['status' => 'success', 'restored' => $imp['username']]);
} catch (Throwable $e) {
    error_log('exit_impersonation: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'error' => 'Failed to exit impersonation']);
}
