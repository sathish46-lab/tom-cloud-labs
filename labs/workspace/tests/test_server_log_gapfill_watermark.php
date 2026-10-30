<?php
/**
 * test_server_log_gapfill_watermark.php
 *
 * The server-log panel replays log lines after (re)connects through the
 * gap-fill endpoint /api/labs/logs. Requirement after the "reload — Your lab
 * is up and ready..." log kept coming back on every page reload:
 *
 *   - gap-fill is positioning data: the client asks "after=<watermark>" for
 *     exactly the lines it missed while its socket was down;
 *   - after=<last_seq> must return ZERO lines (nothing missed);
 *   - after=-1 on an ACTIVE run still returns the run context (mid-run
 *     reconnect or first view during deploy);
 *   - a finished, stale run (not deploying, file old) returns nothing.
 */

require_once __DIR__ . '/bootstrap.php';

define('LAB_HASH_SALT', '8b51626f3a468904e8b6f83747f2fcf1');

function lab_hash_of(string $email, string $labName): string {
    return md5($email . $labName . LAB_HASH_SALT);
}

/** Write a fake per-run log file exactly like labs_worker.py would. */
function write_run_file(string $runKey, array $lines): bool {
    $dir = '/var/log/labsctl/runs';
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
        return false;
    }
    return file_put_contents($dir . '/' . $runKey . '.log', implode("\n", $lines) . "\n") !== false;
}

function make_run_file_stale(string $runKey): void {
    @touch('/var/log/labsctl/runs/' . $runKey . '.log', time() - 3600);
}

function setup_watermark_lab(string $email, string $labName, string $status): string {
    $db = DatabaseConnection::getDefaultDatabase();
    $hash = lab_hash_of($email, $labName);

    // Real user documents carry a numeric user_id (logs.php ownership uses
    // it via getUserId()); test users get one so the harness matches prod.
    $userId = 980000 + ($db->machine_labs->countDocuments() % 1000);

    $db->users->updateOne(['email' => $email], ['$set' => ['user_id' => $userId]]);
    $db->machine_labs->deleteOne(['instance_hash' => $hash]);
    $db->machine_labs->insertOne([
        'instance_hash' => $hash,
        'name' => 'gapfill-watermark-' . $labName,
        'lab_type' => $labName,
        'template_name' => $labName,
        'user_email' => $email,
        'user_id' => $userId,
        'status' => $status,
        'deploy_run_id' => 'run-' . $hash,
        'internal_ip' => '10.30.0.1',
        'created_at' => time(),
        'install_type' => 'machine',
    ]);
    return $hash;
}

function teardown_watermark_lab(string $email, string $labName, string $runKey): void {
    DatabaseConnection::getDefaultDatabase()->machine_labs->deleteOne(
        ['instance_hash' => lab_hash_of($email, $labName)]
    );
    @unlink('/var/log/labsctl/runs/' . $runKey . '.log');
}

function gapfill(string $cookie, string $hash, int $after = -1, string $runKey = 'gapfill-test-run'): array {
    $resp = http_request(
        'GET',
        "/api/labs/logs?hash={$hash}&after={$after}&run_id={$runKey}&ts=" . time(),
        ['cookie' => $cookie]
    );
    return ['status' => $resp['status'], 'json' => json_decode($resp['body'], true) ?: []];
}

/**
 * The session cookie has to travel with every request (copied from the
 * test_admin_appearance.php pattern — bootstrap.php does not define it).
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
$email = 'gapfill_wm_' . time() . '@example.com';
$token = create_test_user($email);
$user  = session_cookie($token);

$hash   = setup_watermark_lab($email, 'essentials', 'deploying');
$runKey = 'gapfill-test-run';
$lines  = [
    '[*] Bootstrapping lab image...',
    '[*] Your lab is up and ready to experiment... Page will reload in few seconds.',
    '[✓] Deployment complete',
    '[✓] Lab is ready',
    '[*] Metrics streaming connected',
];
test("test preconditions: fake run file written", write_run_file($runKey, $lines));

// ── 1. Auth boundary ──
$resp = http_request('GET', "/api/labs/logs?hash={$hash}&after=-1");
test("logs API without session is refused", $resp['status'] === 401, 'status=' . $resp['status']);

// ── 2. Bad input ──
$resp = gapfill($user['cookie'], 'ZZZZ', -1);
test("invalid hash is 400", $resp['status'] === 400, 'status=' . $resp['status']);
$resp = http_request('GET', "/api/labs/logs?hash={$hash}&after=abc", ['cookie' => $user['cookie']]);
test("non-numeric after is 400", $resp['status'] === 400, 'status=' . $resp['status']);

// ── 3. Mid-run context on first view (active) — after=-1 replays the file ──
$r = gapfill($user['cookie'], $hash, -1);
test("active run gap-fill returns the run context", $r['status'] === 200 && ($r['json']['active'] ?? false)
    && count($r['json']['lines'] ?? []) === 5,
    'active=' . var_export($r['json']['active'] ?? null, true) . ' lines=' . count($r['json']['lines'] ?? []));
test("gap-fill lines carry per-line seq", isset($r['json']['lines'][0]['seq']) && $r['json']['lines'][4]['seq'] === 4,
    'first_seq=' . var_export($r['json']['lines'][0]['seq'] ?? null, true));
test("gap-fill reports the run id", ($r['json']['run_id'] ?? '') === $runKey, 'run_id=' . ($r['json']['run_id'] ?? '-'));
test("gap-fill reports last_seq", ($r['json']['last_seq'] ?? -2) === 4, 'last_seq=' . var_export($r['json']['last_seq'] ?? null, true));

// ── 4. THE REPORTED BUG: watching to the end then reconnecting/reloading must
// return NOTHING — all lines were already seen ──
$r = gapfill($user['cookie'], $hash, 4);
test("after the last seen seq nothing is re-sent",
    $r['status'] === 200 && ($r['json']['lines'] ?? ['x']) === []
    && ($r['json']['last_seq'] ?? -1) === 4,
    'lines=' . count($r['json']['lines'] ?? []) . ' last_seq=' . var_export($r['json']['last_seq'] ?? null, true));

// ── 5. Missed chunk in the middle: only the missed lines come back ──
$r = gapfill($user['cookie'], $hash, 2);
$missed = array_map(fn($l) => $l['seq'], $r['json']['lines'] ?? []);
test("only the missed lines (seq>2) are returned on mid-run reconnect",
    $r['status'] === 200 && $missed === [3, 4],
    'seqs=' . json_encode($missed));

// ── 6. Finished + stale run: not deploying and file old → nothing at all ──
$staleResp = DatabaseConnection::getDefaultDatabase()->machine_labs->updateOne(
    ['instance_hash' => $hash],
    ['$set' => ['status' => 'running']]
);
make_run_file_stale($runKey);
$r = gapfill($user['cookie'], $hash, -1);
test("finished stale run is never replayed",
    $r['status'] === 200 && ($r['json']['active'] ?? true) === false && ($r['json']['lines'] ?? null) === [],
    'active=' . var_export($r['json']['active'] ?? null, true) . ' lines=' . count($r['json']['lines'] ?? []));

// ── 7. Finished but file JUST written → brief replay window still ok ──
@touch('/var/log/labsctl/runs/' . $runKey . '.log', time());
DatabaseConnection::getDefaultDatabase()->machine_labs->updateOne(
    ['instance_hash' => $hash],
    ['$set' => ['status' => 'running']]
);
$r = gapfill($user['cookie'], $hash, -1);
test("freshly finished run still replays within the freshness window",
    $r['status'] === 200 && ($r['json']['active'] ?? false) === true && count($r['json']['lines'] ?? []) > 0,
    'active=' . var_export($r['json']['active'] ?? null, true));

// ── 8. Ownership: another user's hash is not readable ──
$resp = http_request(
    'GET',
    '/api/labs/logs?hash=' . str_repeat('b', 32) . '&after=-1',
    ['cookie' => $user['cookie']]
);
test("unknown lab hash is 404 with no internals leaked", $resp['status'] === 404, 'status=' . $resp['status']);
test("error body carries no run-file content", strpos($resp['body'], '[✓] Deployment complete') === false);

// ── 9. Run id injection attempts are rejected ──
$resp = http_request(
    'GET',
    '/api/labs/logs?hash=' . $hash . '&after=0&run_id=../etc/passwd',
    ['cookie' => $user['cookie']]
);
test("path traversal in run_id is 400", $resp['status'] === 400, 'status=' . $resp['status']);

// ── 10. Mixed user_id storage: newer users own labs recorded as ObjectId.
// Strict PHP `!==` five-endpoint compare used to 403 their gap-fill; the
// ownership gate now string-compares both representations. ──
$email2 = 'gapfill_oid_' . time() . '@example.com';
$token2 = create_test_user($email2);
$user2  = session_cookie($token2, '/dashboard');
$db     = DatabaseConnection::getDefaultDatabase();
$uid2   = $db->users->findOne(['email' => $email2])['_id']; // no numeric user_id on this doc
test("second test user emulates the modern signup shape", $uid2 instanceof \MongoDB\BSON\ObjectId
    && $db->users->findOne(['email' => $email2])['user_id'] === null);

$hash2 = lab_hash_of($email2, 'essentials');
$db->machine_labs->deleteOne(['instance_hash' => $hash2]);
$db->machine_labs->insertOne([
    'instance_hash' => $hash2,
    'lab_type' => 'essentials', 'template_name' => 'essentials',
    'user_email' => $email2, 'user_id' => $uid2, 'status' => 'deploying',
    'deploy_run_id' => 'run-' . $hash2, 'internal_ip' => '10.30.0.2',
    'install_type' => 'machine', 'created_at' => time(),
]);
write_run_file('run-' . $hash2, ['[*] oid owner line one', '[*] digit two']);

$r = gapfill($user2['cookie'], $hash2, -1, 'run-' . $hash2);
test("ObjectId-owned lab gap-fills for its ObjectId-session token owner",
    $r['status'] === 200 && ($r['json']['success'] ?? false) === true && count($r['json']['lines'] ?? []) === 2,
    'status=' . $r['status'] . ' lines=' . count($r['json']['lines'] ?? []));

// 10b. A DIFFERENT user (moreover of the numeric era) cannot read that lab:
$resp = http_request('GET', "/api/labs/logs?hash={$hash2}&after=-1", ['cookie' => $user['cookie']]);
test("other user's lab stays 403 under the string compare", $resp['status'] === 403, 'status=' . $resp['status']);

// ── 11. Same string-compare gate applies to the whole lab api family ──
$resp = http_request('GET', "/api/labs/code_info?hash={$hash2}", ['cookie' => $user2['cookie']]);
test("code_info admits the ObjectId owner (was 500 due to old check)",
    $resp['status'] === 200, 'status=' . $resp['status']);
$resp = http_request('GET', "/api/labs/code_info?hash={$hash2}", ['cookie' => $user['cookie']]);
test("code_info denies a third party", $resp['status'] === 500, 'status=' . $resp['status']);

teardown_watermark_lab($email, 'essentials', $runKey);
teardown_watermark_lab($email2, 'essentials', 'run-' . $hash2);
DatabaseConnection::getDefaultDatabase()->machine_labs->deleteOne(['instance_hash' => $hash2]);
cleanup_test_user($email);
cleanup_test_user($email2);

test("no machine_labs leftovers", DatabaseConnection::getDefaultDatabase()->machine_labs->countDocuments(
    ['user_email' => $email]
) === 0);
test("no run-file leftovers", !is_file('/var/log/labsctl/runs/' . $runKey . '.log'));

test_summary();
