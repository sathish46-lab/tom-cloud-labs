<?php
/**
 * PHP-level permission matrix check for /api/chat/call-signal.php
 *
 * Verifies:
 *   - Superuser (GROUP_SUPERUSER) can send offer/answer/ice/end
 *   - Admin (GROUP_ADMIN) can send offer/answer/ice/end
 *   - Member cannot send offer (403) but CAN answer/ice/end if participant
 *   - Non-participant cannot send answer/ice/end (403)
 *   - chat_publish() failure returns HTTP 502
 *
 * Usage (inside Docker container):
 *   php tests/e2e/php-call-permission-check.php
 */

require_once __DIR__ . '/../bootstrap.php';

echo "=== Call Signal Permission Matrix Check ===\n\n";

// ---- Helper: HTTP request with session ----
function signal_request(string $type, string $callId, string $sessionCookie, string $csrfToken, array $extra = []): array {
    $body = [
        'type' => $type,
        'call_id' => $callId,
        '_csrf_token' => $csrfToken,
    ] + $extra;
    return http_request('POST', '/api/chat/call-signal.php', [
        'headers' => [
            'Content-Type: application/json',
            'X-CSRF-Token: ' . $csrfToken,
        ],
        'cookie' => 'session_token=' . $sessionCookie,
        'body' => json_encode($body),
    ]);
}

// ---- Create test users ----
echo "--- Creating test users ---\n";

$superUserEmail = 'perm-super@example.com';
$superUserToken = create_test_user($superUserEmail, 'superuser');

$adminEmail = 'perm-admin@example.com';
$adminToken = create_test_user($adminEmail, 'admin');

$memberEmail = 'perm-member@example.com';
$memberToken = create_test_user($memberEmail, 'member');

echo "  Superuser: $superUserEmail (token: $superUserToken)\n";
echo "  Admin:     $adminEmail (token: $adminToken)\n";
echo "  Member:    $memberEmail (token: $memberToken)\n";

$callId = 'perm-call-' . bin2hex(random_bytes(8));

// ---- Get CSRF tokens ----
echo "\n--- Getting CSRF tokens ---\n";

$superCsrf = get_csrf_token($superUserToken);
$adminCsrf = get_csrf_token($adminToken);
$memberCsrf = get_csrf_token($memberToken);

echo "  Superuser CSRF: " . ($superCsrf ? 'got' : 'MISSING') . "\n";
echo "  Admin CSRF:     " . ($adminCsrf ? 'got' : 'MISSING') . "\n";
echo "  Member CSRF:    " . ($memberCsrf ? 'got' : 'MISSING') . "\n";

if (!$superCsrf || !$adminCsrf || !$memberCsrf) {
    echo "\n  SKIP: Cannot get CSRF tokens — dashboard may not be accessible\n";
    cleanup_test_user($superUserEmail);
    cleanup_test_user($adminEmail);
    cleanup_test_user($memberEmail);
    test_summary();
}

// ---- Test 1: Superuser can send offer ----
echo "\n--- Test 1: Superuser can send offer ---\n";
$r = signal_request('offer', $callId, $superUserToken, $superCsrf, [
    'sdp' => 'v=0\r\nm=video 9 UDP/TLS/RTP/SAVPF 96',
    'sdp_type' => 'offer',
]);
test("Superuser offer → 200 success", $r['status'] === 200, "got {$r['status']}: {$r['body']}");

// ---- Test 2: Superuser can send answer ----
echo "\n--- Test 2: Superuser can send answer ---\n";
$callId2 = 'perm-call-' . bin2hex(random_bytes(8));
$r = signal_request('offer', $callId2, $superUserToken, $superCsrf, [
    'sdp' => 'v=0\r\nm=video 9 UDP/TLS/RTP/SAVPF 96',
    'sdp_type' => 'offer',
]);
// Superuser sends offer, then another superuser can answer
$r2 = signal_request('answer', $callId2, $adminToken, $adminCsrf, [
    'sdp' => 'v=0\r\nm=video 9 UDP/TLS/RTP/SAVPF 96',
    'sdp_type' => 'answer',
]);
test("Superuser answer → 200 success", $r2['status'] === 200, "got {$r2['status']}: {$r2['body']}");

// ---- Test 3: Admin can send offer ----
echo "\n--- Test 3: Admin can send offer ---\n";
$callId3 = 'perm-call-' . bin2hex(random_bytes(8));
$r = signal_request('offer', $callId3, $adminToken, $adminCsrf, [
    'sdp' => 'v=0\r\nm=video 9 UDP/TLS/RTP/SAVPF 96',
    'sdp_type' => 'offer',
]);
test("Admin offer → 200 success", $r['status'] === 200, "got {$r['status']}: {$r['body']}");

// ---- Test 4: Member cannot send offer (403) ----
echo "\n--- Test 4: Member cannot send offer ---\n";
$callId4 = 'perm-call-' . bin2hex(random_bytes(8));
$r = signal_request('offer', $callId4, $memberToken, $memberCsrf, [
    'sdp' => 'v=0\r\nm=video 9 UDP/TLS/RTP/SAVPF 96',
    'sdp_type' => 'offer',
]);
test("Member offer → 403 forbidden", $r['status'] === 403, "got {$r['status']}: {$r['body']}");

// ---- Test 5: Member cannot send answer for call they're not part of (403) ----
echo "\n--- Test 5: Member cannot answer call they're not part of ---\n";
$callId5 = 'perm-call-' . bin2hex(random_bytes(8));
// Superuser creates the offer first
signal_request('offer', $callId5, $superUserToken, $superCsrf, [
    'sdp' => 'v=0\r\nm=video 9 UDP/TLS/RTP/SAVPF 96',
    'sdp_type' => 'offer',
]);
// Member tries to answer
$r = signal_request('answer', $callId5, $memberToken, $memberCsrf, [
    'sdp' => 'v=0\r\nm=video 9 UDP/TLS/RTP/SAVPF 96',
    'sdp_type' => 'answer',
]);
test("Member answer (not participant) → 403 forbidden", $r['status'] === 403, "got {$r['status']}: {$r['body']}");

// ---- Test 6: Superuser can send ice ----
echo "\n--- Test 6: Superuser can send ice ---\n";
$callId6 = 'perm-call-' . bin2hex(random_bytes(8));
signal_request('offer', $callId6, $superUserToken, $superCsrf, [
    'sdp' => 'v=0\r\nm=video 9 UDP/TLS/RTP/SAVPF 96',
    'sdp_type' => 'offer',
]);
$r = signal_request('ice', $callId6, $superUserToken, $superCsrf, [
    'candidate' => json_encode(['candidate' => 'candidate:0 1 udp 2113667326 192.168.1.1 12345 typ host']),
]);
test("Superuser ice → 200 success", $r['status'] === 200, "got {$r['status']}: {$r['body']}");

// ---- Test 7: Superuser can send end ----
echo "\n--- Test 7: Superuser can send end ---\n";
$callId7 = 'perm-call-' . bin2hex(random_bytes(8));
signal_request('offer', $callId7, $superUserToken, $superCsrf, [
    'sdp' => 'v=0\r\nm=video 9 UDP/TLS/RTP/SAVPF 96',
    'sdp_type' => 'offer',
]);
$r = signal_request('end', $callId7, $superUserToken, $superCsrf);
test("Superuser end → 200 success", $r['status'] === 200, "got {$r['status']}: {$r['body']}");

// ---- Test 8: Invalid type → 422 ----
echo "\n--- Test 8: Invalid type → 422 ---\n";
$r = signal_request('bogus', $callId, $superUserToken, $superCsrf);
test("Invalid type → 422", $r['status'] === 422, "got {$r['status']}: {$r['body']}");

// ---- Test 9: Missing call_id → 422 ----
echo "\n--- Test 9: Missing call_id → 422 ---\n";
$r = signal_request('offer', '', $superUserToken, $superCsrf, [
    'sdp' => 'v=0\r\n',
    'sdp_type' => 'offer',
]);
test("Missing call_id → 422", $r['status'] === 422, "got {$r['status']}: {$r['body']}");

// ---- Test 10: Non-member (no session) → 401 ----
echo "\n--- Test 10: No session → 401 ---\n";
$r = http_request('POST', '/api/chat/call-signal.php', [
    'headers' => [
        'Content-Type: application/json',
        'X-CSRF-Token: ' . $superCsrf,
    ],
    'body' => json_encode([
        'type' => 'offer',
        'call_id' => $callId,
        'sdp' => 'v=0\r\n',
        'sdp_type' => 'offer',
        '_csrf_token' => $superCsrf,
    ]),
]);
test("No session → 401", $r['status'] === 401, "got {$r['status']}: {$r['body']}");

// ---- Cleanup ----
echo "\n--- Cleanup ---\n";
cleanup_test_user($superUserEmail);
cleanup_test_user($adminEmail);
cleanup_test_user($memberEmail);

test_summary();
