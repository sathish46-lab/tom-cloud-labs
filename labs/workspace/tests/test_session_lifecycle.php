<?php
/**
 * Session lifecycle hardening — REAL RUNTIME tests (real HTTP + real DB):
 *
 *  A. Login convergence: all PHPSESSID variants carry ONE id; /home stable;
 *     re-login never grows session_tokens (pipeline replaces old token).
 *  B. Absolute 30-day session cap (auth_since) + PHP-session idle timeout.
 *  C. Remember-token idle timeout (24h) + token 30-day absolute cap; revocation.
 *  D. OTP attempt limit: 5 wrong codes invalidate the code.
 *  E. Auth boundary controls: guest / bogus token → 401.
 *
 * Rate limits (keyed per email): POST /signin 5/900s, verify_login_2fa 5/300s.
 * Every part uses a fresh unique email so buckets never collide.
 */

require_once __DIR__ . '/bootstrap.php';

const TL_PASS = 'TestPassword123!';

/** Raw HTTP helper: keeps ALL Set-Cookie values in emission order. */
function sess_raw(string $method, string $path, array $opts = []): array {
    $ch = curl_init(TEST_BASE_URL . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT        => 20,
    ]);
    $headers = $opts['headers'] ?? [];
    $headers[] = 'Host: ' . TEST_HOST_HEADER;
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    if (isset($opts['body'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS,
            is_array($opts['body']) ? http_build_query($opts['body']) : $opts['body']);
    }
    if (isset($opts['cookie'])) {
        curl_setopt($ch, CURLOPT_COOKIE, $opts['cookie']);
    }
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hs = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return ['status' => 0, 'set' => [], 'set_all' => [], 'body' => '', 'body_json' => null, 'error' => $err];
    }
    $hdr = substr($raw, 0, $hs);
    $body = substr($raw, $hs);
    preg_match_all('/^Set-Cookie:\s*([^=;\r\n]+)=([^;\r\n]*)/mi', $hdr, $mm, PREG_SET_ORDER);
    $set = [];
    $setAll = [];
    foreach ($mm as $c) {
        $name = trim($c[1]);
        $val = $c[2];
        $set[$name] = $val;
        $setAll[$name][] = $val;
    }
    return [
        'status'    => $status,
        'set'       => $set,      // last value per name = what a browser keeps
        'set_all'   => $setAll,   // every emission, in order
        'body'      => $body,
        'body_json' => json_decode($body, true),
    ];
}

function cookie_str(array $jar): string {
    $parts = [];
    foreach ($jar as $k => $v) {
        $parts[] = $k . '=' . $v;
    }
    return implode('; ', $parts);
}

function jar_merge(array &$jar, array $resp): void {
    foreach ($resp['set'] as $k => $v) {
        if ($v === '' || $v === 'deleted') {
            unset($jar[$k]);
        } else {
            $jar[$k] = $v;
        }
    }
}

function tl_user(string $suffix, array $extra = []): string {
    $email = 'sesslife_' . $suffix . '_' . uniqid('', true) . '@example.com';
    create_test_user($email);
    $db = DatabaseConnection::getDefaultDatabase();
    $db->users->updateOne(['email' => $email], ['$set' => array_merge(
        ['is_verified' => true],
        $extra
    )]);
    return $email;
}

function do_login(string $email, array &$jar, bool $remember = false): array {
    $g = sess_raw('GET', '/signin', ['cookie' => cookie_str($jar)]);
    jar_merge($jar, $g);
    if (!preg_match('/name="_csrf_token" value="([^"]+)"/', $g['body'], $m)) {
        return ['ok' => false, 'status' => $g['status'], 'why' => 'no csrf'];
    }
    $post = sess_raw('POST', '/signin', [
        'cookie' => cookie_str($jar),
        'body'   => [
            'email'        => $email,
            'password'     => TL_PASS,
            '_csrf_token'  => $m[1],
            'remember'     => $remember ? '1' : '',
        ],
    ]);
    jar_merge($jar, $post);
    return [
        'ok'      => $post['status'] === 302,
        'status'  => $post['status'],
        'set'     => $post['set'],       // browser-final (last write wins)
        'set_all' => $post['set_all'],   // every emission, in order
        'resp'    => $post,
        'body'    => $post['body'],
    ];
}

function session_file(): string {
    $dir = preg_replace('/^\d+;/', '', (string) session_save_path());
    return rtrim($dir !== '' ? $dir : '/var/cache/labs/sessions', '/');
}

function edit_session_int(string $sid, string $key, int $val): bool {
    $f = session_file() . '/sess_' . $sid;
    if (!is_file($f)) {
        return false;
    }
    $c = (string) file_get_contents($f);
    $c = preg_replace('/' . preg_quote($key, '/') . '\|i:\d+;/', '', $c, 1);
    return file_put_contents($f, $key . '|i:' . $val . ';' . $c) !== false;
}

function token_count(string $email): int {
    $db = DatabaseConnection::getDefaultDatabase();
    $u = $db->users->findOne(['email' => $email], ['session_tokens' => 1]);
    return count($u['session_tokens'] ?? []);
}

$api = '/api/account/settings';

// ════════════════════════════════════════════════════════
// E first (cheap controls): auth boundary
// ════════════════════════════════════════════════════════
echo "--- E: auth boundary ---\n";
$guest = sess_raw('GET', $api);
test('E1 guest API call is 401', $guest['status'] === 401, 'status=' . $guest['status']);

$bogus = sess_raw('GET', $api, ['cookie' => 'session_token=' . str_repeat('ab', 32)]);
test('E2 bogus remember-token is 401', $bogus['status'] === 401, 'status=' . $bogus['status']);

// ════════════════════════════════════════════════════════
// A: login convergence + stability + token hygiene
// ════════════════════════════════════════════════════════
echo "\n--- A: login convergence + token hygiene ---\n";
$ua = tl_user('a');
$jar = [];
$r = do_login($ua, $jar, true);
test('A1 login redirects 302', $r['ok'], 'status=' . ($r['status'] ?? '?') . ' ' . ($r['why'] ?? ''));

$ids = array_values(array_unique($r['set_all']['PHPSESSID'] ?? []));
test('A2 all PHPSESSID variants carry ONE id', count($ids) === 1 && $ids[0] !== '', json_encode($ids));
test('A3 session_token cookie issued (64 hex)',
    isset($r['set']['session_token']) && preg_match('/^[0-9a-f]{64}$/', $r['set']['session_token']) === 1);

$apiOk = sess_raw('GET', $api, ['cookie' => cookie_str($jar)]);
test('A4 logged-in session auths API (200)', $apiOk['status'] === 200, 'status=' . $apiOk['status']);

$h1 = sess_raw('GET', '/home', ['cookie' => cookie_str($jar)]);
jar_merge($jar, $h1);
$h2 = sess_raw('GET', '/home', ['cookie' => cookie_str($jar)]);
jar_merge($jar, $h2);
// /home intentionally rotates the id each hit (SessionAuth::rotateSessionId).
// What must hold is convergence: the browser-final id is re-emitted for BOTH
// cookie scopes (host-only + Domain), so the variants can never diverge.
$hv1 = $h1['set_all']['PHPSESSID'] ?? [];
$hv2 = $h2['set_all']['PHPSESSID'] ?? [];
$hf1 = $hv1 !== [] ? end($hv1) : '';
$hf2 = $hv2 !== [] ? end($hv2) : '';
test('A5 /home rotation ends converged (final id emitted for both cookie scopes)',
    $hf1 !== '' && count(array_keys($hv1, $hf1)) >= 2
    && $hf2 !== '' && count(array_keys($hv2, $hf2)) >= 2,
    json_encode([$hv1, $hv2]));
test('A6 /home rotates PHPSESSID on every hit (final id differs each time)',
    $hf1 !== ($ids[0] ?? '') && $hf2 !== '' && $hf2 !== $hf1,
    json_encode([$ids, $hf1, $hf2]));

$cnt1 = token_count($ua);
$sidBefore = $jar['PHPSESSID'] ?? '';
$r2 = do_login($ua, $jar, true);
test('A7 re-login with same browser succeeds (302)', $r2['ok'], 'status=' . ($r2['status'] ?? '?'));
$cnt2 = token_count($ua);
test('A8 re-login does not grow session_tokens', $cnt2 === $cnt1, "$cnt1 -> $cnt2");
// The login response may re-emit the old id before the regenerated one
// (pre-auth refresh runs before the login handler) — what matters is the
// LAST write per cookie scope, which a browser always applies last.
$r2vals = $r2['set_all']['PHPSESSID'] ?? [];
test('A9 re-login last PHPSESSID write is the NEW id (last wins)',
    count($r2vals) > 0 && end($r2vals) !== $sidBefore && end($r2vals) === ($jar['PHPSESSID'] ?? ''),
    json_encode([$sidBefore, $r2vals, $jar['PHPSESSID'] ?? null]));
$apiOk2 = sess_raw('GET', $api, ['cookie' => cookie_str($jar)]);
$apiIds = array_values(array_unique($apiOk2['set_all']['PHPSESSID'] ?? []));
test('A10 post-relogin request: authed 200 + ONE converged id',
    $apiOk2['status'] === 200 && count($apiIds) === 1 && $apiIds[0] === ($jar['PHPSESSID'] ?? ''),
    'status=' . $apiOk2['status'] . ' ids=' . json_encode($apiIds));

// ════════════════════════════════════════════════════════
// B: absolute 30-day cap + PHP-session idle
// ════════════════════════════════════════════════════════
echo "\n--- B: absolute cap + session idle ---\n";
$ub = tl_user('b');
$jarB = [];
$rb = do_login($ub, $jarB);
test('B1 baseline login succeeds', $rb['ok']);
$apiB = sess_raw('GET', $api, ['cookie' => cookie_str($jarB)]);
test('B2 baseline session is authed (200)', $apiB['status'] === 200, 'status=' . $apiB['status']);

$sidB = $jarB['PHPSESSID'] ?? '';
$edited = $sidB !== '' && edit_session_int($sidB, 'auth_since', time() - 31 * 86400);
test('B3 session file editable (auth_since injection)', $edited, 'sid=' . $sidB);
if ($edited) {
    $cap = sess_raw('GET', $api, ['cookie' => cookie_str($jarB)]);
    test('B4 absolute 30-day cap logs session out (401)', $cap['status'] === 401, 'status=' . $cap['status']);
}

$uc = tl_user('c');
$jarC = [];
$rc = do_login($uc, $jarC);
test('B5 baseline login succeeds (idle part)', $rc['ok']);
$sidC = $jarC['PHPSESSID'] ?? '';
$editedC = $sidC !== '' && edit_session_int($sidC, 'last_activity', time() - 90000);
test('B6 session file editable (last_activity injection)', $editedC, 'sid=' . $sidC);
if ($editedC) {
    $idle = sess_raw('GET', $api, ['cookie' => cookie_str($jarC)]);
    test('B7 PHP-session idle timeout logs out (401)', $idle['status'] === 401, 'status=' . $idle['status']);
}

// ════════════════════════════════════════════════════════
// C: remember-token idle + absolute cap
// ════════════════════════════════════════════════════════
echo "\n--- C: token idle + token absolute cap ---\n";
$ud = tl_user('d');
$jarD = [];
$rd = do_login($ud, $jarD, true);
test('C1 login with remember succeeds', $rd['ok']);
$tokD = $jarD['session_token'] ?? '';
test('C2 remember token captured', strlen($tokD) === 64, 'len=' . strlen($tokD));

$db = DatabaseConnection::getDefaultDatabase();
if ($tokD !== '') {
    $upd = $db->users->updateOne(
        ['email' => $ud, 'session_tokens.token_id' => hash('sha256', $tokD)],
        ['$set' => ['session_tokens.$.last_activity' => time() - 90000]]
    );
    test('C3 token last_activity aged in DB', $upd->getMatchedCount() === 1);

    $tokOnly = sess_raw('GET', $api, ['cookie' => 'session_token=' . $tokD]);
    test('C4 idle remember-token is rejected (401)', $tokOnly['status'] === 401, 'status=' . $tokOnly['status']);

    $after = $db->users->findOne(['email' => $ud, 'session_tokens.token_id' => hash('sha256', $tokD)]);
    test('C5 idle token revoked from DB', $after === null);
}

$ue = tl_user('e');
$jarE = [];
$re = do_login($ue, $jarE, true);
test('C6 login with remember succeeds (cap part)', $re['ok']);
$tokE = $jarE['session_token'] ?? '';
if ($tokE !== '') {
    $updE = $db->users->updateOne(
        ['email' => $ue, 'session_tokens.token_id' => hash('sha256', $tokE)],
        ['$set' => ['session_tokens.$.created_at' => time() - 31 * 86400]]
    );
    test('C7 token created_at aged 31 days in DB', $updE->getMatchedCount() === 1);

    $capTok = sess_raw('GET', $api, ['cookie' => 'session_token=' . $tokE]);
    test('C8 token older than 30 days rejected (401)', $capTok['status'] === 401, 'status=' . $capTok['status']);
}

// ════════════════════════════════════════════════════════
// D: OTP attempt limit
// ════════════════════════════════════════════════════════
echo "\n--- D: OTP attempt limit ---\n";

// D-happy: correct OTP completes login
$uf = tl_user('f', ['two_factor_enabled' => true]);
$jarF = [];
$gF = sess_raw('GET', '/signin', ['cookie' => cookie_str($jarF)]);
jar_merge($jarF, $gF);
preg_match('/name="_csrf_token" value="([^"]+)"/', $gF['body'], $mF);
$loginF = sess_raw('POST', '/signin', [
    'cookie' => cookie_str($jarF),
    'body'   => ['email' => $uf, 'password' => TL_PASS, '_csrf_token' => $mF[1], 'remember' => ''],
]);
jar_merge($jarF, $loginF);
test('D1 2FA challenge rendered (200 + otp form)',
    $loginF['status'] === 200 && strpos($loginF['body'], 'name="otp"') !== false,
    'status=' . $loginF['status']);

$dbF = DatabaseConnection::getDefaultDatabase();
$seedF = $dbF->users->updateOne(['email' => $uf], ['$set' => [
    'two_factor_otp'       => password_hash('123456', PASSWORD_DEFAULT),
    'two_factor_expires'   => time() + 60,
    'two_factor_attempts'  => 0,
]]);
test('D2 seeded known OTP in DB', $seedF->getMatchedCount() === 1);

$vF = sess_raw('POST', '/api/auth/verify_login_2fa', [
    'cookie' => cookie_str($jarF),
    'body'   => ['otp' => '123456'],
]);
test('D3 correct OTP completes login (success)',
    ($vF['body_json']['status'] ?? '') === 'success', json_encode($vF['body_json']));
jar_merge($jarF, $vF); // login regenerated the session id — adopt it
$apiF = sess_raw('GET', $api, ['cookie' => cookie_str($jarF)]);
test('D4 OTP login session auths API (200)', $apiF['status'] === 200, 'status=' . $apiF['status']);

// D-limit: 5 wrong codes invalidate
$ug = tl_user('g', ['two_factor_enabled' => true]);
$jarG = [];
$gG = sess_raw('GET', '/signin', ['cookie' => cookie_str($jarG)]);
jar_merge($jarG, $gG);
preg_match('/name="_csrf_token" value="([^"]+)"/', $gG['body'], $mG);
$loginG = sess_raw('POST', '/signin', [
    'cookie' => cookie_str($jarG),
    'body'   => ['email' => $ug, 'password' => TL_PASS, '_csrf_token' => $mG[1], 'remember' => ''],
]);
jar_merge($jarG, $loginG);
test('D5 2FA challenge rendered (limit part)',
    $loginG['status'] === 200 && strpos($loginG['body'], 'name="otp"') !== false);

$dbG = DatabaseConnection::getDefaultDatabase();
$dbG->users->updateOne(['email' => $ug], ['$set' => [
    'two_factor_otp'      => password_hash('999999', PASSWORD_DEFAULT),
    'two_factor_expires'  => time() + 60,
    'two_factor_attempts' => 0,
]]);

$wrongMsgs = [];
for ($i = 1; $i <= 4; $i++) {
    $v = sess_raw('POST', '/api/auth/verify_login_2fa', [
        'cookie' => cookie_str($jarG),
        'body'   => ['otp' => sprintf('%06d', $i)],
    ]);
    $wrongMsgs[] = $v['body_json']['error'] ?? ('status=' . $v['status']);
}
test('D6 wrong OTP #1-#4 rejected with Incorrect OTP code',
    $wrongMsgs === array_fill(0, 4, 'Incorrect OTP code.'), json_encode($wrongMsgs));

$gAfter = $dbG->users->findOne(['email' => $ug], ['two_factor_attempts' => 1]);
test('D7 attempt counter incremented to 4', (int) ($gAfter['two_factor_attempts'] ?? -1) === 4,
    'attempts=' . ($gAfter['two_factor_attempts'] ?? 'unset'));

$v5 = sess_raw('POST', '/api/auth/verify_login_2fa', [
    'cookie' => cookie_str($jarG),
    'body'   => ['otp' => '000005'],
]);
test('D8 5th wrong OTP invalidates the code',
    strpos($v5['body_json']['error'] ?? '', 'Too many incorrect attempts') === 0,
    json_encode($v5['body_json']));

$gInvalid = $dbG->users->findOne(['email' => $ug]);
test('D9 OTP hash removed from DB after limit', !isset($gInvalid['two_factor_otp']));

$sessRaw = (string) @file_get_contents(session_file() . '/sess_' . ($jarG['PHPSESSID'] ?? ''));
test('D10 pending 2FA login cleared from session',
    $sessRaw !== '' && strpos($sessRaw, '2fa_pending_email') === false,
    $sessRaw === '' ? 'session file missing' : substr($sessRaw, 0, 80));

// ════════════════════════════════════════════════════════
// G: /home session-id rotation (stays logged in)
// ════════════════════════════════════════════════════════
echo "\n--- G: /home id rotation ---\n";
$uh = tl_user('h');
$jarH = [];
$rh = do_login($uh, $jarH, true);
test('G1 login with remember succeeds (rotation part)', $rh['ok']);
$sidOld = $jarH['PHPSESSID'] ?? '';
test('G2 pre-rotation sid captured', strlen($sidOld) === 32, 'sid=' . $sidOld);

$home1 = sess_raw('GET', '/home', ['cookie' => cookie_str($jarH)]);
$sidNew = $home1['set']['PHPSESSID'] ?? '';
test('G3 /home rotates PHPSESSID to a new id',
    $home1['status'] === 200 && $sidNew !== '' && $sidNew !== $sidOld,
    'status=' . $home1['status'] . ' old=' . $sidOld . ' new=' . $sidNew);
$variants = $home1['set_all']['PHPSESSID'] ?? [];
// Bootstrap refresh emits the pre-rotation id before home.php rotates; a
// browser overwrites those with the later same-scope writes. The invariant:
// the browser-final id is re-emitted for BOTH scopes (host-only + Domain).
test('G4 rotation ends converged (final id emitted for both cookie scopes)',
    $variants !== [] && count(array_keys($variants, $sidNew)) >= 2,
    json_encode($variants));
jar_merge($jarH, $home1);

$apiH = sess_raw('GET', $api, ['cookie' => cookie_str($jarH)]);
test('G5 still logged in after rotation (API 200)', $apiH['status'] === 200, 'status=' . $apiH['status']);

$home2 = sess_raw('GET', '/home', ['cookie' => cookie_str($jarH)]);
$sidNew2 = $home2['set']['PHPSESSID'] ?? '';
test('G6 next /home reload rotates again (new id each hit)',
    $home2['status'] === 200 && $sidNew2 !== '' && $sidNew2 !== $sidNew,
    'status=' . $home2['status'] . ' prev=' . $sidNew . ' now=' . $sidNew2);

$stale = sess_raw('GET', $api, ['cookie' => 'PHPSESSID=' . $sidOld]);
test('G7 old sid rejected after rotation (401, no session_token)',
    $stale['status'] === 401, 'status=' . $stale['status']);

// ════════════════════════════════════════════════════════
// Cleanup
// ════════════════════════════════════════════════════════
foreach ([$ua, $ub, $uc, $ud, $ue, $uf, $ug, $uh] as $e) {
    cleanup_test_user($e);
}

test_summary();
