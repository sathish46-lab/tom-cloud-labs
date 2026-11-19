<?php
require_once __DIR__ . '/../../load.php';

$admin = AuthMiddleware::requireAdmin();
AuthMiddleware::requireCsrf();

$email = trim($_POST['email'] ?? '');
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    api_json(['status' => 'error', 'error' => 'Valid email required'], 400);
}

if (!empty($_SESSION['impersonator']['username'])) {
    api_json(['status' => 'error', 'error' => 'Exit the current impersonation first'], 400);
}

try {
    $db = DatabaseConnection::getDefaultDatabase();
    $target = $db->users->findOne(['email' => $email]);
    if (!$target || empty($target['username'])) {
        api_json(['status' => 'error', 'error' => 'User not found'], 400);
    }

    $targetUsername = (string) $target['username'];
    $currentUsername = (string)($_SESSION['username'] ?? '');
    if ($targetUsername === $currentUsername) {
        api_json(['status' => 'error', 'error' => 'You are already acting as this user'], 400);
    }

    // Freeze the original identity before overwriting the session.
    $_SESSION['impersonator'] = [
        'username'     => $currentUsername,
        'email'        => $_SESSION['user_email'] ?? $admin->getEmail(),
        'user_id'      => $_SESSION['user_id'] ?? $admin->getUserId(),
        'started_at'   => time(),
        'target_email' => $email,
    ];

    $_SESSION['username']   = $targetUsername;
    $_SESSION['user_email'] = $email;
    if (isset($target['user_id']))  $_SESSION['user_id']  = $target['user_id'];
    if (isset($target['avatar_url'])) $_SESSION['user_avatar'] = $target['avatar_url'];

    AuditLog::log(
        'impersonate',
        'user',
        (string)($target['user_id'] ?? $email),
        [
            'target_email' => $email,
            'target_username' => $targetUsername,
            'impersonator' => $currentUsername,
        ],
        (string)($admin->getUserId() ?? 'unknown')
    );

    api_json([
        'status'  => 'success',
        'as'      => $targetUsername,
        'redirect'=> '/home',
    ], 200);
} catch (Throwable $e) {
    errors_report(['context' => 'admin/impersonate', 'message' => 'impersonate: ' . $e->getMessage()]);
    api_json(['status' => 'error', 'error' => 'Failed to start impersonation'], 400);
}
