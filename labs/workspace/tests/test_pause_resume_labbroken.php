<?php
/**
 * test_pause_resume_labbroken.php
 *
 * When a machine-lab record still says "running" but the container is gone
 * (crash, manual docker stop, OOM kill), pause/resume must NOT leak engine
 * internals. Both APIs answer 409 with code "lab_broken" and an actionable
 * message; the engine reports the unusable container as rc=3.
 *
 * Categories: happy-ish boundary (stale status), auth boundary, bad input,
 * failure path. A real running->paused transition is not covered here: it
 * needs a live machine container (covered manually / by labsctl itself).
 */

require_once __DIR__ . '/bootstrap.php';

// The salt is duplicated from User::getLabHash — keep in sync.
define('LAB_HASH_SALT', '8b51626f3a468904e8b6f83747f2fcf1');

function lab_broken_test_hash(string $email, string $labName): string {
    return md5($email . $labName . LAB_HASH_SALT);
}

function lab_broken_test_setup_lab(string $email, string $labName, string $status): string {
    $db = DatabaseConnection::getDefaultDatabase();
    $hash = lab_broken_test_hash($email, $labName);
    $db->machine_labs->deleteOne(['instance_hash' => $hash]);
    $db->machine_labs->insertOne([
        'instance_hash' => $hash,
        'name' => 'broken-test-' . $labName,
        'lab_type' => $labName,
        'template_name' => $labName,
        'user_email' => $email,
        'status' => $status,
        'created_at' => time(),
        'install_type' => 'machine',
    ]);
    return $hash;
}

function lab_broken_test_teardown(string $email, string $labName): void {
    $db = DatabaseConnection::getDefaultDatabase();
    $db->machine_labs->deleteOne([
        'instance_hash' => lab_broken_test_hash($email, $labName)
    ]);
}

/**
 * The CSRF token lives in the PHP session, so the session cookie has to travel
 * with every request that must satisfy requireCsrf(). (Copied from the
 * test_admin_appearance.php pattern.)
 */
function session_cookie(string $sessionToken, string $path = '/dashboard'): array {
    $resp = http_request('GET', $path, ['cookie' => "session_token=$sessionToken"]);
    $sid = '';
    if (isset($resp['headers']['Set-Cookie'])
        && preg_match('/PHPSESSID=([^;]+)/', $resp['headers']['Set-Cookie'], $m)) {
        $sid = $m[1];
    }
    $csrf = null;
    if (preg_match('/<meta name="csrf-token" content="([^"]+)">/', $resp['body'], $m)) {
        $csrf = $m[1];
    }
    return [
        'cookie' => 'session_token=' . $sessionToken . ($sid !== '' ? '; PHPSESSID=' . $sid : ''),
        'csrf'   => $csrf,
    ];
}

// ── Setup ──
$email  = 'pausebroken_' . time() . '@example.com';
$token  = create_test_user($email);
$user   = session_cookie($token);

$resp = http_request('GET', '/dashboard', ['cookie' => "session_token=$token"]);
test("auth bootstrap (dashboard reachable)", $resp['status'] === 200);

// ── Auth boundary: no session at all ──
$resp = http_request('POST', '/api/labs/pause', ['body' => ['lab' => 'essentials']]);
$json = json_decode($resp['body'], true);
test("pause without session is refused", $resp['status'] === 401 || $resp['status'] === 403);
test("pause without session leaks no lab hash", strpos($resp['body'], md5('guestessentials' . LAB_HASH_SALT)) === false);

// ── Bad input: unknown lab type (no machine_labs record) ──
$resp = http_request('POST', '/api/labs/pause', [
    'cookie' => $user['cookie'],
    'body'   => ['lab' => 'ghost_lab_type'],
]);
$json = json_decode($resp['body'], true);
test("pause unknown lab -> 409 lab_broken",
    $resp['status'] === 409 && ($json['code'] ?? '') === 'lab_broken',
    'status=' . $resp['status'] . ' code=' . ($json['code'] ?? '-'));
test("pause unknown lab message is actionable (no internals)",
    isset($json['message'])
    && $json['message'] === 'Somehow the lab is broken. Redeploy it to continue learning.'
    && strpos($json['message'], 'labsctl') === false
    && strpos($json['message'], 'rc=') === false,
    !empty($json['message']) ? 'got: ' . $json['message'] : 'no message');

// ── Stale status: DB says running, container does not exist ──
$hash = lab_broken_test_setup_lab($email, 'essentials', 'running');
$resp = http_request('POST', '/api/labs/pause', [
    'cookie' => $user['cookie'],
    'body'   => ['lab' => 'essentials'],
]);
$json = json_decode($resp['body'], true);
test("pause stale lab -> 409 lab_broken",
    $resp['status'] === 409 && ($json['code'] ?? '') === 'lab_broken',
    'status=' . $resp['status'] . ' code=' . ($json['code'] ?? '-'));
test("pause stale lab hides engine output", strpos($resp['body'], 'docker') === false && strpos($resp['body'], 'labsctl') === false);

// The engine flipped the lying status so the UI badge stops saying running.
$statusRow = DatabaseConnection::getDefaultDatabase()->machine_labs->findOne(
    ['instance_hash' => $hash],
    ['projection' => ['status' => 1]]
);
test("stale lab is marked need_redeploy by the engine",
    ($statusRow['status'] ?? '') === 'need_redeploy',
    'status=' . ($statusRow['status'] ?? 'missing'));

// ── Resume on stale lab ──
$resp = http_request('POST', '/api/labs/resume', [
    'cookie' => $user['cookie'],
    'body'   => ['lab' => 'essentials'],
]);
$json = json_decode($resp['body'], true);
test("resume stale lab -> 409 lab_broken",
    $resp['status'] === 409 && ($json['code'] ?? '') === 'lab_broken',
    'status=' . $resp['status'] . ' code=' . ($json['code'] ?? '-'));

// ── DB-status mismatch without a working state machine: paused elsewhere ──
lab_broken_test_teardown($email, 'essentials');
lab_broken_test_setup_lab($email, 'essentials', 'paused');
$resp = http_request('POST', '/api/labs/pause', [
    'cookie' => $user['cookie'],
    'body'   => ['lab' => 'essentials'],
]);
$json = json_decode($resp['body'], true);
test("pause when already paused -> 409 lab_broken",
    $resp['status'] === 409 && ($json['code'] ?? '') === 'lab_broken',
    'status=' . $resp['status'] . ' code=' . ($json['code'] ?? '-'));

lab_broken_test_teardown($email, 'essentials');
cleanup_test_user($email);

test("no machine_labs leftovers", DatabaseConnection::getDefaultDatabase()->machine_labs->countDocuments(
    ['user_email' => $email]
) === 0);

test_summary();
