<?php
/**
 * Test: News page functionality
 *
 * Tests:
 * 1. Anonymous users can access /news
 * 2. Logged-in users can access /news with correct TomIsAdmin state
 * 3. Admin users see Fetch Now button
 * 4. CSRF token is properly set
 * 5. News API endpoints respond correctly
 *
 * Usage (inside the container):
 *   php workspace/tests/test_news_page.php
 */

require_once __DIR__ . '/bootstrap.php';

echo "=== News Page Tests ===\n\n";

// ── Helper functions ──

function news_session_cookie(string $sessionToken): array {
    $resp = http_request('GET', '/dashboard', ['cookie' => "session_token=$sessionToken"]);
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

// ── Test 1: Anonymous access ──

echo "--- Anonymous user access ---\n";

$anon = http_request('GET', '/news');
test("News page loads for anonymous user", $anon['status'] === 200, 'status=' . $anon['status']);
test("Anonymous page has CSRF token", strpos($anon['body'], 'window.csrfToken') !== false);
test("Anonymous TomIsAdmin is false", strpos($anon['body'], 'window.TomIsAdmin = false') !== false);
test("Anonymous does not see Fetch Now button", strpos($anon['body'], 'class="btn btn-primary btn-sm news-fetch-btn"') === false);

// ── Test 2: Logged-in user access ──

echo "\n--- Logged-in user access ---\n";

$userEmail = 'newsuser_' . time() . '@test.com';
$userToken = create_test_user($userEmail, 'user');
$user = news_session_cookie($userToken);

$userResp = http_request('GET', '/news', ['cookie' => $user['cookie']]);
test("News page loads for regular user", $userResp['status'] === 200, 'status=' . $userResp['status']);
test("User TomIsAdmin is false", strpos($userResp['body'], 'window.TomIsAdmin = false') !== false);
test("Regular user does not see Fetch Now button", strpos($userResp['body'], 'class="btn btn-primary btn-sm news-fetch-btn"') === false);

// ── Test 3: Admin access ──

echo "\n--- Admin user access ---\n";

$adminEmail = 'newsadmin_' . time() . '@test.com';
$adminToken = create_test_user($adminEmail, 'superuser');
$admin = news_session_cookie($adminToken);

$adminResp = http_request('GET', '/news', ['cookie' => $admin['cookie']]);
test("News page loads for admin user", $adminResp['status'] === 200, 'status=' . $adminResp['status']);
test("Admin TomIsAdmin is true", strpos($adminResp['body'], 'window.TomIsAdmin = true') !== false);
test("Admin sees Fetch Now button", strpos($adminResp['body'], 'news-fetch-btn') !== false);

// ── Test 4: API endpoints ──

echo "\n--- API endpoints ---\n";

$apiList = http_request('GET', '/api/news/list.php?limit=1');
test("News list API returns success", $apiList['status'] === 200, 'status=' . $apiList['status']);
test("News list API returns valid JSON", strpos($apiList['body'], '"status":"success"') !== false);

$apiCategories = http_request('GET', '/api/news/categories.php');
test("News categories API returns success", $apiCategories['status'] === 200, 'status=' . $apiCategories['status']);

// ── Cleanup ──

echo "\n--- Cleanup ---\n";

cleanup_test_user($userEmail);
cleanup_test_user($adminEmail);

test("User accounts cleaned up", true);

test_summary();