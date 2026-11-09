<?php
/**
 * POST /api/admin/clear_sessions
 *
 * Deletes ALL PHP session files under the session save path — instantly
 * logging out every signed-in user, including the admin who ran it.
 * Each user must sign in again; auth_status/username/CSRF are all discarded.
 *
 * Response: {"status":"success","removed":N,"failed":N} — flattened into the
 * global failure counters when any file could not be unlinked.
 */
require_once __DIR__ . '/../../load.php';

header('Content-Type: application/json');

$admin = AuthMiddleware::requireAdmin();
AuthMiddleware::requireCsrf();

$sessionPath = rtrim(session_save_path() ?: sys_get_temp_dir(), '/') . '/';
$removed = 0;
$failed  = 0;

// Audit first: the user identity is still alive at this point.
AuditLog::log(
    'clear',
    'all_sessions',
    'sessions',
    ['removed_by' => (string)($admin->getEmail() ?? $_SESSION['username'] ?? 'unknown')]
);

$files = glob($sessionPath . 'sess_*');
if (is_array($files)) {
    foreach ($files as $file) {
        if (@unlink($file)) {
            $removed++;
        } else {
            $failed++;
        }
    }
}

// Wipe our own session last: clear entrydata so shutdown does not rewrite
// sess_<own id>, destroy it, and expire the cookie client-side.
$_SESSION = [];
if (session_status() === PHP_SESSION_ACTIVE) {
    @unlink($sessionPath . 'sess_' . session_id());
    session_destroy();
}
if (ini_get('session.use_cookies')) {
    setcookie(session_name(), '', time() - 3600, ini_get('session.cookie_path') ?: '/', ini_get('session.cookie_domain') ?: '', (bool)ini_get('session.cookie_secure'));
}

if ($failed > 0) {
    echo json_encode(['status' => 'error', 'error' => "$failed session file(s) could not be deleted", 'removed' => $removed, 'failed' => $failed], JSON_PRETTY_PRINT);
} else {
    echo json_encode(['status' => 'success', 'message' => 'All sessions cleared. Every user must sign in again.', 'removed' => $removed], JSON_PRETTY_PRINT);
}
