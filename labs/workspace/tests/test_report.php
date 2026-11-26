<?php
/**
 * Test: Report Issues feature.
 *
 * REAL RUNTIME TEST — exercises the report submit API and asserts the
 * notification that reaches a superuser is a non-navigating popup carrying
 * the submitted data.
 *   1. Static wiring (API, JS helper, bell + quiz entry points)
 *   2. Happy path creates a report notification for superusers
 *   3. Auth, validation and CSRF boundaries
 *
 * Usage:
 *   php workspace/tests/test_report.php
 */

require_once __DIR__ . '/bootstrap.php';

echo "=== Report Issues Tests ===\n\n";

$apiSrc   = file_get_contents(SRC_PATH . '/api/reports/submit.php');
$reportJs = file_get_contents(PROJECT_ROOT . '/workspace/js/report.js');
$appJs    = file_get_contents(PROJECT_ROOT . '/htdocs/assets/js/app.js');
$siteNav  = file_get_contents(SRC_PATH . '/template/_sitenav.php');
$quizView = file_get_contents(SRC_PATH . '/template/pages/quiz/v.php');
$quizHub  = file_get_contents(SRC_PATH . '/template/pages/quiz/quiz_hub.php');
$quizJs   = file_get_contents(PROJECT_ROOT . '/workspace/js/quiz/v.js');

// ── Static wiring ──
echo "--- Wiring ---\n";

test('report API exists', file_exists(SRC_PATH . '/api/reports/submit.php'));
test('report JS helper exists', file_exists(PROJECT_ROOT . '/workspace/js/report.js'));
test('API requires authentication', strpos($apiSrc, 'AuthMiddleware::requireAuth()') !== false);
test('API requires CSRF', strpos($apiSrc, 'AuthMiddleware::requireCsrf()') !== false);
test('API rate limits submissions', strpos($apiSrc, "rate_limit('report:submit'") !== false);
test('API only accepts valid type slugs', strpos($apiSrc, "preg_match('/^[a-z0-9_\\-]{1,40}$/'") !== false);
test('API notifies superusers', strpos($apiSrc, "['role' => 'superuser']") !== false);
test('API stores a report popup payload', strpos($apiSrc, "'popup'    => 'report'") !== false);
test('JS helper exposes TomReport', strpos($reportJs, 'window.TomReport') !== false);
test('Bundled app.js includes TomReport', strpos($appJs, 'window.TomReport') !== false);
test('Bell opens report popups instead of navigating', strpos($siteNav, "clicked.data.popup === 'report'") !== false);
test('Bell calls TomReport.view', strpos($siteNav, 'window.TomReport.view(clicked)') !== false);
test('Quiz result button opens TomReport', strpos($quizView, "TomReport.open({type:'quiz'") !== false);
test('Quiz hub report link opens TomReport', strpos($quizHub, "TomReport.open({type:'quiz_hub'}") !== false);
test('Old quiz mailto handler removed', strpos($quizJs, 'mailto:support@tomweb.in') === false);

// ── Runtime setup ──
$db = DatabaseConnection::getDefaultDatabase();

$reporterEmail  = 'report_reporter_' . bin2hex(random_bytes(4)) . '@test.tomweb.in';
$validatorEmail = 'report_validator_' . bin2hex(random_bytes(4)) . '@test.tomweb.in';
$adminEmail     = 'report_admin_' . bin2hex(random_bytes(4)) . '@test.tomweb.in';

$reporterToken  = create_test_user($reporterEmail, 'user');
$validatorToken = create_test_user($validatorEmail, 'user');
$adminToken     = create_test_user($adminEmail, 'superuser');

$reporter  = test_session_cookie($reporterToken);
$validator = test_session_cookie($validatorToken);
$admin     = test_session_cookie($adminToken);

$adminDoc = $db->users->findOne(['email' => $adminEmail]);
$adminUserId = isset($adminDoc['user_id']) && (string) $adminDoc['user_id'] !== ''
    ? (string) $adminDoc['user_id']
    : (string) $adminDoc['_id'];

$reporterEmails = [$reporterEmail, $validatorEmail];

$cleanup = function () use ($db, $reporterEmails) {
    $db->notifications->deleteMany([
        'category' => 'report',
        'type'     => 'report_issue',
        'data.report.reported_by' => ['$in' => $reporterEmails],
    ]);
};

$cleanup();

$post = function (array $body, ?array $session = null, ?string $csrf = null) {
    $headers = ['Content-Type: application/json'];
    if ($csrf !== null) $headers[] = 'X-CSRF-Token: ' . $csrf;
    $opts = ['headers' => $headers, 'body' => json_encode($body)];
    if ($session !== null) $opts['cookie'] = $session['cookie'];
    return http_request('POST', '/api/reports/submit', $opts);
};

// ── Happy path ──
echo "\n--- Happy Path ---\n";

$resp = $post([
    'type'      => 'quiz',
    'entity_id' => 'quiz_abc123',
    'message'   => 'The correct answer for question 3 is marked wrong.',
    'page'      => '/quiz/v/quiz_abc123',
], $reporter, $reporter['csrf']);
$json = json_decode($resp['body'], true);

test('Report submission succeeds', ($json['success'] ?? false) === true, $resp['body']);
test('Report delivered to at least one superuser', (int) ($json['delivered'] ?? 0) >= 1, $resp['body']);

$doc = $db->notifications->findOne([
    'user_id'  => $adminUserId,
    'category' => 'report',
    'type'     => 'report_issue',
]);
test('Superuser received the report notification', $doc !== null);

$data   = $doc ? (array) ($doc['data'] ?? []) : [];
$report = (array) ($data['report'] ?? []);

test('Notification is marked as a report popup', ($data['popup'] ?? '') === 'report', json_encode($data));
test('Notification does not navigate', ($data['url'] ?? '') === '#', json_encode($data));
test('Report type stored', ($report['type'] ?? '') === 'quiz', json_encode($report));
test('Report entity id stored', ($report['entity_id'] ?? '') === 'quiz_abc123', json_encode($report));
test('Report message stored', strpos((string) ($report['message'] ?? ''), 'question 3') !== false, json_encode($report));
test('Reporter email stored', ($report['reported_by'] ?? '') === $reporterEmail, json_encode($report));
test('Reporter id stored', ($report['reported_by_id'] ?? '') !== '', json_encode($report));
test('Report timestamp stored', ($report['at'] ?? '') !== '', json_encode($report));
test('Report title names the type', strpos((string) ($doc['title'] ?? ''), 'quiz') !== false, (string) ($doc['title'] ?? ''));

// ── Auth boundary ──
echo "\n--- Auth Boundary ---\n";

$resp = $post(['type' => 'quiz', 'message' => 'Anonymous attempt to report.']);
test('Anonymous request is rejected', in_array($resp['status'], [401, 403], true), 'status=' . $resp['status']);

// ── Validation (separate reporter keeps the rate-limit bucket fresh) ──
echo "\n--- Validation ---\n";

$resp = $post(['type' => 'quiz', 'message' => 'no'], $validator, $validator['csrf']);
test('Too-short message rejected', $resp['status'] === 400, 'status=' . $resp['status'] . ' ' . $resp['body']);

$resp = $post(['message' => 'Missing type should fail'], $validator, $validator['csrf']);
test('Missing type rejected', $resp['status'] === 400, 'status=' . $resp['status'] . ' ' . $resp['body']);

$resp = $post(['type' => 'quiz'], $validator, $validator['csrf']);
test('Missing message rejected', $resp['status'] === 400, 'status=' . $resp['status'] . ' ' . $resp['body']);

$resp = $post(['type' => 'Bad Type!', 'message' => 'Invalid type should fail'], $validator, $validator['csrf']);
test('Invalid type rejected', $resp['status'] === 400, 'status=' . $resp['status'] . ' ' . $resp['body']);

// ── CSRF ──
echo "\n--- CSRF ---\n";

$resp = $post(['type' => 'quiz', 'message' => 'Missing CSRF should fail.'], $reporter, null);
test('Missing CSRF rejected', $resp['status'] === 403, 'status=' . $resp['status'] . ' ' . $resp['body']);

$resp = $post(['type' => 'quiz', 'message' => 'Bad CSRF should fail.'], $reporter, 'deadbeef');
test('Invalid CSRF rejected', $resp['status'] === 403, 'status=' . $resp['status'] . ' ' . $resp['body']);

// ── Cleanup ──
$cleanup();
test('Test notifications cleaned up', $db->notifications->countDocuments([
    'category' => 'report',
    'type'     => 'report_issue',
    'data.report.reported_by' => ['$in' => $reporterEmails],
]) === 0);

cleanup_test_user($reporterEmail);
cleanup_test_user($validatorEmail);
cleanup_test_user($adminEmail);

test_summary();
