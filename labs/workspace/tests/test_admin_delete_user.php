<?php
/**
 * Test: admin delete-user flow (OTP + backup + restore + admin-only viewers)
 *
 * Covers the pure OTP helpers, the HTTP delete/restore endpoints (CSRF,
 * typed phrases, expiry, guards), the snapshot backup integrity, the
 * deleted-users admin pages, and the admin-only public profile snapshot.
 *
 * Usage:
 *   php workspace/tests/test_admin_delete_user.php
 */

require_once __DIR__ . '/bootstrap.php';
require_once SRC_PATH . '/utils/user_delete.php';

echo "=== Admin delete user: unit helpers ===\n";

const DU_ADMIN  = 'delu-admin@test.dev';
const DU_TARGET = 'delu-target@test.dev';
const DU_NORMAL = 'delu-normal@test.dev';
const DU_USER   = 'delutarget';

test('delete_otp_cache_key is stable', delete_otp_cache_key(DU_ADMIN, DU_TARGET) === delete_otp_cache_key(DU_ADMIN, DU_TARGET));

delete_otp_clear(DU_ADMIN, DU_TARGET);
test('no OTP requested yet', delete_otp_load(DU_ADMIN, DU_TARGET) === null);
test('check with no entry fails', delete_otp_check(null, '123456')['ok'] === false);

delete_otp_store(DU_ADMIN, DU_TARGET, '123456');
$entry = delete_otp_load(DU_ADMIN, DU_TARGET);
test('OTP stored and loads', is_array($entry) && !empty($entry['otp_hash']) && !empty($entry['expires']));
test('correct OTP accepted', delete_otp_check($entry, '123456')['ok'] === true);
test('wrong OTP rejected', delete_otp_check($entry, '000000')['ok'] === false && str_contains((string)delete_otp_check($entry, '000000')['error'], 'Incorrect'));
test('expired OTP rejected', delete_otp_check([
    'otp_hash' => password_hash('123456', PASSWORD_DEFAULT),
    'expires'  => time() - 5,
], '123456')['ok'] === false);
delete_otp_clear(DU_ADMIN, DU_TARGET);
test('OTP cleared', delete_otp_load(DU_ADMIN, DU_TARGET) === null);

test('ud_array flattens BSON into PHP arrays', ud_array(new MongoDB\Model\BSONArray(['a' => 1])) === ['a' => 1]);
test('ud_array keeps string keys', ud_array(new MongoDB\Model\BSONDocument(['x' => 2])) === ['x' => 2]);
test('ud_array on plain array', ud_array(['x']) === ['x']);
test('ud_array on scalar yields []', ud_array('nope') === []);

echo "\n=== Admin delete user: source wiring ===\n";

$uvSrc = file_get_contents(SRC_PATH . '/template/pages/admin/user_view.php');
test('user_view has Danger Zone card', str_contains($uvSrc, 'Danger Zone'));
test('user_view has delete modal', str_contains($uvSrc, 'uvDeleteModal'));
test('user_view sends typed DELETE phrase to API', str_contains($uvSrc, "confirm: typed"));
test('user_view requires 6-digit OTP', str_contains($uvSrc, '/^\d{6}$/') || str_contains($uvSrc, '\d{6}'));

$delApi = file_get_contents(SRC_PATH . '/api/admin/delete_user.php');
test('delete API enforces typed phrase', str_contains($delApi, 'hash_equals') && str_contains($delApi, "'DELETE '"));
test('delete API requires admin + CSRF', str_contains($delApi, 'requireAdmin') && str_contains($delApi, 'requireCsrf'));

$resApi = file_get_contents(SRC_PATH . '/api/admin/restore_user.php');
test('restore API enforces typed phrase', str_contains($resApi, 'hash_equals') && str_contains($resApi, "'RESTORE '"));

$profileUtils = file_get_contents(SRC_PATH . '/utils/profile.php');
test('profile_resolve serves deleted snapshots to admins', str_contains($profileUtils, 'deleted_users') && str_contains($profileUtils, 'is_deleted_snapshot'));
$profileTpl = file_get_contents(SRC_PATH . '/template/pages/profile.php');
test('profile shows deleted-account banner', str_contains($profileTpl, 'Deleted account'));

$htaccess = file_get_contents(PROJECT_ROOT . '/htdocs/.htaccess');
test('.htaccess routes admin/deleted-users', str_contains($htaccess, 'admin/deleted-users'));

$ratelimit = file_get_contents(SRC_PATH . '/utils/ratelimit.php');
test('rate limit rule for delete_user exists', str_contains($ratelimit, 'admin:rl:delete_user'));

$mailerSrc = file_get_contents(SRC_PATH . '/lib/core/Auth/Mailer.class.php');
test('Mailer has sendDeleteUserOtp', str_contains($mailerSrc, 'sendDeleteUserOtp'));

$navSrc = file_get_contents(SRC_PATH . '/template/_nav.php');
test('nav has Deleted Users entry', str_contains($navSrc, 'Deleted Users'));

echo "\n=== Admin delete user: fixtures ===\n";

$db      = DatabaseConnection::getDefaultDatabase();
$instDb  = DatabaseConnection::getClient()->selectDatabase('tom_labs_instances_db');
$emails  = [DU_ADMIN, DU_TARGET, DU_NORMAL];
$regex   = ['/^delu-/'];

$du_cleanup = static function () use ($db, $instDb): void {
    foreach ([$db->users, $db->user_stats, $db->transactions, $db->machine_labs,
              $db->devices, $db->ip_registry, $db->user_activity, $db->domains,
              $db->session_tokens, $db->audit_log] as $coll) {
        try { $coll->deleteMany(['email' => ['$regex' => '^delu-']]); } catch (Throwable $e) {}
    }
    try { $db->audit_log->deleteMany(['entity_id' => ['$regex' => '^delu-']]); } catch (Throwable $e) {}
    try { $db->transactions->deleteMany(['user_email' => ['$regex' => '^delu-']]); } catch (Throwable $e) {}
    try { $db->deleted_users->deleteMany(['email' => ['$regex' => '^delu-']]); } catch (Throwable $e) {}
    try { $instDb->instances->deleteMany(['email' => ['$regex' => '^delu-']]); } catch (Throwable $e) {}
    foreach ([DU_ADMIN, DU_TARGET, DU_NORMAL] as $em) {
        $home = '/var/tomlabs/storage/' . md5($em);
        $bak  = '/var/tomlabs/storage/.deleted/' . md5($em);
        foreach ([$home, $bak] as $d) {
            if (is_dir($d)) {
                @shell_exec('rm -rf ' . escapeshellarg($d));
            }
        }
        delete_otp_clear(DU_ADMIN, $em);
    }
};

$du_cleanup();

$uid = 987001;
$db->users->insertOne([
    'email' => DU_TARGET, 'username' => DU_USER, 'user_id' => $uid,
    'role' => 'user', 'state' => 'active', 'is_verified' => true,
    'first_name' => 'Del', 'last_name' => 'Target',
    'created_at' => time() - 86400, 'last_login' => time() - 3600,
    'ip_address_v4' => '198.51.100.7', 'ip_address_v6' => '2001:db8::77',
    'zeal_stats' => ['zeal' => 4200, 'jolt' => 30],
    'password' => password_hash('x', PASSWORD_DEFAULT),
]);
$db->user_stats->insertOne(['email' => DU_TARGET, 'user_id' => $uid, 'zeal' => 4200, 'jolt' => 30, 'updated_at' => time()]);
$db->transactions->insertOne(['user_email' => DU_TARGET, 'user_id' => (string)$uid, 'username' => DU_USER,
    'direction' => 'add', 'currency' => 'zeal', 'amount' => 100, 'type' => 'Admin Adjustment',
    'reason' => 'seed credit', 'actor' => DU_ADMIN, 'created_at' => time() - 7200]);
$db->machine_labs->insertOne(['instance_hash' => 'deluhash1', 'email' => DU_TARGET, 'username' => DU_USER,
    'user_id' => $uid, 'lab_type' => 'docker_lab', 'status' => 'running', 'internal_ip' => '10.20.0.5', 'created_at' => time() - 5000]);
$db->devices->insertOne(['user_id' => $uid, 'email' => DU_TARGET, 'device_name' => 'MacBook',
    'assigned_ip' => '10.99.0.4', 'status' => 'active', 'created_at' => time() - 4000]);
$db->ip_registry->insertOne(['ip_addr' => '10.99.0.4', 'status' => 'reserved', 'email' => DU_TARGET,
    'user_id' => $uid, 'reserved_to' => DU_USER, 'reserved_at' => time() - 3900]);
$db->user_activity->insertOne(['user_id' => $uid, 'email' => DU_TARGET, 'page' => '/home',
    'hour' => 10, 'date' => date('Y-m-d'), 'timestamp' => time() - 600]);
$db->domains->insertOne(['user_id' => $uid, 'email' => DU_TARGET, 'domain' => 'delu.example.com',
    'status' => 'active', 'created_at' => time() - 3000]);
$instDb->instances->insertOne(['instance_hash' => 'deluinst1', 'user_id' => $uid, 'username' => DU_USER,
    'email' => DU_TARGET, 'name' => 'My Box', 'slug' => 'my-box', 'status' => 'running',
    'visibility' => 'private', 'type' => 'vm', 'created_at' => new MongoDB\BSON\UTCDateTime()]);

$duHome = '/var/tomlabs/storage/' . md5(DU_TARGET);
@shell_exec('rm -rf ' . escapeshellarg($duHome));
mkdir($duHome . '/home/' . DU_USER, 0755, true);
file_put_contents($duHome . '/home/' . DU_USER . '/notes.txt', 'important work');

test('target fixture created', $db->users->countDocuments(['email' => DU_TARGET]) === 1);
test('home fixture created', is_file($duHome . '/home/' . DU_USER . '/notes.txt'));

echo "\n=== Admin delete user: HTTP guards ===\n";

/* ---------------------------------------------------------- HTTP helpers */

function du_cookies_parse(string $header): array
{
    $jar = [];
    foreach (explode(';', $header) as $pair) {
        $pair = trim($pair);
        if ($pair !== '' && str_contains($pair, '=')) {
            [$k, $v] = explode('=', $pair, 2);
            $jar[trim($k)] = trim($v);
        }
    }
    return $jar;
}

function du_cookie_str(array $jar): string
{
    $parts = [];
    foreach ($jar as $k => $v) {
        $parts[] = $k . '=' . $v;
    }
    return implode('; ', $parts);
}

/** One HTTP round-trip that keeps cookies alive and returns jar + response. */
function du_req(string $method, string $path, array $opts = []): array
{
    $ch = curl_init(TEST_BASE_URL . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $headers = ['Host: ' . TEST_HOST_HEADER];
    if (!empty($opts['jar'])) {
        $headers[] = 'Cookie: ' . du_cookie_str($opts['jar']);
    }
    if (!empty($opts['csrf'])) {
        $headers[] = 'X-CSRF-Token: ' . $opts['csrf'];
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    if (isset($opts['body'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($opts['body']) ? http_build_query($opts['body']) : $opts['body']);
    }
    $raw   = curl_exec($ch);
    $code  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hs    = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    $head = substr((string)$raw, 0, $hs);
    $body = substr((string)$raw, $hs);
    $jar  = $opts['jar'] ?? [];
    if (preg_match_all('/^Set-Cookie:\s*([^=\s]+)=([^;]*)/mi', $head, $m, PREG_SET_ORDER)) {
        foreach ($m as $c) {
            $jar[$c[1]] = $c[2];
        }
    }
    $loc = '';
    if (preg_match('/^Location:\s*(.+)$/mi', $head, $lm)) {
        $loc = trim($lm[1]);
    }
    return ['status' => $code, 'body' => $body, 'json' => json_decode($body, true),
            'jar' => $jar, 'location' => $loc];
}

/** Fresh admin session + CSRF for one immediate mutating call (proven pattern). */
function du_admin_csrf(string $token, string $path = '/admin/deleted-users'): array
{
    $r = du_req('GET', $path, ['jar' => du_cookies_parse('session_token=' . $token)]);
    $csrf = null;
    if (preg_match('/<meta name="csrf-token" content="([^"]+)"/', $r['body'], $m)) {
        $csrf = $m[1];
    }
    return ['jar' => $r['jar'], 'csrf' => $csrf];
}

function du_admin_post(string $token, string $path, array $body): array
{
    $s = du_admin_csrf($token);
    return du_req('POST', $path, ['jar' => $s['jar'], 'csrf' => $s['csrf'], 'body' => $body]);
}

// Clear this target's rate-limit bucket so re-runs never trip 429.
$rlDir   = is_dir('/dev/shm/ratelimit_actions') ? '/dev/shm/ratelimit_actions' : '/tmp/ratelimit_actions';
$rlKey   = md5('admin:rl:delete_user:' . md5(strtolower(DU_TARGET)));
foreach (glob($rlDir . '/' . $rlKey . '_*.count') ?: [] as $f) {
    @unlink($f);
}

$adminToken  = create_test_user(DU_ADMIN, 'superuser');
$normalToken = create_test_user(DU_NORMAL, 'user');

// Missing CSRF → 403
$r = du_req('POST', '/api/admin/delete_user_request', [
    'jar'  => du_cookies_parse('session_token=' . $adminToken),
    'body' => ['email' => DU_TARGET],
]);
test('delete request without CSRF → 403', $r['status'] === 403);

// Non-admin → 403 on every delete/restore endpoint
$s = du_admin_csrf($normalToken, '/home');
foreach ([['/api/admin/delete_user_request', ['email' => DU_TARGET]],
          ['/api/admin/delete_user', ['email' => DU_TARGET, 'otp' => '123456', 'confirm' => 'DELETE ' . DU_TARGET]],
          ['/api/admin/restore_user', ['id' => '0123456789abcdef01234567', 'confirm' => 'RESTORE x']]] as [$p, $b]) {
    $r = du_req('POST', $p, ['jar' => $s['jar'], 'csrf' => $s['csrf'], 'body' => $b]);
    test("non-admin blocked on $p", $r['status'] === 403);
}

// Non-admin page access → redirected away
$r = du_req('GET', '/admin/deleted-users', ['jar' => du_cookies_parse('session_token=' . $normalToken)]);
test('non-admin page → redirect', $r['status'] === 302);
$r = du_req('GET', '/admin/deleted-users');
test('anon page → signin', $r['status'] === 302 && str_contains($r['location'], '/signin'));

// Self-delete guard
$r = du_admin_post($adminToken, '/api/admin/delete_user_request', ['email' => DU_ADMIN]);
test('self-delete blocked', ($r['json']['error'] ?? '') !== '' && str_contains((string)$r['json']['error'], 'own account'));

// Unknown target
$r = du_admin_post($adminToken, '/api/admin/delete_user_request', ['email' => 'nobody-here@test.dev']);
test('unknown target rejected', ($r['json']['error'] ?? '') === 'User not found');

// Trigger: SMTP may be unavailable locally — accept both outcomes, but the
// endpoint must always answer JSON (never a 500). Retry once on curl-level
// network failures so a slow SMTP connect can never flake the suite.
$r = ['status' => -1, 'json' => null];
for ($i = 0; $i < 2 && !is_array($r['json']); $i++) {
    $r = du_admin_post($adminToken, '/api/admin/delete_user_request', ['email' => DU_TARGET]);
    if ($r['status'] === 0) {
        usleep(300000);
    }
}
test('delete trigger answers JSON', in_array($r['status'], [200, 400, 403], true) && is_array($r['json']), 'status=' . $r['status']);

echo "\n=== Admin delete user: OTP confirmation + backup ===\n";

// Deterministic OTP regardless of SMTP (mirrors what the mailed code does).
delete_otp_store(DU_ADMIN, DU_TARGET, '123456');

$r = du_admin_post($adminToken, '/api/admin/delete_user', [
    'email' => DU_TARGET, 'otp' => '123456', 'confirm' => 'DELETE wrong@example.com',
]);
test('wrong typed phrase rejected', str_contains((string)($r['json']['error'] ?? ''), 'Type exactly'));

$r = du_admin_post($adminToken, '/api/admin/delete_user', [
    'email' => DU_TARGET, 'otp' => '654321', 'confirm' => 'DELETE ' . DU_TARGET,
]);
test('wrong OTP rejected', str_contains((string)($r['json']['error'] ?? ''), 'Incorrect'));

// Expire the code, then try again with the right digits.
$expired = delete_otp_load(DU_ADMIN, DU_TARGET);
$expired['expires'] = time() - 5;
Cache::set(delete_otp_cache_key(DU_ADMIN, DU_TARGET), $expired);
$r = du_admin_post($adminToken, '/api/admin/delete_user', [
    'email' => DU_TARGET, 'otp' => '123456', 'confirm' => 'DELETE ' . DU_TARGET,
]);
test('expired OTP rejected', str_contains((string)($r['json']['error'] ?? ''), 'expired'));

// Real delete.
delete_otp_store(DU_ADMIN, DU_TARGET, '123456');
$r = du_admin_post($adminToken, '/api/admin/delete_user', [
    'email' => DU_TARGET, 'otp' => '123456', 'confirm' => 'DELETE ' . DU_TARGET,
]);
test('delete succeeds', ($r['json']['status'] ?? '') === 'success', json_encode($r['json'] ?? null));
$snapshotId = (string)($r['json']['id'] ?? '');
test('snapshot id returned', preg_match('/^[a-f0-9]{24}$/', $snapshotId) === 1);

echo "\n=== Admin delete user: purge + snapshot ===\n";

test('users doc purged', $db->users->countDocuments(['email' => DU_TARGET]) === 0);
test('user_stats purged', $db->user_stats->countDocuments(['email' => DU_TARGET]) === 0);
test('machine_labs purged', $db->machine_labs->countDocuments(['email' => DU_TARGET]) === 0);
test('devices purged', $db->devices->countDocuments(['email' => DU_TARGET]) === 0);
test('ip_registry purged', $db->ip_registry->countDocuments(['email' => DU_TARGET]) === 0);
test('user_activity purged', $db->user_activity->countDocuments(['email' => DU_TARGET]) === 0);
test('domains purged', $db->domains->countDocuments(['email' => DU_TARGET]) === 0);
test('transactions purged', $db->transactions->countDocuments(['user_email' => DU_TARGET]) === 0);
test('instances purged', $instDb->instances->countDocuments(['email' => DU_TARGET]) === 0);

$rec = $db->deleted_users->findOne(['_id' => new MongoDB\BSON\ObjectId($snapshotId)]);
test('snapshot stored', $rec !== null);
test('snapshot status deleted', (string)($rec['status'] ?? '') === 'deleted');
test('snapshot keeps user doc', ($rec['snapshot']['user']['username'] ?? '') === DU_USER);
test('snapshot keeps user_stats', count(ud_array($rec['snapshot']['collections']['user_stats'] ?? [])) === 1);
test('snapshot keeps machine_labs', count(ud_array($rec['snapshot']['collections']['machine_labs'] ?? [])) === 1);
test('snapshot summary zeal', (int)($rec['snapshot']['summary']['zeal'] ?? 0) === 4200);
test('home folder moved aside', !is_dir($duHome) && is_dir('/var/tomlabs/storage/.deleted/' . md5(DU_TARGET)));
test('home backup keeps files', is_file('/var/tomlabs/storage/.deleted/' . md5(DU_TARGET) . '/home/' . DU_USER . '/notes.txt'));
test('audit trail recorded delete', $db->audit_log->countDocuments(['action' => 'delete_user', 'entity_id' => DU_TARGET]) >= 1);

echo "\n=== Admin delete user: viewers ===\n";

$r = du_req('GET', '/admin/deleted-users', ['jar' => du_cookies_parse('session_token=' . $adminToken)]);
test('list page 200', $r['status'] === 200);
test('list shows archived email', str_contains($r['body'], DU_TARGET));

$r = du_req('GET', '/admin/deleted-users/' . $snapshotId, ['jar' => du_cookies_parse('session_token=' . $adminToken)]);
test('detail page 200', $r['status'] === 200);
test('detail shows restore action', str_contains($r['body'], 'Return Back'));
test('detail renders ledger section', str_contains($r['body'], 'Currency ledger'));
test('detail renders seeded transaction', str_contains($r['body'], 'seed credit'));
test('detail renders points tile', str_contains($r['body'], '4,200'));

$r = du_req('GET', '/admin/user/' . rawurlencode(DU_TARGET), ['jar' => du_cookies_parse('session_token=' . $adminToken)]);
test('deleted user view redirects to snapshot', $r['status'] === 302 && str_contains($r['location'], '/admin/deleted-users/'));

$r = du_req('GET', '/' . DU_USER, ['jar' => du_cookies_parse('session_token=' . $adminToken)]);
test('admin sees public snapshot banner', $r['status'] === 200 && str_contains($r['body'], 'Deleted account'));

$r = du_req('GET', '/' . DU_USER, ['jar' => du_cookies_parse('session_token=' . $normalToken)]);
test('normal user gets no snapshot', !str_contains($r['body'], 'Deleted account') && !str_contains($r['body'], DU_TARGET));

$r = du_req('GET', '/' . DU_USER);
test('anon never sees snapshot', $r['status'] === 302 && str_contains($r['location'], '/signin'));

echo "\n=== Admin delete user: restore ===\n";

$r = du_admin_post($adminToken, '/api/admin/restore_user', [
    'id' => $snapshotId, 'confirm' => 'RESTORE wrong@example.com',
]);
test('restore wrong phrase rejected', str_contains((string)($r['json']['error'] ?? ''), 'Type exactly'));

$r = du_admin_post($adminToken, '/api/admin/restore_user', [
    'id' => $snapshotId, 'confirm' => 'RESTORE ' . DU_TARGET,
]);
test('restore succeeds', ($r['json']['status'] ?? '') === 'success', json_encode($r['json'] ?? null));

test('user doc back', $db->users->countDocuments(['email' => DU_TARGET]) === 1);
test('user_stats back', $db->user_stats->countDocuments(['email' => DU_TARGET]) === 1);
test('machine_labs back', $db->machine_labs->countDocuments(['email' => DU_TARGET]) === 1);
test('devices back', $db->devices->countDocuments(['email' => DU_TARGET]) === 1);
test('ip_registry back', $db->ip_registry->countDocuments(['email' => DU_TARGET]) === 1);
test('user_activity back', $db->user_activity->countDocuments(['email' => DU_TARGET]) === 1);
test('domains back', $db->domains->countDocuments(['email' => DU_TARGET]) === 1);
test('transactions back', $db->transactions->countDocuments(['user_email' => DU_TARGET]) === 1);
test('instances back', $instDb->instances->countDocuments(['email' => DU_TARGET]) === 1);
test('zeal preserved', (int)($db->user_stats->findOne(['email' => DU_TARGET])['zeal'] ?? 0) === 4200);
test('home folder back', is_file($duHome . '/home/' . DU_USER . '/notes.txt'));
test('snapshot marked restored', (string)($db->deleted_users->findOne(['_id' => new MongoDB\BSON\ObjectId($snapshotId)])['status'] ?? '') === 'restored');

$r = du_admin_post($adminToken, '/api/admin/restore_user', [
    'id' => $snapshotId, 'confirm' => 'RESTORE ' . DU_TARGET,
]);
test('double restore blocked', str_contains((string)($r['json']['error'] ?? ''), 'already been restored'));

// A second snapshot for a live account must refuse to restore (reuse guard).
$fakeId = (string)$db->deleted_users->insertOne([
    'email' => DU_TARGET, 'username' => DU_USER, 'user_id' => $uid, 'status' => 'deleted',
    'deleted_at' => time(), 'deleted_by' => DU_ADMIN, 'deleted_by_id' => 'x',
    'snapshot' => ['user' => $db->users->findOne(['email' => DU_TARGET]),
                   'collections' => [], 'array_refs' => [], 'files' => [], 'summary' => []],
])->getInsertedId();
$r = user_delete_restore($fakeId, DU_ADMIN, 'adm');
test('restore blocked while email is reused', ($r['status'] ?? '') === 'error' && str_contains((string)($r['error'] ?? ''), 'live account'));
test('failed restore leaves user intact', $db->users->countDocuments(['email' => DU_TARGET]) === 1);
$db->deleted_users->deleteOne(['_id' => new MongoDB\BSON\ObjectId($fakeId)]);

// Public profile returns to normal after restore.
$r = du_req('GET', '/' . DU_USER);
test('profile normal again after restore', $r['status'] === 200 && !str_contains($r['body'], 'Deleted account'));

echo "\n=== Admin delete user: cleanup ===\n";

$du_cleanup();
cleanup_test_user(DU_ADMIN);
cleanup_test_user(DU_NORMAL);
test('fixtures cleaned', $db->users->countDocuments(['email' => ['$regex' => '^delu-']]) === 0);
test('snapshots cleaned', $db->deleted_users->countDocuments(['email' => ['$regex' => '^delu-']]) === 0);
test('storage cleaned', !is_dir($duHome) && !is_dir('/var/tomlabs/storage/.deleted/' . md5(DU_TARGET)));

test_summary();
