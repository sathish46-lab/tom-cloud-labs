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

$admin = AuthMiddleware::requireAdmin();
AuthMiddleware::requireCsrf();

$email   = trim($_POST['email'] ?? '');
$otp     = trim($_POST['otp'] ?? '');
$confirm = trim($_POST['confirm'] ?? '');

if ($email === '') {
api_json(['status' => 'error', 'error' => 'Target email is required'], 400);
}
if (!hash_equals('DELETE ' . $email, $confirm)) {
api_json(['status' => 'error', 'error' => 'Type exactly: DELETE ' . $email], 400);
}
if (!preg_match('/^\d{6}$/', $otp)) {
api_json(['status' => 'error', 'error' => 'Enter the 6-digit confirmation code'], 400);
}

$adminEmail = (string)($admin->getEmail() ?? '');
if ($adminEmail === '') {
api_json(['status' => 'error', 'error' => 'Admin identity not found'], 400);
}
if (strtolower($adminEmail) === strtolower($email)) {
api_json(['status' => 'error', 'error' => 'You cannot delete your own account'], 400);
}

$db = DatabaseConnection::getDefaultDatabase();
if (!$db->users->findOne(['email' => $email])) {
api_json(['status' => 'error', 'error' => 'User not found'], 400);
}

$entry = delete_otp_load($adminEmail, $email);
$check = delete_otp_check($entry, $otp);
if (!$check['ok']) {
    if ($entry && (int)($entry['expires'] ?? 0) < time()) {
        delete_otp_clear($adminEmail, $email);
    }
api_json(['status' => 'error', 'error' => $check['error']], 400);
}

delete_otp_clear($adminEmail, $email);

$result = user_delete_run($email, $adminEmail, $admin->getUserId());
api_json($result, 200);
