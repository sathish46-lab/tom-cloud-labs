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

header('Content-Type: application/json');

$admin = AuthMiddleware::requireAdmin();
AuthMiddleware::requireCsrf();

$targetEmail = trim($_POST['email'] ?? '');
if ($targetEmail === '') {
    echo json_encode(['status' => 'error', 'error' => 'Target email is required']);
    exit;
}

$adminEmail = (string)($admin->getEmail() ?? '');
if ($adminEmail === '') {
    echo json_encode(['status' => 'error', 'error' => 'Admin identity not found']);
    exit;
}
if (strtolower($adminEmail) === strtolower($targetEmail)) {
    echo json_encode(['status' => 'error', 'error' => 'You cannot delete your own account']);
    exit;
}

$db = DatabaseConnection::getDefaultDatabase();
$target = $db->users->findOne(['email' => $targetEmail]);
if (!$target) {
    echo json_encode(['status' => 'error', 'error' => 'User not found']);
    exit;
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
    echo json_encode(['status' => 'error', 'error' => 'Could not send the confirmation email — check SMTP settings']);
    exit;
}

echo json_encode([
    'status'     => 'success',
    'sent_to'    => $adminEmail,
    'expires_in' => DELETE_OTP_TTL,
]);
