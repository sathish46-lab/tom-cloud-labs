<?php
/**
 * Test: /api/labs/logs — WS-reconnect log gap-fill endpoint.
 *
 * Real runtime only: real HTTP GET + real session cookies (auth boundary),
 * real Mongo machine_labs doc (ownership/status), real per-run file
 * /var/log/labsctl/runs/<run>.log written exactly like labs_worker.py
 * emit() writes it (one line per seq, seq == line index).
 *
 * Categories: happy (full/after-filter/boundary), auth, ownership
 * (multi-tenant), bad input, failure (missing file/unknown lab/inactive).
 */
require_once __DIR__ . '/bootstrap.php';

function logs_session_cookie(string $sessionToken): string {
    $resp = http_request('GET', '/dashboard', ['cookie' => "session_token=$sessionToken"]);
    $sid = '';
    if (isset($resp['headers']['Set-Cookie'])
        && preg_match('/PHPSESSID=([^;]+)/', $resp['headers']['Set-Cookie'], $m)) {
        $sid = $m[1];
    }
    return 'session_token=' . $sessionToken . ($sid !== '' ? '; PHPSESSID=' . $sid : '');
}

$tag     = (string)time();
$runsDir = '/var/log/labsctl/runs';
$hashA   = md5('logs-test-a-' . $tag);
$runA    = 'testrun-' . $tag;
$fileA   = "$runsDir/$runA.log";
$emailA  = 'logs_a_' . $tag . '@example.com';
$hashB   = md5('logs-test-b-' . $tag);
$runB    = 'testrun-nofile-' . $tag;
$emailB  = 'logs_b_' . $tag . '@example.com';
$hashC   = md5('logs-test-c-' . $tag);
$runC    = 'testrun-inactive-' . $tag;
$fileC   = "$runsDir/$runC.log";

// ── Auth boundary ────────────────────────────────────────────────────────────
$anon = http_request('GET', "/api/labs/logs?hash=$hashA&after=-1");
test('Unauthenticated logs fetch is rejected (401)', $anon['status'] === 401, 'http=' . $anon['status']);

// ── Setup: two real users (multi-tenant) ─────────────────────────────────────
$tokenA  = create_test_user($emailA, 'user');
$cookieA = logs_session_cookie($tokenA);
$tokenB  = create_test_user($emailB, 'user');
$cookieB = logs_session_cookie($tokenB);

$usersDb  = DatabaseConnection::getDefaultDatabase();
$labsDb   = DatabaseConnection::getClient()->selectDatabase('tom_labs_db');
$uidA     = (string)($usersDb->users->findOne(['email' => $emailA])['_id'] ?? '');
$uidB     = (string)($usersDb->users->findOne(['email' => $emailB])['_id'] ?? '');
test('Test users created with usable distinct ids', $uidA !== '' && $uidB !== '' && $uidA !== $uidB,
    "uidA=$uidA uidB=$uidB");

// ── Setup: active machine_labs doc (owned by A) + per-run file ───────────────
$lines = [
    "[Queue] Job accepted. Worker [test] assigned.",
    "[*] Starting deployment for test lab",
    "[✓] Docker network ready",
    "[✓] Container started",
    "[*] reload",
];
if (!is_dir($runsDir)) { @mkdir($runsDir, 0755, true); }
file_put_contents($fileA, implode("\n", $lines) . "\n");

$labsDb->machine_labs->insertOne([
    'instance_hash' => $hashA,
    'user_id'       => $uidA,
    'email'         => $emailA,
    'lab_type'      => 'essentials',
    'status'        => 'deploying',
    'deploy_run_id' => $runA,
    'created_at'    => time(),
]);

// ── Happy: full replay (after=-1) ────────────────────────────────────────────
$r  = http_request('GET', "/api/labs/logs?hash=$hashA&after=-1", ['cookie' => $cookieA]);
$j  = $r['body_json'] ?? [];
test('Happy: HTTP 200', $r['status'] === 200, 'http=' . $r['status']);
test('Happy: success=true', ($j['success'] ?? false) === true, json_encode($j));
test('Happy: active=true while status=deploying', ($j['active'] ?? false) === true,
    'active=' . var_export($j['active'] ?? null, true));
test('Happy: missing=false', ($j['missing'] ?? null) === false,
    'missing=' . var_export($j['missing'] ?? null, true));
test('Happy: run_id resolves from machine_labs.deploy_run_id', ($j['run_id'] ?? '') === $runA,
    'run_id=' . ($j['run_id'] ?? ''));
test('Happy: all lines with seq == line index, content exact',
    count($j['lines'] ?? []) === 5
    && ($j['last_seq'] ?? -1) === 4
    && ($j['lines'][0]['seq'] ?? -1) === 0
    && ($j['lines'][0]['log'] ?? '') === $lines[0]
    && ($j['lines'][4]['seq'] ?? -1) === 4
    && ($j['lines'][4]['log'] ?? '') === $lines[4],
    json_encode($j['lines'] ?? []));

// ── Happy: after filter (the exact reconnect use-case) ───────────────────────
$r2 = http_request('GET', "/api/labs/logs?hash=$hashA&after=2", ['cookie' => $cookieA]);
$j2 = $r2['body_json'] ?? [];
test('after=2 returns only lines with seq > 2',
    count($j2['lines'] ?? []) === 2
    && ($j2['lines'][0]['seq'] ?? -1) === 3
    && ($j2['lines'][1]['seq'] ?? -1) === 4,
    json_encode($j2['lines'] ?? []));

// ── Boundary: after == last_seq → nothing new ────────────────────────────────
$r3 = http_request('GET', "/api/labs/logs?hash=$hashA&after=4", ['cookie' => $cookieA]);
test('after=last_seq returns empty gap', count(($r3['body_json']['lines']) ?? []) === 0,
    json_encode($r3['body_json'] ?? []));

// ── Ownership: user B must not read user A's run ─────────────────────────────
$r4 = http_request('GET', "/api/labs/logs?hash=$hashA&after=-1", ['cookie' => $cookieB]);
test('Ownership: other user gets 403', $r4['status'] === 403, 'http=' . $r4['status']);

// ── Bad input ────────────────────────────────────────────────────────────────
$r1 = http_request('GET', '/api/labs/logs?after=-1', ['cookie' => $cookieA]);
test('Bad input: missing hash → 400', $r1['status'] === 400, 'http=' . $r1['status']);
$r2b = http_request('GET', '/api/labs/logs?hash=not-a-hash&after=-1', ['cookie' => $cookieA]);
test('Bad input: malformed hash → 400', $r2b['status'] === 400, 'http=' . $r2b['status']);
$r3b = http_request('GET', "/api/labs/logs?hash=$hashA&after=abc", ['cookie' => $cookieA]);
test('Bad input: non-numeric after → 400', $r3b['status'] === 400, 'http=' . $r3b['status']);
$r4b = http_request('GET', "/api/labs/logs?hash=$hashA&run_id=../etc/passwd&after=-1", ['cookie' => $cookieA]);
test('Bad input: path-traversal run_id → 400', $r4b['status'] === 400, 'http=' . $r4b['status']);

// ── Failure: unknown lab → 404 ───────────────────────────────────────────────
$r5 = http_request('GET', '/api/labs/logs?hash=' . md5('no-such-' . $tag) . '&after=-1', ['cookie' => $cookieA]);
test('Unknown lab → 404', $r5['status'] === 404, 'http=' . $r5['status']);

// ── Failure: active run with missing file → 200, empty, no 5xx ───────────────
$labsDb->machine_labs->insertOne([
    'instance_hash' => $hashB,
    'user_id'       => $uidA,
    'email'         => $emailA,
    'lab_type'      => 'essentials',
    'status'        => 'deploying',
    'deploy_run_id' => $runB,
    'created_at'    => time(),
]);
$r6 = http_request('GET', "/api/labs/logs?hash=$hashB&after=-1", ['cookie' => $cookieA]);
$j6 = $r6['body_json'] ?? [];
test('Missing run file → 200 success + missing=true + empty lines',
    $r6['status'] === 200 && ($j6['success'] ?? false) === true
    && ($j6['missing'] ?? null) === true && (($j6['lines'] ?? null) === []),
    json_encode($j6));

// ── Inactive run: finished long ago → no replay even though file exists ──────
file_put_contents($fileC, implode("\n", $lines) . "\n");
touch($fileC, time() - 400);
clearstatcache(true, $fileC);
$labsDb->machine_labs->insertOne([
    'instance_hash' => $hashC,
    'user_id'       => $uidA,
    'email'         => $emailA,
    'lab_type'      => 'essentials',
    'status'        => 'running',
    'deploy_run_id' => $runC,
    'created_at'    => time() - 600,
]);
$r7 = http_request('GET', "/api/labs/logs?hash=$hashC&after=-1", ['cookie' => $cookieA]);
$j7 = $r7['body_json'] ?? [];
test('Inactive (old) run → active=false and no replay',
    $r7['status'] === 200 && ($j7['active'] ?? true) === false && (($j7['lines'] ?? null) === []),
    json_encode($j7));

// ── Cleanup ──────────────────────────────────────────────────────────────────
foreach ([$hashA, $hashB, $hashC] as $h) {
    $labsDb->machine_labs->deleteOne(['instance_hash' => $h]);
}
foreach ([$fileA, $fileC] as $f) {
    @unlink($f);
}
cleanup_test_user($emailA);
cleanup_test_user($emailB);

test_summary();
