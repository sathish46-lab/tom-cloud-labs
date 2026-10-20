<?php
/**
 * Test: labs deploy API generates, returns, and persists a per-run `run_id`.
 *
 * Real runtime only (per testing rules):
 *   - real HTTP POST to /api/labs/deploy (session auth, no CSRF on this endpoint)
 *   - real Mongo write to machine_labs.deploy_run_id
 *   - real worker processing that persists run_id into deploy_history / deploy_log
 *   - auth boundary: unauthenticated deploy must not succeed
 *
 * The run_id is written by the worker on BOTH the success and failure paths,
 * so the persistence assertion proves the job was processed end-to-end even if
 * the container itself fails to start.
 */
require_once __DIR__ . '/bootstrap.php';

/** Build a session cookie (session_token + PHPSESSID) for a test user. */
function runid_session_cookie(string $sessionToken): string {
    $resp = http_request('GET', '/dashboard', ['cookie' => "session_token=$sessionToken"]);
    $sid = '';
    if (isset($resp['headers']['Set-Cookie'])
        && preg_match('/PHPSESSID=([^;]+)/', $resp['headers']['Set-Cookie'], $m)) {
        $sid = $m[1];
    }
    return 'session_token=' . $sessionToken . ($sid !== '' ? '; PHPSESSID=' . $sid : '');
}

$testEmail = 'runid_' . time() . '@example.com';
$labType   = 'essentials';

// ── Auth boundary ────────────────────────────────────────────────────────────
$anon = http_request('POST', '/api/labs/deploy', ['body' => ['lab' => $labType]]);
$anonStatus = $anon['body_json']['status'] ?? null;
test('Unauthenticated deploy does not succeed',
    $anon['status'] === 401 || $anonStatus !== 'success',
    'http=' . $anon['status'] . ' status=' . var_export($anonStatus, true));

// ── Happy path: authenticated deploy returns run_id ──────────────────────────
$token  = create_test_user($testEmail, 'user');
$cookie = runid_session_cookie($token);

$resp = http_request('POST', '/api/labs/deploy', [
    'cookie' => $cookie,
    'body'   => ['lab' => $labType],
]);
$json   = $resp['body_json'] ?? [];
$hash   = $json['hash']   ?? '';
$runId  = $json['run_id'] ?? '';

test('Deploy API returns HTTP 200', $resp['status'] === 200, 'status=' . $resp['status']);
test('Deploy API returns status=success', ($json['status'] ?? '') === 'success', json_encode($json));
test('Deploy API returns the lab hash', $hash !== '', 'hash=' . $hash);
test('Deploy API returns a run_id', $runId !== '', 'run_id=' . var_export($runId, true));
test('run_id is a valid UUID or 32-hex id',
    (bool)preg_match('/^([0-9a-f]{32}|[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})$/i', $runId),
    'run_id=' . $runId);

// ── Real DB: machine_labs.deploy_run_id matches the returned run_id ──────────
$labsDb = DatabaseConnection::getClient()->selectDatabase('tom_labs_db');
$doc = $hash !== '' ? $labsDb->machine_labs->findOne(['instance_hash' => $hash]) : null;
test('machine_labs record was created', $doc !== null, 'hash=' . $hash);
test('machine_labs.deploy_run_id matches returned run_id',
    $doc !== null && ($doc['deploy_run_id'] ?? '') === $runId,
    'stored=' . var_export($doc['deploy_run_id'] ?? null, true));

// ── Worker persistence: poll until the worker writes run_id ──────────────────
$persisted  = false;
$histRun    = null;
$logRun     = null;
$deadline   = time() + 90;
while (time() < $deadline) {
    $doc     = $hash !== '' ? $labsDb->machine_labs->findOne(['instance_hash' => $hash]) : null;
    $histRun = $doc['deploy_history'][0]['run_id'] ?? null;
    $logRun  = $doc['deploy_log']['run_id'] ?? null;
    if ($histRun === $runId || $logRun === $runId) {
        $persisted = true;
        break;
    }
    sleep(1);
}
test('Worker persisted run_id into deploy_history / deploy_log', $persisted,
    'history_run=' . var_export($histRun, true) . ' log_run=' . var_export($logRun, true));

// ── Cleanup: remove lab, release IP, delete records, remove test user ────────
if ($hash !== '') {
    // Best-effort container/lab teardown (no-op if the deploy never created one)
    @exec('labsctl lab remove --hash=' . escapeshellarg($hash) . ' 2>&1', $rmOut, $rmRc);
    // Release the reserved tunnel IP back to the pool
    $ip = $doc['internal_ip'] ?? null;
    if ($ip) {
        try {
            (new \TomLabs\Labs\IPManager())->release($ip, $testEmail);
        } catch (Throwable $e) {
            // release is best-effort; pool leak must not fail the test
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
