<?php
/**
 * Test: /api/labs/ensure_codeserver — queue-backed on-demand code-server start.
 *
 * Real runtime only (per testing rules):
 *   - real HTTP through the API (session auth, no CSRF on this endpoint)
 *   - real Mongo reads/writes (ownership, status guard, deploy_run_id hand-off)
 *   - real queue job processed by labs_worker → labsctl lab ensure-codeserver →
 *     lab code-status flips to running (happy path — needs a real deploy)
 *
 * Categories covered: auth boundary, ownership (multi-tenant), bad input,
 * failure path (stopped / deploying lab), happy path.
 */
require_once __DIR__ . '/bootstrap.php';

/** Build a session cookie (session_token + PHPSESSID) for a test user. */
function ensure_session_cookie(string $sessionToken): string {
    $resp = http_request('GET', '/dashboard', ['cookie' => "session_token=$sessionToken"]);
    $sid = '';
    if (isset($resp['headers']['Set-Cookie'])
        && preg_match('/PHPSESSID=([^;]+)/', $resp['headers']['Set-Cookie'], $m)) {
        $sid = $m[1];
    }
    return 'session_token=' . $sessionToken . ($sid !== '' ? '; PHPSESSID=' . $sid : '');
}

/** POST /api/labs/ensure_codeserver and return [http_status, json]. */
function ensure_post(string $cookie, array $body): array {
    $resp = http_request('POST', '/api/labs/ensure_codeserver', [
        'cookie' => $cookie,
        'body'   => $body,
    ]);
    return [$resp['status'], $resp['body_json'] ?? []];
}

$testEmail = 'ensure_' . time() . '@example.com';
$labType   = 'essentials';

$labsDb = DatabaseConnection::getClient()->selectDatabase('tom_labs_db');

// ── Auth boundary ────────────────────────────────────────────────────────────
$anon = http_request('POST', '/api/labs/ensure_codeserver', ['body' => ['lab' => $labType]]);
$anonStatus = $anon['body_json']['status'] ?? null;
test('Unauthenticated ensure does not succeed',
    $anon['status'] === 401 || $anonStatus !== 'success',
    'http=' . $anon['status'] . ' status=' . var_export($anonStatus, true));

// ── Setup test user ──────────────────────────────────────────────────────────
$token  = create_test_user($testEmail, 'user');
$cookie = ensure_session_cookie($token);
$userDoc  = DatabaseConnection::getDefaultDatabase()->users->findOne(['email' => $testEmail]);
$userId   = (string)($userDoc['_id'] ?? '');

// ── Ownership: another user's lab hash must be refused ──────────────────────
$foreignHash = str_repeat('ab', 16); // 32-hex, owned by nobody
$labsDb->machine_labs->insertOne([
    'instance_hash' => $foreignHash,
    'user_id'       => str_repeat('9', 24), // not the test user
    'status'        => 'running',
    'lab_type'      => $labType,
    'created_at'    => time(),
]);
[$oHttp, $oJson] = ensure_post($cookie, ['lab' => $labType, 'hash' => $foreignHash]);
test('Foreign lab hash is refused with 403',
    $oHttp === 403 && ($oJson['status'] ?? '') === 'error',
    'http=' . $oHttp . ' body=' . json_encode($oJson));
$labsDb->machine_labs->deleteOne(['instance_hash' => $foreignHash]);

// ── Bad input: unknown / malformed hash never succeeds ──────────────────────
[$b1Http, $b1Json] = ensure_post($cookie, ['lab' => $labType, 'hash' => str_repeat('cd', 16)]);
test('Unknown lab hash returns HTTP 500 + status=error',
    $b1Http === 500 && ($b1Json['status'] ?? '') === 'error',
    'http=' . $b1Http . ' body=' . json_encode($b1Json));
test('Unknown lab hash does not return success',
    ($b1Json['status'] ?? '') !== 'success',
    'body=' . json_encode($b1Json));

[$b2Http, $b2Json] = ensure_post($cookie, ['lab' => $labType, 'hash' => 'not-a-valid-hash!!']);
test('Malformed hash never succeeds (falls back and still fails cleanly)',
    ($b2Json['status'] ?? '') !== 'success',
    'http=' . $b2Http . ' body=' . json_encode($b2Json));

// ── Failure path: lab exists but is not running / still deploying ───────────
$ownedHash = str_repeat('ef', 16);
function ensure_insert_lab($db, string $hash, string $userId, string $status): void {
    $db->machine_labs->insertOne([
        'instance_hash' => $hash,
        'user_id'       => $userId,
        'status'        => $status,
        'lab_type'      => 'essentials',
        'created_at'    => time(),
    ]);
}
ensure_insert_lab($labsDb, $ownedHash, $userId, 'stopped');
[$sHttp, $sJson] = ensure_post($cookie, ['lab' => $labType, 'hash' => $ownedHash]);
test('Stopped lab is rejected with a clear error',
    $sHttp === 200
        && ($sJson['status'] ?? '') === 'error'
        && stripos($sJson['error'] ?? '', 'not running') !== false,
    'http=' . $sHttp . ' body=' . json_encode($sJson));

$labsDb->machine_labs->updateOne(['instance_hash' => $ownedHash], ['$set' => ['status' => 'deploying']]);
[$dHttp, $dJson] = ensure_post($cookie, ['lab' => $labType, 'hash' => $ownedHash]);
test('Deploying lab is rejected with a clear error',
    $dHttp === 200
        && ($dJson['status'] ?? '') === 'error'
        && stripos($dJson['error'] ?? '', 'being deployed') !== false,
    'http=' . $dHttp . ' body=' . json_encode($dJson));

// Ownership of the synthetic doc was fully exercised — remove before the real deploy.
$labsDb->machine_labs->deleteOne(['instance_hash' => $ownedHash]);

// ── Happy path: deploy for real, then ensure, then code-status must flip ────
$resp = http_request('POST', '/api/labs/deploy', [
    'cookie' => $cookie,
    'body'   => ['lab' => $labType],
]);
$json      = $resp['body_json'] ?? [];
$hash      = $json['hash'] ?? '';
$deployRun = $json['run_id'] ?? '';
test('Setup: deploy API accepts the test user',
    $resp['status'] === 200 && ($json['status'] ?? '') === 'success' && $hash !== '',
    'http=' . $resp['status'] . ' body=' . json_encode($json));

$deadline  = time() + 150;
$deployed  = false;
$labDoc    = null;
while (time() < $deadline) {
    $labDoc = $hash !== '' ? $labsDb->machine_labs->findOne(['instance_hash' => $hash]) : null;
    if ($labDoc && ($labDoc['status'] ?? '') === 'running') {
        $deployed = true;
        break;
    }
    sleep(2);
}
test('Setup: lab reached status=running', $deployed,
    'status=' . var_export($labDoc['status'] ?? null, true));

if ($deployed) {
    // Queue the launch — no hash param: exercises the server-side getLabHash fallback.
    [$eHttp, $eJson] = ensure_post($cookie, ['lab' => $labType]);
    $ensureRun = $eJson['run_id'] ?? '';
    test('Ensure returns HTTP 200 + status=success', $eHttp === 200 && ($eJson['status'] ?? '') === 'success',
        'http=' . $eHttp . ' body=' . json_encode($eJson));
    test('Ensure returns a run_id distinct from the deploy run',
        $ensureRun !== '' && $ensureRun !== $deployRun,
        'ensure=' . var_export($ensureRun, true) . ' deploy=' . var_export($deployRun, true));

    $doc = $labsDb->machine_labs->findOne(['instance_hash' => $hash], ['projection' => ['deploy_run_id' => 1]]);
    test('machine_labs.deploy_run_id was set to the ensure run (gap-fill source)',
        $ensureRun !== '' && ($doc['deploy_run_id'] ?? '') === $ensureRun,
        'stored=' . var_export($doc['deploy_run_id'] ?? null, true));

    // The worker must create the per-run log file (live + gap-fill source).
    $runFile = '/var/log/labsctl/runs/' . $ensureRun . '.log';
    $fileSeen = false;
    $deadline = time() + 45;
    while (time() < $deadline) {
        if ($ensureRun !== '' && is_file($runFile) && filesize($runFile) > 0) {
            $fileSeen = true;
            break;
        }
        sleep(1);
    }
    test('Worker wrote the per-run log file for the ensure job', $fileSeen, 'file=' . $runFile);

    // Ground truth: labsctl code-status must report code-server running.
    $running = false;
    $deadline = time() + 60;
    while (time() < $deadline) {
        $st = http_request('POST', '/api/labs/code_status', [
            'cookie' => $cookie,
            'body'   => ['lab' => $labType],
        ]);
        $stJson = $st['body_json'] ?? [];
        if (!empty($stJson['running']) || !empty($stJson['codeserver_running'])) {
            $running = true;
            break;
        }
        sleep(2);
    }
    test('code_status reports code-server running after ensure', $running,
        'last=' . json_encode($stJson ?? []));

    // Ensure must not flip the lab's status (the container is fine either way).
    $doc = $labsDb->machine_labs->findOne(['instance_hash' => $hash], ['projection' => ['status' => 1]]);
    test('Lab status still running after ensure (not clobbered to error)',
        ($doc['status'] ?? '') === 'running', 'status=' . var_export($doc['status'] ?? null, true));

    // Worker persisted the ensure run into deploy_history under ensure_log.
    $histOk = false;
    $deadline = time() + 20;
    while (time() < $deadline) {
        $doc = $labsDb->machine_labs->findOne(['instance_hash' => $hash], ['projection' => ['deploy_history' => 1]]);
        $top = $doc['deploy_history'][0] ?? null;
        if ($top && ($top['action'] ?? '') === 'ensure-codeserver'
            && $ensureRun !== '' && ($top['run_id'] ?? '') === $ensureRun) {
            $histOk = true;
            break;
        }
        sleep(1);
    }
    test('deploy_history recorded the ensure-codeserver run', $histOk,
        'top=' . json_encode($doc['deploy_history'][0] ?? null));
}

// ── Cleanup: remove lab, release IP, delete records, delete test user ───────
if ($hash !== '') {
    $labDoc = $labsDb->machine_labs->findOne(['instance_hash' => $hash]);
    @exec('labsctl lab remove --hash=' . escapeshellarg($hash) . ' 2>&1', $rmOut, $rmRc);
    $ip = $labDoc['internal_ip'] ?? null;
    if ($ip) {
        try {
            (new \TomLabs\Labs\IPManager())->release($ip, $testEmail);
        } catch (Throwable $e) {
            // best-effort; pool leak must not fail the test
        }
    }
    $labsDb->machine_labs->deleteOne(['instance_hash' => $hash]);
    try {
        DatabaseConnection::getClient()->selectDatabase('tom_labs_instances_db')
            ->instances->deleteOne(['instance_hash' => $hash]);
    } catch (Throwable $e) {
        // instances doc may not exist
    }
}
cleanup_test_user($testEmail);

test_summary();
