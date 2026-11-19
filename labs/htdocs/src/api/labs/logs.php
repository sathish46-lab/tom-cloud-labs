<?php
/**
 * GET /api/labs/logs?hash=INSTANCE_HASH&after=SEQ[&run_id=RUN]
 *
 * Gap-fill endpoint for the live log console (STOMP has no replay):
 * when the browser (re)subscribes to logs.<hash>, it calls this once to
 * recover lines emitted while its WebSocket was disconnected. Not a
 * polling endpoint — one fetch per (re)connect only.
 *
 * Source of truth mid-run: per-run file /var/log/labsctl/runs/<run>.log
 * written line-by-line by labs_worker.py (seq == line index). Mongo is
 * still written once at the end of the run (unchanged).
 */
require_once __DIR__ . '/../../../src/load.php';

$user = AuthMiddleware::requireAuth();

$hash     = trim($_GET['hash'] ?? '');
$runParam = trim($_GET['run_id'] ?? '');
$after    = $_GET['after'] ?? '-1';

if ($hash === '' || !preg_match('/^[a-f0-9]{32}$/i', $hash)) {
    api_json(['success' => false, 'error' => 'Invalid hash'], 400);
}
if (!is_numeric($after)) {
    api_json(['success' => false, 'error' => 'Invalid after'], 400);
}
if ($runParam !== '' && !preg_match('/^[A-Za-z0-9._-]{1,80}$/', $runParam)) {
    api_json(['success' => false, 'error' => 'Invalid run_id'], 400);
}
$after = (int)$after;

try {
    $db   = DatabaseConnection::getClient()->selectDatabase('tom_labs_db');
    $inst = $db->machine_labs->findOne(
        ['instance_hash' => $hash],
        ['projection' => ['user_id' => 1, 'deploy_run_id' => 1, 'status' => 1]]
    );

    if (!$inst) {
        api_json(['success' => false, 'error' => 'Lab not found'], 404);
    }

    // Ownership (string-compare across the storage kinds in the wild:
    // numeric user_id on legacy users/documents, ObjectId on newer ones —
    // a strict PHP comparison 403s otherwise, breaking log gap-fill).
    $ownerId = (string)($inst['user_id'] ?? '');
    if ($ownerId === '' || $ownerId !== (string)$user->getUserId()) {
        api_json(['success' => false, 'error' => 'Access denied'], 403);
    }

    $runKey = $runParam !== '' ? $runParam : (string)($inst['deploy_run_id'] ?? '');
    if ($runKey === '') {
        $runKey = $hash;
    }

    $file    = '/var/log/labsctl/runs/' . $runKey . '.log';
    $missing = !is_file($file);
    $lines   = [];
    $lastSeq = -1;

    if (!$missing) {
        $raw = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($raw === false) {
            $raw = [];
            $missing = true;
        }
        $total = count($raw);
        if ($total > 2000) {
            $after = max($after, $total - 2000);
        }
        foreach ($raw as $i => $line) {
            $lastSeq = $i;
            if ($i > $after) {
                $lines[] = ['seq' => $i, 'log' => $line];
            }
        }
    }

    // Replay only while the run is active, or if the file was just written
    // (covers a reconnect landing right after the run finished).
    $fresh  = !$missing && (time() - filemtime($file)) < 180;
    $active = (($inst['status'] ?? '') === 'deploying') || $fresh;

    api_json([
        'success'  => true,
        'run_id'   => $runKey,
        'active'   => $active,
        'missing'  => $missing,
        'after'    => $after,
        'last_seq' => $lastSeq,
        'lines'    => $active ? $lines : [],
    ], 200);
} catch (Exception $e) {
    errors_report(['context' => 'labs/logs', 'message' => 'Logs API Error: ' . $e->getMessage()]);
    api_json(['success' => false, 'error' => 'Internal server error'], 500);
}
