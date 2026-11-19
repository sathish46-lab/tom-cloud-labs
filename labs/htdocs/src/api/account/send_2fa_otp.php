<?php
require_once __DIR__ . '/../../../src/load.php';

$user = AuthMiddleware::requireAuth();

$db = DatabaseConnection::getDefaultDatabase();

$email = $user->getEmail();
$username = $user->getUsername() ?? 'User';

// Generate 6-digit OTP
$otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$expires = time() + 60; // strictly 60 seconds
$context = $_GET['action'] ?? 'enable';

try {
    $otpHash = password_hash($otp, PASSWORD_DEFAULT);
    $db->users->updateOne(
        ['email' => $email],
        ['$set' => [
            'two_factor_otp' => $otpHash,
            'two_factor_expires' => $expires
        ]]
    );

    // Send Email
    $sent = \Auth\Mailer::send2faOtp($email, $username, $otp, $context);
    
    if ($sent) {
        api_json(['status' => 'success'], 200);
    } else {
        // Even if email fails locally due to SMTP config, we shouldn't block local dev if we just want to test logic.
        // But for security, we error out if mail fails.
        api_json(['status' => 'error', 'error' => 'Failed to send OTP email. Check SMTP settings.'], 400);
    }
} catch (Exception $e) {
    api_json(['status' => 'error', 'error' => 'Database error.'], 400);
}
