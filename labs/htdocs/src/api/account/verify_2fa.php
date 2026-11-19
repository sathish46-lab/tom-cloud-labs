<?php
require_once __DIR__ . '/../../../src/load.php';

$user = AuthMiddleware::requireAuth();

$db = DatabaseConnection::getDefaultDatabase();

$email = $user->getEmail();
$submittedOtp = trim($_POST['otp'] ?? '');

if (empty($submittedOtp) || strlen($submittedOtp) !== 6) {
    api_json(['status' => 'error', 'error' => 'Invalid OTP format.'], 400);
}

$userDoc = $db->users->findOne(['email' => $email]);

if (!$userDoc || !isset($userDoc['two_factor_otp']) || !isset($userDoc['two_factor_expires'])) {
    api_json(['status' => 'error', 'error' => 'No active 2FA request found.'], 400);
}

// Strictly check 1-minute expiration
if (time() > $userDoc['two_factor_expires']) {
    api_json(['status' => 'error', 'error' => 'OTP has expired.'], 400);
}

// Verify OTP match
if (!password_verify($submittedOtp, $userDoc['two_factor_otp'])) {
    api_json(['status' => 'error', 'error' => 'Incorrect OTP code.'], 400);
}

// Success! Enable 2FA and clear OTP fields
try {
    $db->users->updateOne(
        ['email' => $email],
        ['$set' => [
            'two_factor_enabled' => true
        ], '$unset' => [
            'two_factor_otp' => '',
            'two_factor_expires' => ''
        ]]
    );

    api_json(['status' => 'success'], 200);
} catch (Exception $e) {
    api_json(['status' => 'error', 'error' => 'Failed to save 2FA status.'], 400);
}
