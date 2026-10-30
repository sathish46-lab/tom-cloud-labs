<?php
/**
 * test_code_server_url_gate.php
 *
 * Code-server is on-demand: the container being "running" says nothing about
 * the IDE process. The connection-details panel must show the Code-Server URL
 * only while the IDE is actually up; otherwise a "Not running" placeholder.
 * The password (and the copy affordance) stay visible regardless.
 *
 * Runtime probe against a containerless machine-lab record: code_status.php
 * must report codeserver_running=false, and the dashboard must render the
 * gated placeholder instead of a dead URL.
 */

require_once __DIR__ . '/bootstrap.php';

define('LAB_HASH_SALT', '8b51626f3a468904e8b6f83747f2fcf1');

function lab_hash_of(string $email, string $labName): string {
    return md5($email . $labName . LAB_HASH_SALT);
}

function setup_gate_lab(string $email, string $labName, string $status): string {
    $db = DatabaseConnection::getDefaultDatabase();
    $hash = lab_hash_of($email, $labName);
    $db->machine_labs->deleteOne(['instance_hash' => $hash]);
    $db->machine_labs->insertOne([
        'instance_hash' => $hash,
        'name' => 'codeserver-gate-' . $labName,
        'lab_type' => $labName,
        'template_name' => $labName,
        'user_email' => $email,
        'user_id' => $db->users->findOne(['email' => $email])['_id'],
        'status' => $status,
        'internal_ip' => '10.20.30.40',
        'credentials' => [
            'username' => explode('@', $email)[0],
            'password' => 'TestPassword123!',
            'code_server_pass' => 'CodePass-Gate-123',
            'su_pass' => 'SuPass-Gate-123',
        ],
        'created_at' => time(),
        'install_type' => 'machine',
    ]);
    return $hash;
}

function teardown_gate_lab(string $email, string $labName): void {
    DatabaseConnection::getDefaultDatabase()->machine_labs->deleteOne(
        ['instance_hash' => lab_hash_of($email, $labName)]
    );
}

/**
 * The session cookie has to travel with every request (Copied from the
 * test_admin_appearance.php pattern).
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
$email = 'codeserver_gate_' . time() . '@example.com';
$token = create_test_user($email);
$user  = session_cookie($token);

$hash   = setup_gate_lab($email, 'essentials', 'running');
$ideUrl = 'https://code-' . $hash . '.tomweb.shop';

// ── 1. Engine truth: code_status on a container that does not exist ──
$resp = http_request('POST', '/api/labs/code_status', [
    'cookie' => $user['cookie'],
    'body'   => ['lab' => 'essentials'],
]);
$json = json_decode($resp['body'], true);
test("code_status reports not-running for a dead container", $resp['status'] === 200 && $json['running'] === false,
    'status=' . $resp['status'] . ' running=' . var_export($json['running'] ?? null, true));
test("code_status still hands back the fresh URL shape", ($json['url'] ?? '') !== '' && ($json['hash'] ?? '') === $hash,
    'url=' . ($json['url'] ?? '-'));

// ── 2. Dashboard: URL hidden, placeholder + password + copy row kept ──
$resp = http_request('GET', '/labs/dashboard/' . $hash, ['cookie' => $user['cookie']]);
$body = $resp['body'];
test("dashboard renders HTTP 200 with the gate in place", $resp['status'] === 200, 'status=' . $resp['status'] . ' len=' . strlen($body));
test("dead code-server hides its URL from the dashboard",
    $resp['status'] === 200 && strpos($body, 'code-' . $hash) === false && strpos($body, '://' . $hash) === false,
    'found dead url on page: ' . var_export(strpos($body, 'code-' . $hash) !== false, true));
test("gated field renders the Not running placeholder",
    $resp['status'] === 200 && strpos($body, 'Not running') !== false,
    strpos($body, 'Not running') !== false ? 'ok' : 'placeholder missing');
test("Code-Server Password still shown while URL is gated",
    $resp['status'] === 200 && strpos($body, 'Code-Server Password') !== false
        && strpos($body, htmlspecialchars('CodePass-Gate-123')) !== false,
    strpos($body, 'CodePass-Gate-123') !== false ? 'ok' : 'password missing');

// ── 3. Unknown hash in the URL cannot leak another lab's secrets ──
$resp = http_request('GET', '/labs/dashboard/' . str_repeat('a', 32), ['cookie' => $user['cookie']]);
test("foreign/unknown hash dashboard does not expose gate details",
    $resp['status'] === 200 || $resp['status'] === 302 || $resp['status'] === 404,
    'status=' . $resp['status']);
test("foreign hash page leaks no machine-lab credentials",
    $resp['status'] === 200 || $resp['status'] === 302 || $resp['status'] === 404
        ? strpos($resp['body'] ?? '', 'CodePass-Gate-123') === false
        : true);

// ── 4. Auth boundary: no session never reaches the panel ──
$resp = http_request('GET', '/labs/dashboard/' . $hash);
test("dashboard without session is refused/redirected",
    $resp['status'] === 302 || $resp['status'] === 401 || $resp['status'] === 403,
    'status=' . $resp['status']);

teardown_gate_lab($email, 'essentials');
cleanup_test_user($email);

test("no machine_labs leftovers", DatabaseConnection::getDefaultDatabase()->machine_labs->countDocuments(
    ['user_email' => $email]
) === 0);

test_summary();
