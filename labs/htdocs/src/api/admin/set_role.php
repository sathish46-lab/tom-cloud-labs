<?php
require_once __DIR__ . '/../../load.php';

header('Content-Type: application/json');

$admin = AuthMiddleware::requireAdmin();
AuthMiddleware::requireCsrf();

$allowed = [Constants::GROUP_SUPERUSER, Constants::GROUP_ADMIN, 'user'];
$email   = trim($_POST['email'] ?? '');
$role    = trim($_POST['role'] ?? '');

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'error' => 'Valid email required']); exit;
}
if (!in_array($role, $allowed, true)) {
    echo json_encode(['status' => 'error', 'error' => 'Role must be superuser, admin or user']); exit;
}

try {
    $db = DatabaseConnection::getDefaultDatabase();
    $target = $db->users->findOne(['email' => $email]);
    if (!$target) {
        echo json_encode(['status' => 'error', 'error' => 'User not found']); exit;
    }

    $actingEmail = $admin->getEmail();
    if ($actingEmail !== null && $actingEmail === $email) {
        echo json_encode(['status' => 'error', 'error' => 'You cannot change your own role']); exit;
    }

    $from = $target['role'] ?? 'user';
    if ($from === $role) {
        echo json_encode(['status' => 'success', 'role' => $role, 'unchanged' => true]); exit;
    }

    // Never let the platform end up with zero superusers.
    if ($from === Constants::GROUP_SUPERUSER && $role !== Constants::GROUP_SUPERUSER) {
        $remaining = $db->users->countDocuments([
            'role'  => Constants::GROUP_SUPERUSER,
            'email' => ['$ne' => $email],
        ]);
        if ($remaining < 1) {
            echo json_encode(['status' => 'error', 'error' => 'Cannot demote the last superuser']); exit;
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

    echo json_encode(['status' => 'success', 'role' => $role]);
} catch (Throwable $e) {
    errors_report(['context' => 'admin/set_role', 'message' => 'set_role: ' . $e->getMessage()]);
    echo json_encode(['status' => 'error', 'error' => 'Failed to update role']);
}
