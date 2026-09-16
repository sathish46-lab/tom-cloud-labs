<?php
/**
 * Test P1+P2: Login rate limiting, session fixation, password change rate limiting
 *
 * REAL RUNTIME TEST — Makes actual HTTP requests to verify:
 * 1. Login endpoint returns 429 after 5 failed attempts (15 min window)
 * 2. Session ID is regenerated on successful login (fixation protection)
 * 3. Password change has rate limit rule (3 per hour)
 *
 * Usage:
 *   php workspace/tests/test_p1p2_security.php
 */

require_once __DIR__ . '/bootstrap.php';

echo "=== P1+P2: Login Rate Limit + Session Fixation + Password Change Rate Limit ===\n\n";

// ════════════════════════════════════════════════════════
// PART 1: Login Rate Limiting (5 attempts per 15 min)
// ════════════════════════════════════════════════════════
echo "--- Login Rate Limiting ---\n";

$ratelimitPath = SRC_PATH . '/utils/ratelimit.php';
test("ratelimit.php exists", file_exists($ratelimitPath));

if (file_exists($ratelimitPath)) {
    $src = file_get_contents($ratelimitPath);
    test("Login rate limit rule exists (signin pattern)", strpos($src, 'signin') !== false);
    test("Login rate limit key is auth:rl:signin", strpos($src, 'auth:rl:signin') !== false);
    test("Login rate limit is 5 attempts", strpos($src, "'limit'   => 5") !== false);
    test("Login rate limit window is 900s (15 min)", strpos($src, "'window'  => 900") !== false);
}

// ── Runtime: Hit login endpoint 6 times with wrong password ──
echo "\n--- Runtime Login Rate Limit Test ---\n";

$db = DatabaseConnection::getDefaultDatabase();
$loginEmail = 'ratelimit_login_' . time() . '@example.com';
$db->users->deleteMany(['email' => $loginEmail]);
$db->users->insertOne([
    'email' => $loginEmail,
    'username' => 'rl_login_test',
    'role' => 'user',
    'password' => password_hash('CorrectPass123!', PASSWORD_BCRYPT),
    'created_at' => time(),
    'is_verified' => true,
]);

// Clear any existing rate limit files for this test email
$rlDir = is_dir('/dev/shm') && is_writable('/dev/shm') ? '/dev/shm/ratelimit_actions' : '/tmp/ratelimit_actions';
if (is_dir($rlDir)) {
    foreach (glob($rlDir . '/*.count') as $f) { @unlink($f); }
}

// Send 6 failed login attempts to /signin (the working route)
$rateLimited = false;
$lastResponse = null;
for ($i = 1; $i <= 6; $i++) {
    $response = http_request('POST', '/signin', [
        'body' => ['email' => $loginEmail, 'password' => 'WrongPassword' . $i . '!'],
    ]);
    $lastResponse = $response;

    if ($response['status'] === 429) {
        $rateLimited = true;
        test("Login attempt $i triggers 429 Rate Limited", true);
        break;
    }
}

test("Rate limit triggers after 5 failed attempts", $rateLimited,
    $rateLimited ? "" : "Expected 429 after 6 attempts, got " . ($lastResponse['status'] ?? 'unknown'));

// Verify the rate limit response has proper JSON
if ($rateLimited && isset($lastResponse['body_json'])) {
    test("Rate limit response has error field", isset($lastResponse['body_json']['error']));
    test("Rate limit response has rate_limited flag", ($lastResponse['body_json']['rate_limited'] ?? false) === true);
    test("Rate limit response has retry_after", isset($lastResponse['body_json']['retry_after']));
}

// Verify rate limit file was created
$rateLimitFiles = glob($rlDir . '/*.count');
test("Rate limit counter file created", count($rateLimitFiles) > 0);
if (count($rateLimitFiles) > 0) {
    $count = (int)file_get_contents($rateLimitFiles[0]);
    test("Rate limit counter reached 6", $count === 6);
}

// ── Cleanup rate limit files ──
if (is_dir($rlDir)) {
    foreach (glob($rlDir . '/*.count') as $f) { @unlink($f); }
}

// ════════════════════════════════════════════════════════
// PART 2: Session Fixation Protection
// ════════════════════════════════════════════════════════
echo "\n--- Session Fixation Protection ---\n";

$userSessionPath = SRC_PATH . '/lib/core/UserSession.class.php';
test("UserSession.class.php exists", file_exists($userSessionPath));

if (file_exists($userSessionPath)) {
    $src = file_get_contents($userSessionPath);
    test("session_regenerate_id() called on login", strpos($src, 'session_regenerate_id') !== false);
    test("session_regenerate_id(true) — old session deleted", strpos($src, 'session_regenerate_id(true)') !== false);
}

// ── Runtime: Verify session changes on login ──
echo "\n--- Runtime Session Fixation Test ---\n";

$fixationEmail = 'session_fix_' . time() . '@example.com';
$fixationPass = 'FixationTest123!';
$db->users->deleteMany(['email' => $fixationEmail]);
$db->users->insertOne([
    'email' => $fixationEmail,
    'username' => 'session_fix_test',
    'role' => 'user',
    'password' => password_hash($fixationPass, PASSWORD_BCRYPT),
    'created_at' => time(),
    'is_verified' => true,
]);

// Step 1: GET /signin to get a session ID (use raw curl to capture all Set-Cookie headers)
$ch = curl_init(TEST_BASE_URL . '/signin');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => true,
    CURLOPT_CUSTOMREQUEST => 'GET',
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTPHEADER => ['Host: ' . TEST_HOST_HEADER],
]);
$rawResponse = curl_exec($ch);
curl_close($ch);

// Parse PHPSESSID from Set-Cookie headers
$sessionIdBefore = null;
if (preg_match('/PHPSESSID=([a-f0-9]+)/', $rawResponse, $m)) {
    $sessionIdBefore = $m[1];
}
test("Got session ID before login", $sessionIdBefore !== null,
    "PHPSESSID: " . substr($sessionIdBefore ?? '', 0, 8));

// Extract CSRF token from body
$headerSize = strpos($rawResponse, "\r\n\r\n") + 4;
$body = substr($rawResponse, $headerSize);
$csrfToken = null;
if (preg_match('/name="_csrf_token"\s+value="([^"]+)"/', $body, $m)) {
    $csrfToken = $m[1];
} elseif (preg_match('/value="([^"]+)"\s+name="_csrf_token"/', $body, $m)) {
    $csrfToken = $m[1];
}
test("Got CSRF token from login form", $csrfToken !== null);

// Step 2: Login with correct credentials (using the session cookie + CSRF token)
$ch = curl_init(TEST_BASE_URL . '/signin');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => true,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_COOKIE => "PHPSESSID=$sessionIdBefore",
    CURLOPT_POSTFIELDS => http_build_query([
        '_csrf_token' => $csrfToken,
        'email' => $fixationEmail,
        'password' => $fixationPass,
    ]),
    CURLOPT_HTTPHEADER => [
        'Host: ' . TEST_HOST_HEADER,
        'Content-Type: application/x-www-form-urlencoded',
    ],
]);
$rawLoginResponse = curl_exec($ch);
curl_close($ch);

// Extract new PHPSESSID from login response
$sessionIdAfter = null;
if (preg_match('/PHPSESSID=([a-f0-9]+)/', $rawLoginResponse, $m)) {
    $sessionIdAfter = $m[1];
}

test("Session ID changed after login",
    $sessionIdBefore !== null && $sessionIdAfter !== null && $sessionIdBefore !== $sessionIdAfter,
    "Before: " . substr($sessionIdBefore ?? '', 0, 8) . ", After: " . substr($sessionIdAfter ?? '', 0, 8));

// ── Cleanup ──
$db->users->deleteMany(['email' => $fixationEmail]);

// ════════════════════════════════════════════════════════
// PART 3: Password Change Rate Limiting (3 per hour)
// ════════════════════════════════════════════════════════
echo "\n--- Password Change Rate Limiting ---\n";

if (file_exists($ratelimitPath)) {
    $src = file_get_contents($ratelimitPath);
    test("Password change rate limit rule exists (change_password pattern)", strpos($src, 'change_password') !== false);
    test("Password change rate limit key", strpos($src, 'account:rl:change_password') !== false);
    test("Password change limit is 3 per hour", strpos($src, "'limit'   => 3") !== false);
    test("Password change window is 3600s (1 hour)", strpos($src, "'window'  => 3600") !== false);
}

// ── Cleanup ──
$db->users->deleteMany(['email' => $loginEmail]);

test_summary();
