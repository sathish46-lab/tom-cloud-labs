<?php
/**
 * Test: Admin sidebar active state.
 *
 * The sidebar lives outside #main-content, so HTMX never swaps it — active
 * state is re-synced by JS on every navigation. Two sync functions exist:
 * the authoritative one in _nav.php (honours data-exact / data-match /
 * data-admin-exit) and the fallback in htmx-bridge.js, which must delegate
 * to it instead of prefix-matching every link (that lit up "Dashboard" on
 * every /admin/* page).
 *
 * Usage:
 *   php workspace/tests/test_admin_sidebar_active.php
 */

require_once __DIR__ . '/bootstrap.php';

echo "=== Admin Sidebar Active State Tests ===\n\n";

$navSrc   = file_get_contents(SRC_PATH . '/template/_nav.php');
$bridgeSrc = file_get_contents(__DIR__ . '/../js/htmx-bridge.js');
$served    = file_get_contents(PROJECT_ROOT . '/htdocs/assets/js/htmx-bridge.js');

function flat(string $s): string {
    return preg_replace('/\s+/', ' ', $s);
}

// ── Static wiring ──
echo "--- Wiring ---\n";

test("nav sync function exists", strpos($navSrc, 'function syncSidebarActiveState(') !== false);
test("nav sync accepts a target url", strpos($navSrc, 'function syncSidebarActiveState(targetUrl)') !== false);
test("nav sync honours data-exact", strpos($navSrc, 'link.dataset.exact') !== false);
test("nav sync never marks 'Back to Labs' active", strpos($navSrc, 'link.dataset.adminExit') !== false);
test("bridge delegates to the nav sync", strpos($bridgeSrc, 'window.syncSidebarActiveState') !== false);
test("bridge fallback no longer prefix-matches blindly",
    strpos($bridgeSrc, 'path.startsWith(linkPath) &&') === false);
test("built htmx-bridge.js matches the source", $bridgeSrc === $served);

// ── Runtime: exactly one active link per route ──
echo "\n--- Rendered + Synced State ---\n";

$email = 'sidebar_active_' . time() . '@example.com';
$token = create_test_user($email, 'superuser');
$resp  = http_request('GET', '/dashboard', ['cookie' => 'session_token=' . $token]);
$sid = '';
if (isset($resp['headers']['Set-Cookie'])
    && preg_match('/PHPSESSID=([^;]+)/', $resp['headers']['Set-Cookie'], $m)) {
    $sid = $m[1];
}
$cookie = 'session_token=' . $token . ($sid !== '' ? '; PHPSESSID=' . $sid : '');

$routes = [
    '/admin'            => '/admin',
    '/admin/users'      => '/admin/users',
    '/admin/appearance' => '/admin/appearance',
    '/admin/storage'    => '/admin/storage',
];

foreach ($routes as $path => $expected) {
    $body = http_request('GET', $path, ['cookie' => $cookie])['body'];
    $start = strpos($body, 'admin-sidebar-nav');
    $end   = $start === false ? false : strpos($body, 'main-sidebar-nav', $start);
    $block = $start === false ? '' : substr($body, $start, $end - $start);

    preg_match_all('/<a class="nav-link([^"]*)" href="([^"]*)"/', $block, $m, PREG_SET_ORDER);
    $active = [];
    foreach ($m as $a) {
        if (strpos($a[1], ' active') !== false) $active[] = $a[2];
    }

    test("$path lights up exactly one link", count($active) === 1, implode(', ', $active));
    test("$path lights up itself", $active === [$expected], implode(', ', $active));
}

$resp = http_request('GET', '/admin/appearance', ['cookie' => $cookie])['body'];
test("Dashboard stays inactive on child routes",
    strpos($resp, 'class="nav-link active" href="/admin"') === false);

cleanup_test_user($email);

test_summary();
