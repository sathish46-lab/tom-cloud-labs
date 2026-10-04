<?php
/**
 * Test: Public profile must never expose a role badge.
 *
 * The hero used to render the role pill for every visitor because it gated
 * on the *profile owner's* role ($pfIsStaff) instead of the viewer's. The
 * badge is now removed entirely — anonymous, owner and staff views all show
 * only the rank pill.
 *
 * Usage:
 *   php workspace/tests/test_profile_public.php
 */

require_once __DIR__ . '/bootstrap.php';

echo "=== Public Profile Role Badge Tests ===\n\n";

$tplSrc = file_get_contents(SRC_PATH . '/template/pages/profile.php');

function pf_session(string $sessionToken): string {
    $resp = http_request('GET', '/dashboard', ['cookie' => 'session_token=' . $sessionToken]);
    $sid = '';
    if (isset($resp['headers']['Set-Cookie'])
        && preg_match('/PHPSESSID=([^;]+)/', $resp['headers']['Set-Cookie'], $m)) {
        $sid = $m[1];
    }
    return 'session_token=' . $sessionToken . ($sid !== '' ? '; PHPSESSID=' . $sid : '');
}

function pf_has_role_badge(string $body): bool {
    return strpos($body, 'pf-role-pill') !== false || strpos($body, 'pf-role-') !== false;
}

// ── Static ──
echo "--- Template ---\n";

test("template no longer renders a role pill", strpos($tplSrc, 'pf-role-pill') === false);
test("template no longer branches on the owner's staff flag", strpos($tplSrc, '$pfIsStaff') === false);
test("private account card still exists for owner/staff", strpos($tplSrc, '<span>Role</span>') !== false);

// ── Runtime ──
echo "\n--- Rendered profile ---\n";

$staffEmail = 'pf_staff_' . time() . '@example.com';
$staff2Email = 'pf_staff2_' . time() . '@example.com';
$userEmail  = 'pf_user_' . time() . '@example.com';
$staffUser  = create_test_user($staffEmail, 'superuser');
$staff2User = create_test_user($staff2Email, 'superuser');
$normalUser = create_test_user($userEmail, 'user');
$staffHandle = explode('@', $staffEmail)[0];

$target = '/profile?username=' . urlencode($staffHandle);

$anon = http_request('GET', $target);
test("Public profile loads for anonymous visitors", $anon['status'] === 200, 'status=' . $anon['status']);
test("Anonymous view has no role badge", !pf_has_role_badge($anon['body']));
test("Anonymous view keeps the rank pill", strpos($anon['body'], 'pf-rank-pill') !== false);

$owner = http_request('GET', $target, ['cookie' => pf_session($staffUser)]);
test("Owner view has no role badge", !pf_has_role_badge($owner['body']));

$viewer = http_request('GET', $target, ['cookie' => pf_session($normalUser)]);
test("Regular user's view has no role badge", !pf_has_role_badge($viewer['body']));

$staffViewer = http_request('GET', $target, ['cookie' => pf_session($staff2User)]);
test("Superuser viewing a superuser has no role badge", !pf_has_role_badge($staffViewer['body']));

test("Owner email stays hidden from anonymous visitors",
    strpos($anon['body'], $staffEmail) === false);

cleanup_test_user($staffEmail);
cleanup_test_user($staff2Email);
cleanup_test_user($userEmail);

test_summary();
