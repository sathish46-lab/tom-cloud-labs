<?php
/**
 * Test: Error auditing + the admin Error Monitor.
 *
 * A learner must never see an upstream failure verbatim (no API keys, paths,
 * stack traces). Instead they get a plain-English sentence plus a reference
 * code, and the raw failure lands in /admin/errors as an open event that the
 * admin can acknowledge, resolve and re-open.
 *
 * Covers:
 *   1. Wiring (controller, template, API, .htaccess, sidebar, autoload)
 *   2. Classification — technical detection + plain-English mapping
 *   3. errors_report() — stable reference, dedupe, identity, raw detail
 *   4. errors_public() / errors_to_user() — what a learner may see
 *   5. Rendered Error Monitor — filters, queue, actions, no raw leak
 *   6. _error.php — stack trace only for admins, reference for everyone
 *   7. Quiz job status — the 400/API-key failure over HTTP
 *   8. HTTP access control on /admin/errors and /api/admin/update_error
 *
 * Usage:
 *   php workspace/tests/test_error_reporting.php
 */

require_once __DIR__ . '/bootstrap.php';

echo "=== Error Reporting Tests ===\n\n";

$controller  = PROJECT_ROOT . '/htdocs/app/admin/errors.php';
$template    = SRC_PATH . '/template/pages/admin/errors.php';
$updateApi   = SRC_PATH . '/api/admin/update_error.php';
$helperSrc   = SRC_PATH . '/utils/errors.php';
$loadSrc     = file_get_contents(SRC_PATH . '/load.php');
$htaccessSrc = file_get_contents(PROJECT_ROOT . '/htdocs/.htaccess');
$navSrc      = file_get_contents(SRC_PATH . '/template/_nav.php');
$errorTplSrc = file_get_contents(SRC_PATH . '/template/_error.php');
$jobStatusSrc= file_get_contents(SRC_PATH . '/api/quiz/job_status.php');
$roadmapsSrc = file_get_contents(SRC_PATH . '/api/roadmaps/job_status.php');
$askSrc      = file_get_contents(SRC_PATH . '/api/roadmaps/ask.php');

$refs = [];   // every reference we create, removed at the end

// ── Wiring ──
echo "--- Wiring ---\n";

test("controller app/admin/errors.php exists", file_exists($controller));
test("page template pages/admin/errors.php exists", file_exists($template));
test("queue API src/api/admin/update_error.php exists", file_exists($updateApi));
test("helper src/utils/errors.php exists", file_exists($helperSrc));
test(".htaccess routes /admin/errors", strpos($htaccessSrc, 'RewriteRule ^admin/errors/?$') !== false);
test("sidebar links to /admin/errors", strpos($navSrc, "'url' => '/admin/errors'") !== false);
test("sidebar uses an error icon", strpos($navSrc, "'url' => '/admin/errors', 'icon' => 'bx-error-circle'") !== false);
test("controller enforces admin", strpos(file_get_contents($controller), 'AuthMiddleware::isAdmin()') !== false);
test("update API enforces admin", strpos(file_get_contents($updateApi), 'AuthMiddleware::requireAdmin()') !== false);
test("update API enforces CSRF", strpos(file_get_contents($updateApi), 'AuthMiddleware::requireCsrf()') !== false);
test("load.php autoloads the error helper", strpos($loadSrc, "require_once __DIR__ . '/utils/errors.php'") !== false);
test("global handler audits exceptions", strpos($loadSrc, 'errors_report(') !== false);
test("global handler never echoes the exception", strpos($loadSrc, 'getMessage()') !== false
    && strpos($loadSrc, "echo \"Fatal Error:\"") === false);

// ── Classification ──
echo "\n--- Classification ---\n";

$rawGemini = 'Error: 400 API key not valid. Please pass a valid API key. '
           . '[reason: "API_KEY_INVALID" domain: "googleapis.com" metadata { key: "AIzaSyA-fake-key-0000000000000000000000" }]';
$rawPath   = 'include(/var/www/labs/htdocs/src/lib/labs/Quiz.class.php:291): failed to open stream';
$rawSql    = 'SQLSTATE[HY000] [2002] Connection refused in /opt/worker/main.php';
$plain     = 'Your session has expired. Please sign in again.';

test("gemini 400 payload counts as technical", errors_is_technical($rawGemini));
test("absolute path counts as technical", errors_is_technical($rawPath));
test("SQL/connection error counts as technical", errors_is_technical($rawSql));
test("friendly sentence does not count as technical", !errors_is_technical($plain));
test("plain error page text does not count as technical", !errors_is_technical('The page you are looking is broken.'));

test("API key failure maps to credentials copy", errors_sanitize($rawGemini) === 'The AI service rejected our credentials.');
test("connection failure maps to availability copy", errors_sanitize($rawSql) === 'A storage error occurred.'
    || errors_sanitize($rawSql) === 'The AI service is temporarily unavailable. Please try again in a moment.');
test("rate limit maps to busy copy", errors_sanitize('429 RESOURCE_EXHAUSTED: Quota exceeded') !== '');
test("unmapped text maps to empty string", errors_sanitize('everything is fine') === '');
test("no API key survives sanitising", strpos(errors_sanitize($rawGemini), 'AIza') === false);

// ── Report ──
echo "\n--- Report ---\n";

$db    = DatabaseConnection::getDefaultDatabase();
$email = 'err_user_' . time() . '@example.com';
create_test_user($email, 'user');
$uid = 980000 + random_int(1, 9999);
$db->users->updateOne(['email' => $email], ['$set' => ['user_id' => $uid]]);
$userDoc = $db->users->findOne(['email' => $email]);

$ref1 = errors_report([
    'context'    => 'quiz.generate',
    'message'    => $rawGemini,
    'user_email' => $email,
    'extra'      => ['job_id' => 'job-abc'],
]);
$refs[] = $ref1;

test("report returns a reference code", (bool)preg_match('/^ERR-[0-9A-F]{6}$/', $ref1), $ref1);
test("reference is stable for the same failure",
    errors_ref(['context' => 'quiz.generate', 'message' => $rawGemini, 'user_email' => $email]) === $ref1);

$doc = $db->error_events->findOne(['ref' => $ref1]);
test("event was written", $doc !== null);
test("event starts open", ($doc['status'] ?? '') === 'open');
test("event keeps the raw detail for admins", strpos((string)($doc['detail'] ?? ''), 'API key not valid') !== false);
test("event summary is excerpted", strlen((string)($doc['message'] ?? '')) <= 320);
test("event carries the context", ($doc['context'] ?? '') === 'quiz.generate');
test("event resolved the account", ($doc['user_email'] ?? '') === $email);
test("event resolved the user id", (string)($doc['user_id'] ?? '') === (string)$uid);
test("event recorded the job id", ($doc['extra']['job_id'] ?? '') === 'job-abc');
test("event is counted once", (int)($doc['count'] ?? 0) === 1);

// Second report inside the dedupe window must bump, not duplicate.
$again = errors_report([
    'context'    => 'quiz.generate',
    'message'    => $rawGemini,
    'user_email' => $email,
]);
$refs[] = $again;
$doc = $db->error_events->findOne(['ref' => $ref1]);
test("repeat inside a minute reuses the event", (int)($doc['count'] ?? 0) === 2, 'count=' . ($doc['count'] ?? 0));
test("repeat did not open a second event",
    $db->error_events->countDocuments(['ref' => $ref1]) === 1);

$ref2 = errors_report(['context' => 'exception.api', 'message' => 'Call to a member function getId() on null']);
$refs[] = $ref2;
test("a different failure gets its own reference", $ref2 !== $ref1);
test("anonymous failures are still recorded", ($db->error_events->findOne(['ref' => $ref2])['user_email'] ?? '') === '');

// ── What a learner sees ──
echo "\n--- Public message ---\n";

$public = errors_public($ref1);
test("public message quotes the reference", strpos($public, $ref1) !== false);
test("public message tells them what to do", stripos($public, 'try again') !== false && stripos($public, 'contact') !== false);
test("public message drops the raw failure", strpos($public, 'API key') === false && strpos($public, 'AIza') === false);
test("public message can carry a friendly reason",
    errors_public($ref1, errors_sanitize($rawGemini)) === 'The AI service rejected our credentials. Please try again. If it keeps happening, contact an admin and quote reference ' . $ref1 . '.');
test("technical fallback copy is replaced by the generic sentence",
    errors_public($ref1, $rawGemini) === 'Something went wrong on our side. Please try again. If it keeps happening, contact an admin and quote reference ' . $ref1 . '.');

$errorsToUserRef = null;
$friendly = errors_to_user($rawGemini, $errorsToUserRef);
$refs[] = $errorsToUserRef;
test("errors_to_user never returns the raw payload", strpos($friendly, 'AIza') === false && strpos($friendly, 'domain:') === false);
test("errors_to_user returns a readable sentence", strpos($friendly, 'credentials') !== false, $friendly);
test("errors_to_user reports what it hid", (bool)preg_match('/^ERR-[0-9A-F]{6}$/', (string)$errorsToUserRef));

$noRef = null;
test("errors_to_user passes friendly text through untouched", errors_to_user($plain, $noRef) === $plain && $noRef === null);

// ── Review flow ──
echo "\n--- Review flow ---\n";

test("statuses are open / acknowledged / resolved",
    array_keys(error_statuses()) === ['open', 'acknowledged', 'resolved']);
test("acknowledge moves the event", errors_set_status($ref1, 'acknowledged', 'boss@example.com'));
test("event is acknowledged", ($db->error_events->findOne(['ref' => $ref1])['status'] ?? '') === 'acknowledged');
test("acknowledgement is attributed",
    ($db->error_events->findOne(['ref' => $ref1])['handled_by'] ?? '') === 'boss@example.com');
test("resolve moves the event", errors_set_status($ref1, 'resolved', 'boss@example.com'));
test("event is resolved", ($db->error_events->findOne(['ref' => $ref1])['status'] ?? '') === 'resolved');
test("re-open moves the event back", errors_set_status($ref1, 'open', 'boss@example.com'));
test("event is open again", ($db->error_events->findOne(['ref' => $ref1])['status'] ?? '') === 'open');
test("unknown status is rejected", !errors_set_status($ref1, 'wibble', 'boss@example.com'));
test("unknown reference is rejected", !errors_set_status('ERR-NOPE0', 'open', 'boss@example.com'));

test("filter defaults to the open queue", errors_filter(['status' => 'open']) === ['status' => 'open']);
test("filter drops an unknown status", errors_filter(['status' => 'nonsense']) === []);
$ctxFilter = errors_filter(['context' => 'quiz.generate', 'user' => $email]);
test("filter combines context and user",
    ($ctxFilter['context'] ?? '') === 'quiz.generate' && isset($ctxFilter['$or']));
test("filter takes a date range", isset(errors_filter(['from' => '2026-01-01', 'to' => '2026-01-31'])['created_at']['$gte']));
test("filter ignores a malformed date", !isset(errors_filter(['from' => 'nope'])['created_at']));
test("rows are readable", count(errors_rows(['ref' => ['$in' => $refs]])) >= 1);
test("count matches", errors_count(['ref' => ['$in' => $refs]]) >= 1);
test("find by reference works", (errors_find($ref1)['context'] ?? '') === 'quiz.generate');
test("context list includes ours", in_array('quiz.generate', error_contexts(), true));
$counts = errors_counts();
test("counts are numeric", is_int($counts['open']) && is_int($counts['resolved']) && is_int($counts['total']));

// ── Rendered monitor ──
echo "\n--- Rendered monitor ---\n";

$_GET = ['status' => 'open'];
ob_start();
include $template;
$mon = ob_get_clean();

test("page is headed Error Monitor", strpos($mon, 'Error Monitor') !== false);
test("page shows the reference", strpos($mon, $ref1) !== false);
test("page shows the queue", strpos($mon, 'Awaiting review') !== false);
test("page offers acknowledge, resolve and re-open", strpos($mon, 'err-act') !== false);
$posPayload = strpos($mon, '<script type="application/json"');
$posRaw     = strpos($mon, 'API key not valid');
test("page keeps the raw failure for the audit", $posRaw !== false && $posPayload !== false && $posRaw > $posPayload,
    'payload=' . var_export($posPayload, true) . ' raw=' . var_export($posRaw, true));
$monVisible = preg_replace('#<script[^>]*type="application/json"[^>]*>.*?</script>#s', '', $mon);
test("raw failure never appears outside the payloads",
    strpos($monVisible, 'API key not valid') === false,
    substr($monVisible, max(0, (int)strpos($monVisible, 'API key not valid') - 80), 160));
test("page shows a friendly summary instead", strpos($mon, 'The AI service rejected our credentials.') !== false);
test("raw payload lives in the modal, not in the row",
    substr_count($mon, '<div class="err-detail') === 0);
test("page links back to the admin dashboard", strpos($mon, '/admin') !== false);
test("page explains learners never see the raw text", stripos($mon, 'Learners never see this text') !== false);

$_GET = ['status' => 'all', 'context' => 'exception.api'];
ob_start();
include $template;
$monAll = ob_get_clean();
test("context filter narrows the queue", strpos($monAll, $ref2) !== false && strpos($monAll, $ref1) === false);
$_GET = ['status' => 'all', 'user' => $email];
ob_start();
include $template;
$monUser = ob_get_clean();
test("user filter narrows the queue", strpos($monUser, $ref1) !== false && strpos($monUser, $ref2) === false);

$_GET = ['status' => 'all'];
ob_start();
include $template;
$monAny = ob_get_clean();
test("empty queue explains itself", strpos($monAny, 'Nothing in this view') !== false
    || strpos($monAny, $ref1) !== false);

// ── _error.php gating ──
echo "\n--- Error page gating ---\n";

$adminUser = 'err_admin_' . time() . '@example.com';
$plainUser = 'err_plain_' . time() . '@example.com';
create_test_user($adminUser, 'superuser');
create_test_user($plainUser, 'user');
$adminDoc = $db->users->findOne(['email' => $adminUser]);

$savedAuth = Session::$authStatus;
$savedUser = Session::$userSession;
$exception = new RuntimeException('MongoDB\Driver\Exception\AuthenticationFailed in /var/www/labs/htdocs/src/lib/core/DatabaseConnection.class.php');

// learner view
Session::$authStatus = Constants::STATUS_LOGGEDIN;
Session::$userSession = new UserSession((string)$db->users->findOne(['email' => $plainUser])['username']);
Session::set('error_exception', $exception);
Session::set('error_ref', $ref1);
ob_start();
include SRC_PATH . '/template/_error.php';
$errUser = ob_get_clean();
test("learner sees a plain explanation", strpos($errUser, 'Something went wrong on our side') !== false);
test("learner sees the reference", strpos($errUser, $ref1) !== false);
test("learner is told what to do", stripos($errUser, 'contact an admin') !== false);
test("learner does NOT see the exception class", strpos($errUser, 'Caused by:') === false);
test("learner does NOT see the stack trace", strpos($errUser, 'Stack Trace:') === false);
test("learner does NOT see the file path", strpos($errUser, 'DatabaseConnection.class.php') === false);

// admin view
Session::$userSession = new UserSession((string)$adminDoc['username']);
ob_start();
include SRC_PATH . '/template/_error.php';
$errAdmin = ob_get_clean();
test("admin sees the exception class", strpos($errAdmin, 'Caused by: RuntimeException') !== false);
test("admin sees the stack trace", strpos($errAdmin, 'Stack Trace:') !== false);
test("admin sees the originating file", strpos($errAdmin, 'DatabaseConnection.class.php') !== false);
test("admin still sees the reference", strpos($errAdmin, $ref1) !== false);

Session::$authStatus = $savedAuth;
Session::$userSession = $savedUser;
Session::set('error_exception', null);
Session::set('error_ref', null);
cleanup_test_user($adminUser);
cleanup_test_user($plainUser);

// ── Quiz job status over HTTP (the screenshot case) ──
echo "\n--- Quiz job status ---\n";

$jobEmail = 'err_job_' . time() . '@example.com';
$jobSession = create_test_user($jobEmail, 'user');
$jobUid = 990000 + random_int(1, 999);
$db->users->updateOne(['email' => $jobEmail], ['$set' => ['user_id' => $jobUid]]);
$jobUser = $db->users->findOne(['email' => $jobEmail]);
$jobId = 'job_err_' . time();
$db->quiz_jobs->insertOne([
    '_id'               => $jobId,
    'user_id'           => (int)$jobUid,
    'user_email'        => $jobEmail,
    'percentage'        => 100,
    'available'         => false,
    'status_text'       => $rawGemini,
    'generation_failed' => true,
    'generation_attempt'=> 1,
    'created_at'        => time(),
]);

$resp = http_request('GET', '/api/quiz/job_status.php?job_id=' . urlencode($jobId), ['cookie' => "session_token=$jobSession"]);
$json = json_decode($resp['body'], true);
test("job status returns 200", $resp['status'] === 200, 'status=' . $resp['status']);
test("job status reports the failure", ($json['generation_failed'] ?? false) === true, $resp['body']);
test("job status exposes an error field for the modal", !empty($json['error']), $resp['body']);
test("job status hides the raw API key payload", strpos($resp['body'], 'API key not valid') === false
    && strpos($resp['body'], 'AIza') === false, $resp['body']);
test("job status quotes a reference", preg_match('/ERR-[0-9A-F]{6}/', $resp['body']) === 1, $resp['body']);
test("job status keeps a readable status_text", strpos((string)($json['status_text'] ?? ''), 'credentials') !== false,
    $resp['body']);
$jobRef = null;
if (preg_match('/ERR-[0-9A-F]{6}/', $resp['body'], $m)) { $jobRef = $m[0]; $refs[] = $jobRef; }

$resp = http_request('GET', '/api/quiz/job_status.php?job_id=' . urlencode($jobId), ['cookie' => "session_token=$jobSession"]);
$second = $resp['body'];
test("a second poll reuses the same reference", $jobRef !== null && strpos($second, $jobRef) !== false, $second);
test("polling does not open a second event", $jobRef !== null && $db->error_events->countDocuments(['ref' => $jobRef]) === 1);

$resp = http_request('GET', '/api/quiz/job_status.php?job_id=' . urlencode($jobId));
test("job status requires a session", in_array($resp['status'], [401, 302, 403], true), 'status=' . $resp['status']);

$db->quiz_jobs->deleteMany(['_id' => $jobId]);
cleanup_test_user($jobEmail);

// Source-level guard: no endpoint may echo an exception message again.
test("roadmap job status sanitises its failed branch", strpos($roadmapsSrc, 'errors_public(') !== false);
test("roadmap ask endpoint sanitises its catch block", strpos($askSrc, "getMessage()];") === false
    && strpos($askSrc, 'errors_public(') !== false);
test("quiz job status sanitises its catch block", strpos($jobStatusSrc, "['error' => \$e->getMessage()]") === false);
test("_error.php hides details behind the admin gate", substr_count($errorTplSrc, '$isAdmin && $e') >= 1);

// ── HTTP: monitor + review queue ──
echo "\n--- HTTP ---\n";

function err_session_cookie(string $sessionToken, string $path = '/dashboard'): array {
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

$httpAdminEmail = 'err_http_admin_' . time() . '@example.com';
$httpUserEmail  = 'err_http_user_'  . time() . '@example.com';
$httpAdmin = err_session_cookie(create_test_user($httpAdminEmail, 'superuser'));
$httpUser  = err_session_cookie(create_test_user($httpUserEmail, 'user'));

$resp = http_request('GET', '/admin/errors', ['cookie' => $httpAdmin['cookie']]);
test("Superuser gets 200 on /admin/errors", $resp['status'] === 200, 'status=' . $resp['status']);
test("Page is headed Error Monitor", strpos($resp['body'], 'Error Monitor') !== false);
test("Page shows the summary cards", strpos($resp['body'], 'Awaiting review') !== false);
test("Page shows our open event", strpos($resp['body'], $ref1) !== false);
$hVisible = preg_replace('#<script[^>]*type="application/json"[^>]*>.*?</script>#s', '', $resp['body']);
test("Page never exposes the raw failure outside the payloads",
    strpos($hVisible, 'API key not valid') === false, 'found outside payload');
test("Page's visible summary is friendly", strpos($resp['body'], 'The AI service rejected our credentials.') !== false);

$resp = http_request('GET', '/admin/errors', ['cookie' => $httpUser['cookie']]);
test("Normal user is redirected away", in_array($resp['status'], [301, 302, 403], true), 'status=' . $resp['status']);

$resp = http_request('GET', '/admin/errors');
test("Anonymous visitor is sent to sign in",
    in_array($resp['status'], [301, 302], true)
    && isset($resp['headers']['Location'])
    && strpos($resp['headers']['Location'], '/signin') !== false,
    'status=' . $resp['status'] . ' loc=' . ($resp['headers']['Location'] ?? ''));

$resp = http_request('GET', '/dashboard', ['cookie' => $httpAdmin['cookie']]);
test("Admin sidebar shows the Errors link", strpos($resp['body'], 'href="/admin/errors"') !== false);
test("CSRF token available for the queue API", $httpAdmin['csrf'] !== null);

$act = function (array $fields, ?string $cookie, ?string $csrf) {
    $headers = [];
    if ($csrf !== null) $headers[] = 'X-CSRF-Token: ' . $csrf;
    return http_request('POST', '/api/admin/update_error', [
        'cookie'  => $cookie ?? '',
        'headers' => $headers,
        'body'    => $fields,
    ]);
};

$resp = $act(['ref' => $ref1, 'status' => 'acknowledged'], $httpAdmin['cookie'], null);
$json = json_decode($resp['body'], true);
test("Update API rejects a missing CSRF token",
    ($json['status'] ?? '') !== 'success', $resp['body']);

$resp = $act(['ref' => $ref1, 'status' => 'acknowledged'], $httpAdmin['cookie'], $httpAdmin['csrf']);
$json = json_decode($resp['body'], true);
test("Superuser can acknowledge an error", ($json['status'] ?? '') === 'success', $resp['body']);
test("Event is acknowledged in the queue",
    ($db->error_events->findOne(['ref' => $ref1])['status'] ?? '') === 'acknowledged');

$resp = http_request('GET', '/admin/errors?status=acknowledged', ['cookie' => $httpAdmin['cookie']]);
test("Acknowledged queue lists it", strpos($resp['body'], $ref1) !== false);

$resp = $act(['ref' => $ref1, 'status' => 'resolved'], $httpAdmin['cookie'], $httpAdmin['csrf']);
$json = json_decode($resp['body'], true);
test("Superuser can resolve an error", ($json['status'] ?? '') === 'success', $resp['body']);

$resp = http_request('GET', '/admin/errors?status=resolved', ['cookie' => $httpAdmin['cookie']]);
test("Resolved history lists it", strpos($resp['body'], $ref1) !== false);

$resp = http_request('GET', '/admin/errors?status=open', ['cookie' => $httpAdmin['cookie']]);
test("Resolved error leaves the open queue", strpos($resp['body'], $ref1) === false);

$resp = $act(['ref' => 'ERR-000000', 'status' => 'open'], $httpAdmin['cookie'], $httpAdmin['csrf']);
$json = json_decode($resp['body'], true);
test("Unknown reference is refused", ($json['status'] ?? '') === 'error', $resp['body']);

$resp = $act(['ref' => $ref1, 'status' => 'resolved'], $httpUser['cookie'], $httpUser['csrf']);
$json = json_decode($resp['body'], true);
test("Normal user cannot move the queue", in_array($resp['status'], [401, 403, 302], true)
    || ($json['status'] ?? '') === 'error', 'status=' . $resp['status'] . ' body=' . $resp['body']);

$resp = http_request('GET', '/admin', ['cookie' => $httpAdmin['cookie']]);
test("Admin dashboard warns about open errors", strpos($resp['body'], 'awaiting review') !== false,
    'status=' . $resp['status']);
test("Admin dashboard links to the Error Monitor", strpos($resp['body'], '/admin/errors') !== false);

// ── "Where did it happen?" ──
echo "\n--- Location + details expander ---\n";

$autoRef = errors_report(['context' => 'quiz.generate', 'message' => 'probing for a source line']);
$refs[] = $autoRef;
$autoDoc = $db->error_events->findOne(['ref' => $autoRef]);
test("every event records the file that raised it", !empty($autoDoc['extra']['file']), json_encode($autoDoc['extra'] ?? []));
test("every event records a line number", (int)($autoDoc['extra']['line'] ?? 0) > 0);
test("every event has a where summary", !empty($autoDoc['extra']['where']), json_encode($autoDoc['extra'] ?? []));
test("where is file:line", (bool)preg_match('/:.\d+$/', (string)($autoDoc['extra']['where'] ?? '')), (string)($autoDoc['extra']['where'] ?? ''));
test("every event keeps a source trace", !empty($autoDoc['extra']['trace']));

function err_location_probe(): void {
    throw new RuntimeException('boom at a known place');
}
try {
    err_location_probe();
} catch (Throwable $ex) {
    $exRef = errors_from_exception($ex, 'demo.location')['ref'];
}
$refs[] = $exRef;
$exDoc = $db->error_events->findOne(['ref' => $exRef]);
test("exception events point at the throwing file", basename((string)($exDoc['extra']['file'] ?? '')) === 'test_error_reporting.php',
    (string)($exDoc['extra']['file'] ?? ''));
test("exception events keep the exception class", ($exDoc['extra']['class'] ?? '') === 'RuntimeException');
test("exception events keep the stack trace", strpos((string)($exDoc['extra']['trace'] ?? ''), 'err_location_probe') !== false,
    substr((string)($exDoc['extra']['trace'] ?? ''), 0, 160));

$_GET = ['status' => 'open'];
ob_start();
include $template;
$loc = ob_get_clean();
test("monitor hides the dead Bootstrap toggle", strpos($loc, 'data-bs-toggle="collapse"') === false);
test("monitor has no inline details dump", strpos($loc, '<details') === false);
test("details open as a CoreUI modal like Add Device", strpos($loc, 'id="errDetailModal"') !== false
    && strpos($loc, 'data-coreui-toggle="modal"') !== false);
test("every row can open the modal", substr_count($loc, 'err-open') >= 1);
test("row payloads are embedded for the modal", preg_match('/<script type="application\/json" id="[^"]+">\{.*\}<\/script>/s', $loc) === 1);
test("monitor shows the source line without expanding", preg_match('/<code[^>]*>[^<]*:[0-9]+<\/code>/', $loc) === 1);
test("modal renderer escapes user data", strpos($loc, 'function renderErrorDetail') !== false
    && strpos($loc, 'const esc =') !== false);
test("monitor shows the throwing class inside the payload", strpos($loc, 'RuntimeException') !== false);
test("modal offers the review actions", strpos($loc, 'id="errModalAck"') !== false
    && strpos($loc, 'id="errModalResolve"') !== false);

// ── Whole API surface ──
echo "\n--- API leak sweep ---\n";

$startTpl = file_get_contents(SRC_PATH . '/template/pages/quiz/start.php');
test("quiz modal prefers the friendly error field", strpos($startTpl, "job.error || 'AI Generation failed.'") !== false);
test("quiz generate modal prefers the friendly error field",
    file_get_contents(SRC_PATH . '/template/pages/quiz/generate.php') !== false
    && strpos(file_get_contents(SRC_PATH . '/template/pages/quiz/generate.php'), 'data.error || data.status_text') !== false);

$leaks = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(SRC_PATH . '/api', FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (!$f->isFile() || $f->getExtension() !== 'php') continue;
    $lines = file($f->getPathname());
    foreach ($lines as $i => $line) {
        if (strpos($line, '$e->getMessage()') === false) continue;
        // Job records keep the raw text; it is sanitised when the status is read.
        if (strpos($line, 'error_message') !== false || strpos($line, "'$set'") !== false) continue;
        if (strpos($line, 'errors_report(') !== false || strpos($line, 'errors_from_exception(') !== false) continue;
        $leaks[] = basename($f->getPathname()) . ':' . ($i + 1);
    }
}
test("no API endpoint echoes an exception message to a learner", count($leaks) === 0, implode(', ', $leaks));

$auditCount = 0;
foreach ($it2 = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(SRC_PATH . '/api', FilesystemIterator::SKIP_DOTS)) as $f) {
    if (!$f->isFile() || $f->getExtension() !== 'php') continue;
    $body = file_get_contents($f->getPathname());
    if (strpos($body, 'errors_from_exception(') !== false || strpos($body, 'errors_report(') !== false) $auditCount++;
}
test("the API surface is wired into the audit trail", $auditCount >= 60, 'wired files=' . $auditCount);

// ── Cleanup ──
echo "\n--- Cleanup ---\n";

$refs = array_values(array_filter($refs));
$db->error_events->deleteMany(['ref' => ['$in' => $refs]]);
test("cleanup removed the error events",
    $db->error_events->countDocuments(['ref' => ['$in' => $refs]]) === 0);
cleanup_test_user($email);
cleanup_test_user($httpAdminEmail);
cleanup_test_user($httpUserEmail);
test("cleanup removed the accounts",
    $db->users->countDocuments(['email' => ['$in' => [$email, $httpAdminEmail, $httpUserEmail]]]) === 0);

test_summary();
