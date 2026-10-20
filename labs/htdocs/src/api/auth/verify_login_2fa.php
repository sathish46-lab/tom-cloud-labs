<?php
require_once __DIR__ . '/../../../src/load.php';

header('Content-Type: application/json');

$email = $_SESSION['2fa_pending_email'] ?? null;
if (!$email) {
    echo json_encode(['status' => 'error', 'error' => 'No pending 2FA login session found.']); exit;
}

$submittedOtp = trim($_POST['otp'] ?? '');

if (empty($submittedOtp) || strlen($submittedOtp) !== 6) {
    echo json_encode(['status' => 'error', 'error' => 'Invalid OTP format.']); exit;
}

$db = DatabaseConnection::getDefaultDatabase();
$userDoc = $db->users->findOne(['email' => $email]);

if (!$userDoc || !isset($userDoc['two_factor_otp']) || !isset($userDoc['two_factor_expires'])) {
    echo json_encode(['status' => 'error', 'error' => 'No active 2FA request found.']); exit;
}

// Strictly check 1-minute expiration
if (time() > $userDoc['two_factor_expires']) {
    echo json_encode(['status' => 'error', 'error' => 'OTP has expired.']); exit;
}

// Attempt limit: max 5 wrong OTPs per issued code (industry practice).
// On the limit the code is invalidated and the pending login dropped.
$attempts = (int) ($userDoc['two_factor_attempts'] ?? 0);
if ($attempts >= 5) {
    $db->users->updateOne(['email' => $email], ['$unset' => [
        'two_factor_otp' => '', 'two_factor_expires' => '', 'two_factor_attempts' => ''
    ]]);
    unset($_SESSION['2fa_pending_email']);
    echo json_encode(['status' => 'error', 'error' => 'Too many incorrect attempts. Please sign in again to request a new code.']); exit;
}

// Verify OTP match
if (!password_verify($submittedOtp, $userDoc['two_factor_otp'])) {
    $attempts++;
    if ($attempts >= 5) {
        $db->users->updateOne(['email' => $email], ['$unset' => [
            'two_factor_otp' => '', 'two_factor_expires' => '', 'two_factor_attempts' => ''
        ]]);
        unset($_SESSION['2fa_pending_email']);
        echo json_encode(['status' => 'error', 'error' => 'Too many incorrect attempts. Please sign in again to request a new code.']); exit;
    }
    $db->users->updateOne(['email' => $email], ['$inc' => ['two_factor_attempts' => 1]]);
    echo json_encode(['status' => 'error', 'error' => 'Incorrect OTP code.']); exit;
}

// Success! Complete the login
try {
    $db->users->updateOne(
        ['email' => $email],
        [
            '$unset' => [
                'two_factor_otp' => '',
                'two_factor_expires' => '',
                'two_factor_attempts' => ''
            ],
            '$set' => [
                'last_login' => time()
            ]
        ]
    );

    $username = $userDoc['username']; 
    session_regenerate_id(true);
    $_SESSION['auth_status'] = \Constants::STATUS_LOGGEDIN;
    $_SESSION['username']    = $username;
    // Absolute session lifetime starts now (30-day cap in initSession).
    $_SESSION['auth_since']  = time();
    unset($_SESSION['token_touch']);
    
    // Clear pending email
    unset($_SESSION['2fa_pending_email']);
    
    // Generate and store session token with device info
    $sessionToken = bin2hex(random_bytes(32));
    $deviceInfo = parse_user_agent();
    $clientIp = get_client_ip();
    
    // Same hygiene as UserSession::authenticate: append new token, drop this
    // browser's replaced token, prune past the 30-day cap, keep newest 10.
    $now = time();
    $prune = ['$gte' => ['$$t.created_at', $now - 30 * 86400]];
    $oldTokenId = !empty($_COOKIE['session_token'])
        ? hash('sha256', (string) $_COOKIE['session_token']) : null;
    if ($oldTokenId !== null) {
        $prune = ['$and' => [
            ['$gte' => ['$$t.created_at', $now - 30 * 86400]],
            ['$ne' => ['$$t.token_id', $oldTokenId]],
        ]];
    }
    $db->users->updateOne(
        ['email' => $email],
        [[
            '$set' => array_merge(
                ['session_tokens' => ['$slice' => [
                    ['$filter' => [
                        'input' => ['$concatArrays' => [
                            ['$ifNull' => ['$session_tokens', []]],
                            [[
                                'token_hash' => ['$literal' => password_hash($sessionToken, PASSWORD_DEFAULT)],
                                'token_id'   => hash('sha256', $sessionToken),
                                'ip' => $clientIp,
                                'browser' => $deviceInfo['browser'],
                                'os' => $deviceInfo['os'],
                                'mobile' => $deviceInfo['mobile'],
                                'created_at' => $now,
                                'last_activity' => $now
                            ]],
                        ]],
                        'as' => 't', 'cond' => $prune,
                    ]],
                    -10,
                ]]],
                ['last_login' => $now],
                get_client_ip_fields($clientIp)
            )
        ]]
    );
    
    // Set cookies with environment-aware expiration from session.json
    $lifetime = get_session_lifetime();
    $domain = get_session_domain();

    $isSecure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || 
                (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    setcookie('session_token', $sessionToken, [
        'expires'  => time() + $lifetime,
        'path'     => '/',
        'domain'   => $domain, 
        'secure'   => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    setcookie('username', $username, [
        'expires'  => time() + $lifetime,
        'path'     => '/',
        'domain'   => $domain, 
        'secure'   => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    $_COOKIE['session_token'] = $sessionToken;

    // Re-sync BOTH PHPSESSID variants to the regenerated id + echo new token.
    Session::refreshAuthCookies();

    echo json_encode(['status' => 'success']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'error' => 'Failed to establish session.']);
}
