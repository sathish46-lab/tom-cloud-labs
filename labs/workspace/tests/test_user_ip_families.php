<?php
/**
 * Test: per-family client IP fields (ip_address / ip_address_v4 / ip_address_v6)
 *
 * Verifies get_client_ip_fields() classifies the current client IP by address
 * family and that the admin user view renders both IPv4 and IPv6 rows.
 *
 * Usage:
 *   php workspace/tests/test_user_ip_families.php
 */

require_once __DIR__ . '/bootstrap.php';

echo "=== Client IP Family Fields ===\n";

test('get_client_ip_fields() is defined', function_exists('get_client_ip_fields'));

// Explicit IPv4
$fields = get_client_ip_fields('203.0.113.10');
test('IPv4: ip_address set', ($fields['ip_address'] ?? '') === '203.0.113.10');
test('IPv4: ip_address_v4 set', ($fields['ip_address_v4'] ?? '') === '203.0.113.10');
test('IPv4: no ip_address_v6', !array_key_exists('ip_address_v6', $fields));

// Explicit IPv6
$fields = get_client_ip_fields('2001:db8::1');
test('IPv6: ip_address set', ($fields['ip_address'] ?? '') === '2001:db8::1');
test('IPv6: ip_address_v6 set', ($fields['ip_address_v6'] ?? '') === '2001:db8::1');
test('IPv6: no ip_address_v4', !array_key_exists('ip_address_v4', $fields));

// Compressed IPv6 form
$fields = get_client_ip_fields('2001:4490:4ea9:82e9:d4df:6c75:a747:4c80');
test('Full-length IPv6 classified as v6', array_key_exists('ip_address_v6', $fields));

// Runtime: resolved from CF-Connecting-Ip header
$_SERVER = ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_CF_CONNECTING_IP' => '2001:db8:85a3::8a2e:370:7334'];
$fields = get_client_ip_fields();
test('CF header IPv6 → ip_address_v6', ($fields['ip_address_v6'] ?? '') === '2001:db8:85a3::8a2e:370:7334');
test('CF header IPv6 → no ip_address_v4', !array_key_exists('ip_address_v4', $fields));

$_SERVER = ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_CF_CONNECTING_IP' => '198.51.100.77'];
$fields = get_client_ip_fields();
test('CF header IPv4 → ip_address_v4', ($fields['ip_address_v4'] ?? '') === '198.51.100.77');
test('CF header IPv4 → no ip_address_v6', !array_key_exists('ip_address_v6', $fields));

$_SERVER = ['REMOTE_ADDR' => '192.168.1.100'];
$fields = get_client_ip_fields();
test('Fallback to REMOTE_ADDR → ip_address_v4', ($fields['ip_address_v4'] ?? '') === '192.168.1.100');

$_SERVER = ['REMOTE_ADDR' => 'CLI'];

echo "\n=== Admin user view rendering ===\n";

$viewPath = SRC_PATH . '/template/pages/admin/user_view.php';
test('user_view.php exists', file_exists($viewPath));

if (file_exists($viewPath)) {
    $src = file_get_contents($viewPath);
    test('renders Last IPv4 row', strpos($src, '>Last IPv4</span>') !== false);
    test('renders Last IPv6 row', strpos($src, '>Last IPv6</span>') !== false);
    test('single Last IP row removed', strpos($src, '>Last IP</span>') === false);
    test('legacy ip_address fallback used', strpos($src, '$lastIpV4') !== false && strpos($src, '$lastIpV6') !== false);
}

echo "\n=== Write sites use family fields ===\n";

$writeSites = [
    'auth/signup.php',
    'auth/finish-signup.php',
    'src/lib/core/UserSession.class.php',
    'src/lib/core/Auth/GoogleAuth.class.php',
    'src/api/auth/verify_login_2fa.php',
];
foreach ($writeSites as $site) {
    $path = PROJECT_ROOT . '/htdocs/' . $site;
    $ok = file_exists($path) && strpos((string)file_get_contents($path), 'get_client_ip_fields') !== false;
    test("$site writes get_client_ip_fields()", $ok);
}

test_summary();
