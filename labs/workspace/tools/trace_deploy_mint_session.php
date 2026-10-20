<?php
/**
 * Test-only helper: mint an authenticated session for the deploy tracer.
 * Prints JSON {token, cookie} using the SAME bootstrap/session pattern as the
 * real test suite, so the traced /api/labs/deploy call is a genuine authed hit.
 * Usage: php trace_deploy_mint_session.php <email>
 */
require_once __DIR__ . '/../tests/bootstrap.php';

$email = $argv[1] ?? ('tracer_' . time() . '@example.com');
$token = create_test_user($email, 'user');

// Fetch PHPSESSID the same way tests do
$resp = http_request('GET', '/dashboard', ['cookie' => "session_token=$token"]);
$sid = '';
if (isset($resp['headers']['Set-Cookie'])
    && preg_match('/PHPSESSID=([^;]+)/', $resp['headers']['Set-Cookie'], $m)) {
    $sid = $m[1];
}
$cookie = 'session_token=' . $token . ($sid !== '' ? '; PHPSESSID=' . $sid : '');

echo json_encode([
    'email'  => $email,
    'token'  => $token,
    'cookie' => $cookie,
]);
