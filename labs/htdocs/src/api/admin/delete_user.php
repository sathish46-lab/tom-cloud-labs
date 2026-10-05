<?php
/**
 * POST /api/admin/delete_user
 *
 * Step 2 of the delete-user flow: verifies the cached confirmation code, then
 * backs the account up (snapshot + home folder) and purges every live document.
 * The result is restorable from /admin/deleted-users.
 */
require_once __DIR__ . '/../../load.php';
require_once __DIR__ . '/../../utils/user_delete.php';

header('Content-Type: application/json');

$admin = AuthMiddleware::requireAdmin();
AuthMiddleware::requireCsrf();

$email   = trim($_POST['email'] ?? '');
$otp     = trim($_POST['otp'] ?? '');
$confirm = trim($_POST['confirm'] ?? '');

if ($email === '') {
    echo json_encode(['status' => 'error', 'error' => 'Target email is required']);
    exit;
}
if (!hash_equals('DELETE ' . $email, $confirm)) {
    echo json_encode(['status' => 'error', 'error' => 'Type exactly: DELETE ' . $email]);
    exit;
}
if (!preg_match('/^\d{6}$/', $otp)) {
    echo json_encode(['status' => 'error', 'error' => 'Enter the 6-digit confirmation code']);
    exit;
}

$adminEmail = (string)($admin->getEmail() ?? '');
if ($adminEmail === '') {
    echo json_encode(['status' => 'error', 'error' => 'Admin identity not found']);
    exit;
}
if (strtolower($adminEmail) === strtolower($email)) {
    echo json_encode(['status' => 'error', 'error' => 'You cannot delete your own account']);
    exit;
}

$db = DatabaseConnection::getDefaultDatabase();
if (!$db->users->findOne(['email' => $email])) {
    echo json_encode(['status' => 'error', 'error' => 'User not found']);
    exit;
}

$entry = delete_otp_load($adminEmail, $email);
$check = delete_otp_check($entry, $otp);
if (!$check['ok']) {
    if ($entry && (int)($entry['expires'] ?? 0) < time()) {
        delete_otp_clear($adminEmail, $email);
    }
    echo json_encode(['status' => 'error', 'error' => $check['error']]);
    exit;
}

delete_otp_clear($adminEmail, $email);

$result = user_delete_run($email, $adminEmail, $admin->getUserId());
echo json_encode($result);
