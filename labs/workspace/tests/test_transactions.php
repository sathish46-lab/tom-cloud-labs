<?php
/**
 * Test: Admin → Platform → Transaction Monitor + the Zeal/Jolt adjust control.
 *
 * Covers:
 *   1. Wiring (controller, template, .htaccess, sidebar, API, helper autoload)
 *   2. currency_adjust() — credit, debit, reasons, insufficient balance, audit
 *   3. Quiz::updateUserStats() writing the ledger
 *   4. Filters, totals and the earnings breakdown
 *   5. Access control + the adjust API over HTTP
 *   6. Rendered page markers
 *
 * Usage:
 *   php workspace/tests/test_transactions.php
 */

require_once __DIR__ . '/bootstrap.php';

echo "=== Transaction Monitor Tests ===\n\n";

$controller    = PROJECT_ROOT . '/htdocs/app/admin/transactions.php';
$template      = SRC_PATH . '/template/pages/admin/transactions.php';
$adjustApi     = SRC_PATH . '/api/admin/adjust_currency.php';
$currencySrc   = SRC_PATH . '/utils/currency.php';
$htaccessSrc   = file_get_contents(PROJECT_ROOT . '/htdocs/.htaccess');
$navSrc        = file_get_contents(SRC_PATH . '/template/_nav.php');
$loadSrc       = file_get_contents(SRC_PATH . '/load.php');
$quizSrc       = file_get_contents(SRC_PATH . '/lib/labs/Quiz.class.php');
$userViewSrc   = file_get_contents(SRC_PATH . '/template/pages/admin/user_view.php');

function tx_flat(string $s): string { return preg_replace('/\s+/', ' ', $s); }

// ── Wiring ──
echo "--- Wiring ---\n";

test("controller app/admin/transactions.php exists", file_exists($controller));
test("page template pages/admin/transactions.php exists", file_exists($template));
test("adjust API src/api/admin/adjust_currency.php exists", file_exists($adjustApi));
test("ledger helper src/utils/currency.php exists", file_exists($currencySrc));
test(".htaccess routes /admin/transactions", strpos($htaccessSrc, 'RewriteRule ^admin/transactions/?$') !== false);
test("sidebar links to /admin/transactions", strpos($navSrc, "'url' => '/admin/transactions'") !== false);
test("controller enforces admin", strpos(file_get_contents($controller), 'AuthMiddleware::isAdmin()') !== false);
test("adjust API enforces admin", strpos(file_get_contents($adjustApi), 'AuthMiddleware::requireAdmin()') !== false);
test("adjust API enforces CSRF", strpos(file_get_contents($adjustApi), 'AuthMiddleware::requireCsrf()') !== false);
test("adjust API routes through currency_adjust()", strpos(file_get_contents($adjustApi), 'currency_adjust(') !== false);
test("load.php autoloads the ledger helper", strpos($loadSrc, "require_once __DIR__ . '/utils/currency.php'") !== false);

echo "\n--- Taxonomy ---\n";

$groups = currency_groups();
test("types are grouped into Earnings, Spending and System",
    isset($groups['earning'], $groups['spending'], $groups['system']));
test("Admin Adjustment is a System type", currency_group_of('Admin Adjustment') === 'system');
test("Quiz Completion is an Earning", currency_group_of('Quiz Completion') === 'earning');
test("Hint is a Spending", currency_group_of('Hint') === 'spending');
test("unknown type falls back to system", currency_group_of('Nope') === 'system');
test("currency_label", currency_label('zeal') === 'Zeal' && currency_label('jolt') === 'Jolt');
test("currency_normalize rejects junk", currency_normalize('doge') === null && currency_normalize('ZEAL') === 'zeal');
test("direction aliases resolve",
    currency_normalize_direction('add') === 'earned' && currency_normalize_direction('subtract') === 'spent'
    && currency_normalize_direction('Earned') === 'earned' && currency_normalize_direction('sideways') === null);

// ── currency_adjust() ──
echo "\n--- Currency adjust ---\n";

$db     = DatabaseConnection::getDefaultDatabase();
$email  = 'tx_monitor_' . time() . '@example.com';
$actor  = 'tx_admin_' . time() . '@example.com';
create_test_user($email, 'user');

$balance = function (string $e, string $cur) use ($db): int {
    $row = $db->user_stats->findOne(['user_email' => $e]);
    return $row ? (int)($row[$cur] ?? 0) : -1;
};
$rowsFor = function (string $e) use ($db): array {
    return array_values(iterator_to_array($db->transactions->find(['user_email' => $e]), false));
};

$r = currency_adjust($email, 'zeal', 'add', 100, 'Seeding the ledger', $actor);
test("credit succeeds", ($r['status'] ?? '') === 'success', json_encode($r));
test("credit returns the new balance", ($r['balance'] ?? -1) === 100, json_encode($r));
test("balance persisted to user_stats", $balance($email, 'zeal') === 100, 'got ' . $balance($email, 'zeal'));
$doc = (array)$db->users->findOne(['email' => $email]);
test("denormalized mirror updated", (int)($doc['zeal_stats']['zeal'] ?? -1) === 100,
    'got ' . var_export($doc['zeal_stats']['zeal'] ?? null, true));

$rows = $rowsFor($email);
test("one ledger row written", count($rows) === 1, 'got ' . count($rows));
if (count($rows) === 1) {
    $row = $rows[0];
    test("ledger row direction/currency/amount", $row['direction'] === 'earned' && $row['currency'] === 'zeal' && (int)$row['amount'] === 100);
    test("ledger row carries the reason", ($row['reason'] ?? '') === 'Seeding the ledger');
    test("ledger row is grouped as System", ($row['group'] ?? '') === 'system' && ($row['type'] ?? '') === 'Admin Adjustment');
    test("ledger row records the acting admin", ($row['actor'] ?? '') === $actor);
    test("ledger row is valid", ($row['valid'] ?? false) === true);
    test("ledger row resolves the username", ($row['username'] ?? '') !== '');
}

$r = currency_adjust($email, 'zeal', 'subtract', 40, 'Correcting a mis-count', $actor);
test("debit succeeds", ($r['status'] ?? '') === 'success', json_encode($r));
test("debit reduces the balance", $balance($email, 'zeal') === 60, 'got ' . $balance($email, 'zeal'));
$afterDebit = $rowsFor($email);
test("debit ledger row direction", count($afterDebit) === 2 && $afterDebit[1]['direction'] === 'spent'
    && (int)$afterDebit[1]['amount'] === 40, json_encode(array_column($afterDebit, 'direction')));

$r = currency_adjust($email, 'zeal', 'subtract', 999999, 'Overdraw attempt', $actor);
test("overdraft rejected", ($r['status'] ?? '') === 'error', json_encode($r));
test("overdraft message names the balance", stripos($r['error'] ?? '', 'Insufficient') !== false, $r['error'] ?? '');
test("balance untouched by overdraft", $balance($email, 'zeal') === 60, 'got ' . $balance($email, 'zeal'));
test("no ledger row for a rejected overdraft", count($rowsFor($email)) === 2, 'got ' . count($rowsFor($email)));

$r = currency_adjust($email, 'zeal', 'add', 10, 'no', $actor);
test("short reason rejected", ($r['status'] ?? '') === 'error' && stripos($r['error'] ?? '', 'reason') !== false, json_encode($r));

$r = currency_adjust($email, 'zeal', 'add', 10, '', $actor);
test("empty reason rejected", ($r['status'] ?? '') === 'error' && stripos($r['error'] ?? '', 'reason') !== false, json_encode($r));

$r = currency_adjust($email, 'zeal', 'add', 0, 'Zero amount', $actor);
test("zero amount rejected", ($r['status'] ?? '') === 'error', json_encode($r));

$r = currency_adjust($email, 'bitcoin', 'add', 10, 'Unknown currency', $actor);
test("unknown currency rejected", ($r['status'] ?? '') === 'error' && stripos($r['error'] ?? '', 'Currency') !== false, json_encode($r));

$r = currency_adjust($email, 'zeal', 'sideways', 10, 'Bad direction', $actor);
test("unknown direction rejected", ($r['status'] ?? '') === 'error' && stripos($r['error'] ?? '', 'Direction') !== false, json_encode($r));

$r = currency_adjust('nobody-' . time() . '@example.com', 'zeal', 'add', 10, 'Ghost account', $actor);
test("unknown user rejected", ($r['status'] ?? '') === 'error' && stripos($r['error'] ?? '', 'not found') !== false, json_encode($r));

test("jolt can be credited too",
    (currency_adjust($email, 'jolt', 'add', 25, 'Jolt seed', $actor)['status'] ?? '') === 'success'
    && $balance($email, 'jolt') === 35,
    'got ' . $balance($email, 'jolt')); // starter row seeds 10 jolt

// ── Quiz::updateUserStats() ──
echo "\n--- Learn/compete movements write the ledger ---\n";

\TomLabs\Labs\Quiz::updateUserStats($email, 50, 2, 'Quiz Completion', 'PHP Basics · perfect score');
$rows = $rowsFor($email);
test("Quiz credit wrote zeal + jolt rows", count($rows) === 5, 'got ' . count($rows));
$quizRows = array_values(array_filter($rows, fn($x) => ($x['type'] ?? '') === 'Quiz Completion'));
test("quiz rows typed 'Quiz Completion'", count($quizRows) === 2, 'got ' . count($quizRows));
test("quiz rows carry a description", isset($quizRows[0]['description']) && strpos($quizRows[0]['description'], 'PHP Basics') !== false);
test("balance moved with the quiz credit", $balance($email, 'zeal') === 110, 'got ' . $balance($email, 'zeal'));

\TomLabs\Labs\Quiz::updateUserStats($email, 0, -1, 'Generated Problem', 'Generated a quiz set');
$gen = array_values(array_filter($rowsFor($email), fn($x) => ($x['type'] ?? '') === 'Generated Problem'));
test("spend wrote a 'spent' ledger row", count($gen) === 1 && $gen[0]['direction'] === 'spent' && $gen[0]['currency'] === 'jolt');
test("jolt balance reflects the spend", $balance($email, 'jolt') === 36, 'got ' . $balance($email, 'jolt'));

\TomLabs\Labs\Quiz::updateUserStats($email, 0, 0, 'Quiz Completion', 'no movement');
test("zero movement writes nothing", count($rowsFor($email)) === 6, 'got ' . count($rowsFor($email)));

// ── Filters / totals / breakdown ──
echo "\n--- Filters and aggregates ---\n";

test("status defaults to valid only", currency_filter([])['valid'] === true);
test("status=all drops the valid clause", !array_key_exists('valid', currency_filter(['status' => 'all'])));
test("status=invalid filters to invalid", currency_filter(['status' => 'invalid'])['valid'] === false);
test("direction filter", currency_filter(['direction' => 'spent'])['direction'] === 'spent');
test("currency filter", currency_filter(['currency' => 'jolt'])['currency'] === 'jolt');
test("type filter", currency_filter(['type' => 'Hint'])['type'] === 'Hint');
test("group filter", currency_filter(['group' => 'spending'])['group'] === 'spending');
test("user filter matches email or username",
    isset(currency_filter(['user' => 'alice'])['$or']) && count(currency_filter(['user' => 'alice'])['$or']) === 4);
test("user filter is regex-escaped", strpos(json_encode(currency_filter(['user' => 'a.b+c'])), '\\\\.') !== false);

$f = currency_filter(['from' => '2026-01-01', 'to' => '2026-01-31']);
test("date range bounds are inclusive", ($f['created_at']['$gte'] ?? 0) > 0 && ($f['created_at']['$lte'] ?? 0) > $f['created_at']['$gte']);
test("bad dates are ignored", !isset(currency_filter(['from' => 'nope'])['created_at']));
test("empty date is ignored", !isset(currency_filter(['from' => ''])['created_at']));

$t = currency_totals(currency_filter(['user' => $email]));
test("totals count every row", $t['total'] === 6, 'got ' . $t['total']);
test("totals split earned/spent", ($t['earned']['n'] ?? 0) === 4 && ($t['spent']['n'] ?? 0) === 2,
    json_encode([$t['earned']['n'] ?? null, $t['spent']['n'] ?? null]));
test("totals split by currency", ($t['earned']['zeal'] ?? 0) === 150 && ($t['earned']['jolt'] ?? 0) === 27,
    json_encode($t['earned']));
test("totals exclude invalid rows", ($t['earned']['zeal'] ?? 0) === 150);

$db->transactions->insertOne([
    'user_email' => $email, 'direction' => 'earned', 'currency' => 'zeal', 'amount' => 1,
    'type' => 'Admin Adjustment', 'group' => 'system', 'description' => 'void', 'reason' => 'void',
    'source' => 'admin', 'actor' => $actor, 'valid' => false, 'created_at' => time(), 'username' => 'tx',
]);
$t = currency_totals(currency_filter(['user' => $email, 'status' => 'all']));
test("status=all includes the invalid row", $t['total'] === 7, 'got ' . $t['total']);
test("status=valid hides it again", currency_totals(currency_filter(['user' => $email]))['total'] === 6);

$bd = currency_breakdown($email, 'earned', 'zeal');
test("breakdown groups by type", count($bd) === 2, json_encode(array_column($bd, 'type')));
test("breakdown sums Admin Adjustment", ($bd[0]['type'] ?? '') === 'Admin Adjustment' && (int)$bd[0]['total'] === 100,
    json_encode($bd));
test("breakdown sums Quiz Completion", (int)($bd[1]['total'] ?? 0) === 50 && (int)($bd[1]['count'] ?? 0) === 1,
    json_encode($bd));
$bdJolt = currency_breakdown($email, 'earned', 'jolt');
test("breakdown ignores other currencies",
    count($bdJolt) === 2 && (int)($bdJolt[0]['total'] ?? 0) === 25 && (int)($bdJolt[1]['total'] ?? 0) === 2,
    json_encode($bdJolt));

$recent = currency_recent($email, 3);
test("recent rows are newest first", count($recent) === 3
    && (int)$recent[0]['created_at'] >= (int)$recent[1]['created_at']
    && (int)$recent[1]['created_at'] >= (int)$recent[2]['created_at']);
test("recent rows honour the limit", count(currency_recent($email, 2)) === 2);
test("rows paginate", count(currency_rows(currency_filter(['user' => $email]), 1, 2)) === 2);
test("page 2 returns the rest", count(currency_rows(currency_filter(['user' => $email]), 2, 2)) === 2);
test("count matches the filter", currency_count(currency_filter(['user' => $email])) === 6);
test("count respects status=all", currency_count(currency_filter(['user' => $email, 'status' => 'all'])) === 7);

// ── HTTP: access control ──
echo "\n--- Access control ---\n";

function tx_session_cookie(string $sessionToken, string $path = '/dashboard'): array {
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

$adminEmail = 'tx_admin_' . time() . '@example.com';
$userEmail  = 'tx_plain_' . time() . '@example.com';
$admin = tx_session_cookie(create_test_user($adminEmail, 'superuser'));
$user  = tx_session_cookie(create_test_user($userEmail, 'user'));

$resp = http_request('GET', '/admin/transactions', ['cookie' => $admin['cookie']]);
test("Superuser gets 200 on /admin/transactions", $resp['status'] === 200, 'status=' . $resp['status']);
test("Page is headed Transaction Monitor", strpos($resp['body'], 'Transaction Monitor') !== false);

$resp = http_request('GET', '/admin/transactions', ['cookie' => $user['cookie']]);
test("Normal user is redirected away", in_array($resp['status'], [301, 302, 403], true), 'status=' . $resp['status']);

$resp = http_request('GET', '/admin/transactions');
test("Anonymous visitor is sent to sign in",
    in_array($resp['status'], [301, 302], true)
    && isset($resp['headers']['Location'])
    && strpos($resp['headers']['Location'], '/signin') !== false,
    'status=' . $resp['status'] . ' loc=' . ($resp['headers']['Location'] ?? ''));

$resp = http_request('GET', '/dashboard', ['cookie' => $admin['cookie']]);
test("Admin sidebar shows the Transactions link", strpos($resp['body'], 'href="/admin/transactions"') !== false);
test("CSRF token available for the adjust API", $admin['csrf'] !== null);

// ── HTTP: adjust API ──
echo "\n--- Adjust API ---\n";

$adjust = function (array $fields, string $cookie, ?string $csrf = null) {
    $headers = [];
    if ($csrf !== null) $headers[] = 'X-CSRF-Token: ' . $csrf;
    return http_request('POST', '/api/admin/adjust_currency', [
        'cookie'  => $cookie,
        'headers' => $headers,
        'body'    => $fields,
    ]);
};

$resp = $adjust([
    'email' => $userEmail, 'currency' => 'zeal', 'direction' => 'add',
    'amount' => '75', 'reason' => 'Community event credit',
], $admin['cookie'], $admin['csrf']);
$json = json_decode($resp['body'], true);
test("Superuser credit succeeds", ($json['status'] ?? '') === 'success', $resp['body']);
test("Reported balance matches", (int)($json['balance'] ?? 0) === 75, $resp['body']);
test("Balance actually moved", $balance($userEmail, 'zeal') === 75, 'got ' . $balance($userEmail, 'zeal'));

$stored = array_values(iterator_to_array($db->transactions->find(['user_email' => $userEmail]), false));
test("Ledger row written over HTTP", count($stored) === 1, 'got ' . count($stored));
test("Ledger row keeps the reason",
    count($stored) === 1 && $stored[0]['reason'] === 'Community event credit');
test("Ledger row attributes the superuser",
    count($stored) === 1 && $stored[0]['actor'] === $adminEmail, json_encode($stored[0]['actor'] ?? null));

$resp = $adjust([
    'email' => $userEmail, 'currency' => 'zeal', 'direction' => 'subtract',
    'amount' => '10000', 'reason' => 'Trying to overdraw',
], $admin['cookie'], $admin['csrf']);
$json = json_decode($resp['body'], true);
test("Overdraft over HTTP rejected", ($json['status'] ?? '') === 'error', $resp['body']);
test("Balance unchanged after failed debit", $balance($userEmail, 'zeal') === 75, 'got ' . $balance($userEmail, 'zeal'));

$resp = $adjust([
    'email' => $userEmail, 'currency' => 'zeal', 'direction' => 'add', 'amount' => '10', 'reason' => '',
], $admin['cookie'], $admin['csrf']);
$json = json_decode($resp['body'], true);
test("Missing reason over HTTP rejected", ($json['status'] ?? '') === 'error', $resp['body']);

$resp = $adjust([
    'email' => $userEmail, 'currency' => 'zeal', 'direction' => 'add',
    'amount' => '10', 'reason' => 'No CSRF token here',
], $admin['cookie']);
test("Missing CSRF rejected", $resp['status'] >= 400 || (json_decode($resp['body'], true)['status'] ?? '') === 'error',
    'status=' . $resp['status'] . ' body=' . substr($resp['body'], 0, 120));

$resp = $adjust([
    'email' => $userEmail, 'currency' => 'zeal', 'direction' => 'add',
    'amount' => '10', 'reason' => 'Regular user should be blocked',
], $user['cookie'], $user['csrf']);
test("Normal user is forbidden", $resp['status'] >= 400 || (json_decode($resp['body'], true)['status'] ?? '') === 'error',
    'status=' . $resp['status']);
test("Forbidden request did not move the balance", $balance($userEmail, 'zeal') === 75, 'got ' . $balance($userEmail, 'zeal'));

$resp = $adjust([
    'email' => $userEmail, 'currency' => 'zeal', 'direction' => 'add',
    'amount' => '10', 'reason' => 'Anonymous attempt',
], '');
test("Anonymous request is blocked", $resp['status'] >= 400 || $resp['status'] === 0,
    'status=' . $resp['status']);
test("Anonymous request did not move the balance", $balance($userEmail, 'zeal') === 75, 'got ' . $balance($userEmail, 'zeal'));

// ── Rendered page ──
echo "\n--- Rendered page ---\n";

$page = http_request('GET', '/admin/transactions', ['cookie' => $admin['cookie']])['body'];
test("Summary cards present",
    strpos($page, 'Total Earned') !== false && strpos($page, 'Total Spent') !== false
    && strpos($page, '>Net<') !== false && strpos($page, '>Transactions<') !== false);
test("Table columns match the spec",
    strpos($page, '>Date<') !== false && strpos($page, '>Direction<') !== false
    && strpos($page, '>Amount<') !== false && strpos($page, '>Type<') !== false
    && strpos($page, '>Description<') !== false && strpos($page, '>Valid<') !== false);
test("Filters cover spec", strpos($page, 'id="txUser"') !== false && strpos($page, 'id="txType"') !== false
    && strpos($page, 'id="txDirection"') !== false && strpos($page, 'id="txCurrency"') !== false
    && strpos($page, 'id="txStatus"') !== false && strpos($page, 'id="txFrom"') !== false
    && strpos($page, 'id="txTo"') !== false);
test("Status defaults to Valid", preg_match('/id="txStatus"[^>]*>\s*<option value="valid"[^>]*selected/', $page) === 1);
test("Type options are grouped into Earnings/Spending/System",
    strpos($page, '<optgroup label="Earnings">') !== false
    && strpos($page, '<optgroup label="Spending">') !== false
    && strpos($page, '<optgroup label="System">') !== false);
test("Page states it is read-only", stripos($page, 'read-only') !== false);
test("No grant/adjust button on the monitor",
    strpos($page, 'id="uvAdjustSave"') === false && stripos($page, 'Apply adjustment') === false);
test("Per-user card appears when a user is filtered",
    strpos(http_request('GET', '/admin/transactions?user=' . urlencode($userEmail), ['cookie' => $admin['cookie']])['body'],
           'This user —') !== false);
$filtered = http_request('GET', '/admin/transactions?user=' . urlencode($userEmail), ['cookie' => $admin['cookie']])['body'];
test("Filtering by user returns their row", strpos($filtered, 'Community event credit') !== false);

echo "\n--- User page control ---\n";

test("user_view has the adjust block", strpos($userViewSrc, 'id="uvAdjustSave"') !== false);
test("user_view requires a reason", strpos($userViewSrc, "reason.length < 3") !== false);
test("user_view posts to the adjust API", strpos($userViewSrc, "/api/admin/adjust_currency") !== false);
test("user_view links each account to the monitor",
    strpos($userViewSrc, '/admin/transactions?user=') !== false);
test("user_view renders the earnings breakdown from the ledger",
    strpos($userViewSrc, 'currency_breakdown($email') !== false);

// Including a page template at top level shares scope — user_view.php reassigns $email.
$firstAccount = $email;
$txAccounts   = [$email, $userEmail, $adminEmail];

$_GET['email'] = $userEmail;
ob_start();
include SRC_PATH . '/template/pages/admin/user_view.php';
$uv = ob_get_clean();
test("User page renders the Zeal/Jolt adjust controls",
    strpos($uv, 'Adjust currency') !== false
    && strpos($uv, 'id="uvAdjustSave"') !== false
    && strpos($uv, 'id="uvAdjReason"') !== false,
    'len=' . strlen($uv));
test("User page shows live balances",
    strpos($uv, 'id="uvZealBal"') !== false && strpos($uv, 'id="uvJoltBal"') !== false);
test("User page links the account to the monitor",
    strpos($uv, '/admin/transactions?user=' . urlencode($userEmail)) !== false,
    'want=' . urlencode($userEmail));
test("User page shows its own transaction count",
    strpos($uv, 'All 1 transactions') !== false);

$_GET = ['user' => $userEmail];
ob_start();
include $template;
$tx = ob_get_clean();
test("Monitor renders the matching transaction", strpos($tx, 'Community event credit') !== false);
test("Monitor renders summary values for the filter", strpos($tx, 'Total Earned') !== false);
test("Monitor ticks valid rows", strpos($tx, 'bx-check-circle') !== false);

// The invalid row belongs to the other account — widen the filter to see it.
$_GET = ['user' => $firstAccount, 'status' => 'all'];
ob_start();
include $template;
$txAll = ob_get_clean();
test("Monitor crosses invalid rows when status=all", strpos($txAll, 'bx-x-circle') !== false);
test("Monitor hides invalid rows by default", strpos($tx, 'bx-x-circle') === false);
test("Monitor shows the acting admin on admin rows", strpos($txAll, $adminEmail) !== false);

// ── Cleanup ──
echo "\n--- Cleanup ---\n";

$db->transactions->deleteMany(['user_email' => ['$in' => $txAccounts]]);
$db->user_stats->deleteMany(['user_email' => ['$in' => $txAccounts]]);
cleanup_test_user($firstAccount);
cleanup_test_user($userEmail);
cleanup_test_user($adminEmail);
test("cleanup removed the ledger rows",
    $db->transactions->countDocuments(['user_email' => ['$in' => $txAccounts]]) === 0);
test("cleanup removed the accounts",
    $db->users->countDocuments(['email' => ['$in' => [$firstAccount, $userEmail]]]) === 0);

test_summary();
