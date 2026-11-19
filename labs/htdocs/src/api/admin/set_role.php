<?php
require_once __DIR__ . '/../../load.php';

$admin = AuthMiddleware::requireAdmin();
AuthMiddleware::requireCsrf();

$allowed = [Constants::GROUP_SUPERUSER, Constants::GROUP_ADMIN, 'user'];
$email   = trim($_POST['email'] ?? '');
$role    = trim($_POST['role'] ?? '');

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    api_json(['status' => 'error', 'error' => 'Valid email required'], 400);
}
if (!in_array($role, $allowed, true)) {
    api_json(['status' => 'error', 'error' => 'Role must be superuser, admin or user'], 400);
}

try {
    $db = DatabaseConnection::getDefaultDatabase();
    $target = $db->users->findOne(['email' => $email]);
    if (!$target) {
        api_json(['status' => 'error', 'error' => 'User not found'], 400);
    }

    $actingEmail = $admin->getEmail();
    if ($actingEmail !== null && $actingEmail === $email) {
        api_json(['status' => 'error', 'error' => 'You cannot change your own role'], 400);
    }

    $from = $target['role'] ?? 'user';
    if ($from === $role) {
        api_json(['status' => 'success', 'role' => $role, 'unchanged' => true], 200);
    }

    // Never let the platform end up with zero superusers.
    if ($from === Constants::GROUP_SUPERUSER && $role !== Constants::GROUP_SUPERUSER) {
        $remaining = $db->users->countDocuments([
            'role'  => Constants::GROUP_SUPERUSER,
            'email' => ['$ne' => $email],
        ]);
        if ($remaining < 1) {
            api_json(['status' => 'error', 'error' => 'Cannot demote the last superuser'], 400);
        }
    }

    $db->users->updateOne(['email' => $email], ['$set' => ['role' => $role]]);

    AuditLog::log(
        'update',
        'user',
        (string)($target['user_id'] ?? $email),
        ['field' => 'role', 'from' => $from, 'to' => $role, 'target_email' => $email],
        (string)($admin->getUserId() ?? $actingEmail ?? 'unknown')
    );

    api_json(['status' => 'success', 'role' => $role], 200);
} catch (Throwable $e) {
    errors_report(['context' => 'admin/set_role', 'message' => 'set_role: ' . $e->getMessage()]);
    api_json(['status' => 'error', 'error' => 'Failed to update role'], 400);
}
