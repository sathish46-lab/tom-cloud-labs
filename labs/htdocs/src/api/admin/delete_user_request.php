<?php
/**
 * POST /api/admin/delete_user_request
 *
 * Step 1 of the delete-user flow: mails a 6-digit confirmation code to the
 * acting admin. The code is cached for 2 minutes and only unlocks the actual
 * delete for the same admin + target pair.
 */
require_once __DIR__ . '/../../load.php';
require_once __DIR__ . '/../../utils/user_delete.php';

$admin = AuthMiddleware::requireAdmin();
AuthMiddleware::requireCsrf();

$targetEmail = trim($_POST['email'] ?? '');
if ($targetEmail === '') {
api_json(['status' => 'error', 'error' => 'Target email is required'], 400);
}

$adminEmail = (string)($admin->getEmail() ?? '');
if ($adminEmail === '') {
api_json(['status' => 'error', 'error' => 'Admin identity not found'], 400);
}
if (strtolower($adminEmail) === strtolower($targetEmail)) {
api_json(['status' => 'error', 'error' => 'You cannot delete your own account'], 400);
}

$db = DatabaseConnection::getDefaultDatabase();
$target = $db->users->findOne(['email' => $targetEmail]);
if (!$target) {
api_json(['status' => 'error', 'error' => 'User not found'], 400);
}

$otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
delete_otp_store($adminEmail, $targetEmail, $otp);

$sent = false;
try {
    $sent = \Auth\Mailer::sendDeleteUserOtp(
        $adminEmail,
        (string)($admin->getUsername() ?? $adminEmail),
        $otp,
        $targetEmail
    );
} catch (Throwable $e) {
    errors_from_exception($e, 'admin/delete_user_request');
    $sent = false;
}

if (!$sent) {
    delete_otp_clear($adminEmail, $targetEmail);
api_json(['status' => 'error', 'error' => 'Could not send the confirmation email — check SMTP settings'], 400);
}

api_json([
    'status'     => 'success',
    'sent_to'    => $adminEmail,
    'expires_in' => DELETE_OTP_TTL,
], 200);
