<?php
/**
 * Resend account verification email.
 *
 * Security design:
 *  - Only POST accepted.
 *  - Rate limited (3 per 15 min per email) — see ratelimit.php action rules.
 *  - Only resends to users with is_verified === false and a local password
 *    (Google OAuth users are skipped).
 *  - Always returns a generic success message to prevent email enumeration.
 *  - Generates a fresh verification_token on every request (old token invalidated).
 */

require_once __DIR__ . '/../../../src/load.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_json(['status' => 'error', 'error' => 'Method not allowed.'], 405);
}

$email = trim((string) ($_POST['email'] ?? ''));
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    api_json(['status' => 'error', 'error' => 'Invalid email address.'], 400);
}

$db = DatabaseConnection::getDefaultDatabase();
$user = $db->users->findOne(
    ['email' => $email],
    ['email' => 1, 'username' => 1, 'is_verified' => 1, 'password' => 1, 'auth_provider' => 1]
);

if ($user && empty($user['is_verified']) && !empty($user['password'])) {
    $token = bin2hex(random_bytes(32));
    $db->users->updateOne(
        ['_id' => $user['_id']],
        ['$set' => ['verification_token' => $token]]
    );
    \Auth\Mailer::sendVerification($email, $user['username'] ?? $email, $token);
}

// Always respond generically — never reveal whether an email exists or is verified.
api_json(['status' => 'success', 'message' => 'If an unverified account exists for this email, a verification link has been sent.'], 200);
