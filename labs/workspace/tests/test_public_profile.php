<?php
/**
 * Test: Public (signed-out) profile pages.
 *
 * 1. Wiring — app/profile.php + _master.php + background.js agree on the lock
 * 2. Admin → Settings → Appearance offers the Public profile background card
 * 3. Save API persists and validates public_bg_mode
 * 4. Runtime — an anonymous visitor gets ONE wallpaper, no sidebar, no leak
 *
 * Usage (inside the container):
 *   php workspace/tests/test_public_profile.php
 */

require_once __DIR__ . '/bootstrap.php';

echo "=== Public Profile Background Tests ===\n\n";

$profileApp  = PROJECT_ROOT . '/htdocs/app/profile.php';
$masterSrc   = file_get_contents(SRC_PATH . '/template/_master.php');
$profileSrc  = file_get_contents(PROJECT_ROOT . '/htdocs/src/template/pages/profile.php');
$appearanceSrc = file_get_contents(SRC_PATH . '/template/pages/admin/appearance.php');
$saveApi     = file_get_contents(SRC_PATH . '/api/admin/appearance_save.php');
$bgJs        = file_get_contents(PROJECT_ROOT . '/workspace/js/background.js');
$appJs       = file_get_contents(PROJECT_ROOT . '/htdocs/assets/js/app.js');
$publicHdr   = file_get_contents(SRC_PATH . '/template/partials/_public_header.php');
$appearanceTplSrc = file_get_contents(SRC_PATH . '/template/pages/admin/appearance.php');

function flat_pub(string $s): string {
    return preg_replace('/\s+/', ' ', $s);
}

require_once SRC_PATH . '/lib/core/Appearance.class.php';

// ── Wiring ──
echo "--- Wiring ---\n";

test("profile entry app/profile.php exists", file_exists($profileApp));
test("public header partial exists", file_exists(SRC_PATH . '/template/partials/_public_header.php'));
test("profile entry flags the page public", strpos(file_get_contents($profileApp), "define('IS_PUBLIC_PAGE'") !== false);
test("profile entry sends unknown handles to /signin", strpos(file_get_contents($profileApp), "header('Location: /signin')") !== false);
test("profile entry hands the owner email to _master.php", strpos(file_get_contents($profileApp), "Session::set('profile_owner_email'") !== false);
test("_master.php resolves the public wallpaper", strpos($masterSrc, 'Appearance::resolvePublicMode(') !== false);
test("_master.php emits BG_MODE_LOCKED", strpos($masterSrc, 'window.BG_MODE_LOCKED') !== false);
test("_master.php stops writing localStorage on public pages",
    strpos(flat_pub($masterSrc), 'if (window.BG_MODE_LOCKED) {') !== false
    && strpos(flat_pub($masterSrc), 'else if (serverTheme && serverTheme.mode)') !== false);
test("background.js prefers the locked server mode",
    substr_count($bgJs, 'window.BG_MODE_LOCKED') >= 2);
test("background.js lock is bundled into app.js", strpos($appJs, 'BG_MODE_LOCKED') !== false);
test("public page skips the sidebar chrome", strpos($masterSrc, "defined('IS_PUBLIC_PAGE')") !== false);
test("public header offers a Sign in button", strpos($publicHdr, 'href="/signin"') !== false);

test("Appearance defaults public_bg_mode to War",
    Appearance::defaults()['public_bg_mode'] === 'ninja');
test("Appearance exposes the owner sentinel",
    Appearance::PUBLIC_OWNER === 'owner');

// ── Admin page ──
echo "\n--- Admin: Public profile background card ---\n";

test("Card block present", strpos($appearanceTplSrc, '── Public profile background ──') !== false);
test("Own card header with globe icon", strpos($appearanceTplSrc, 'bx-globe me-2') !== false);
test("Select present", strpos($appearanceTplSrc, 'id="apPublicBg"') !== false);
test("Profile owner's choice offered", strpos($appearanceTplSrc, 'value="<?= Appearance::PUBLIC_OWNER ?>"') !== false);
test("Site default offered", strpos($appearanceTplSrc, 'Same as the site default background') !== false);
test("Themes grouped separately", strpos($appearanceTplSrc, '<optgroup label="Always use">') !== false);
test("Hint element present", strpos($appearanceTplSrc, 'id="apPublicBgHint"') !== false);
test("Current choice badge present", strpos($appearanceTplSrc, 'id="apPublicBgBadge"') !== false);
test("Save payload carries public_bg_mode", strpos($appearanceTplSrc, "fd.append('public_bg_mode'") !== false);

// The control must live in its own card, not inside the Background card.
$firstCardEnd = strpos($appearanceTplSrc, '── Public profile background ──');
$defaultInFirstCard = strpos($appearanceTplSrc, 'id="apDefaultMode"');
test("Public block sits after the Background card",
    $defaultInFirstCard !== false && $firstCardEnd !== false && $defaultInFirstCard < $firstCardEnd);

// ── Save API ──
echo "\n--- Save API ---\n";

$db = DatabaseConnection::getDefaultDatabase();
$originalAppearance = $db->global_settings->findOne(['_id' => 'appearance']);

$adminEmail = 'pubprofile_admin_' . time() . '@example.com';
$adminToken = create_test_user($adminEmail, 'superuser');

function pub_session_cookie(string $sessionToken): array {
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

$admin = pub_session_cookie($adminToken);

$savePublic = function (array $fields) use ($admin) {
    $fields += [
        'default_mode'     => 'spiderman',
        'force_color_mode' => '',
        'public_bg_mode'   => 'ninja',
    ];
    return http_request('POST', '/api/admin/appearance_save', [
        'cookie'  => $admin['cookie'],
        'headers' => ['X-CSRF-Token: ' . $admin['csrf']],
        'body'    => $fields,
    ]);
};

$resp = $savePublic(['public_bg_mode' => 'owner']);
$data = json_decode($resp['body'], true) ?? [];
test("Saving public_bg_mode=owner succeeds", ($data['status'] ?? '') === 'success', $resp['body']);
Appearance::forget();
test("owner persisted", Appearance::get()['public_bg_mode'] === 'owner');

$resp = $savePublic(['public_bg_mode' => 'ironman']);
$data = json_decode($resp['body'], true) ?? [];
test("A fixed wallpaper is accepted", ($data['status'] ?? '') === 'success', $resp['body']);
Appearance::forget();
test("fixed wallpaper persisted", Appearance::get()['public_bg_mode'] === 'ironman');

$resp = $savePublic(['public_bg_mode' => '']);
$data = json_decode($resp['body'], true) ?? [];
test("Falling back to the site default is accepted", ($data['status'] ?? '') === 'success', $resp['body']);
Appearance::forget();
test("site default persisted as empty", Appearance::get()['public_bg_mode'] === '');

$resp = $savePublic(['public_bg_mode' => 'ghostwallpaper']);
$data = json_decode($resp['body'], true) ?? [];
test("Unknown wallpaper rejected", ($data['status'] ?? '') === 'error', $resp['body']);
Appearance::forget();
test("Settings untouched after a rejected save", Appearance::get()['public_bg_mode'] === '');

$resp = $savePublic(['public_bg_mode' => 'ninja']);

// ── Runtime: anonymous visitor ──
echo "\n--- Runtime: signed-out visitor ---\n";

$anon = http_request('GET', '/account');
test("/account redirects to sign in",
    in_array($anon['status'], [301, 302], true)
    && isset($anon['headers']['Location'])
    && strpos($anon['headers']['Location'], '/signin') !== false,
    'status=' . $anon['status']);

$anon = http_request('GET', '/no-such-user-xyz');
test("Unknown handle redirects to sign in",
    in_array($anon['status'], [301, 302], true)
    && strpos($anon['headers']['Location'] ?? '', '/signin') !== false,
    'status=' . $anon['status'] . ' loc=' . ($anon['headers']['Location'] ?? ''));

$pub = http_request('GET', '/sathish47');
$body = $pub['body'];
test("Public profile renders for an anonymous visitor", $pub['status'] === 200, 'status=' . $pub['status']);
test("Page announces the locked wallpaper", preg_match('/window\.BG_MODE_LOCKED\s*=\s*1/', $body) === 1);
test("Page resolves the War wallpaper", preg_match('/window\.RESOLVED_BG_MODE\s*=\s*"ninja"/', $body) === 1);
test("Scene paints the War layers",
    strpos($body, "/assets/Background_Img/ninja/0.png") !== false,
    'spiderman first paint?');
test("Scene does not paint Spidey first",
    strpos(substr($body, (int)strpos($body, 'id="scene"'), 600), 'spiderman') === false);
test("No sidebar for anonymous visitors", strpos($body, 'id="sidebar"') === false);
test("Sign in button offered", strpos($body, '>Sign in</a>') !== false);
test("No session-expired wall", strpos($body, 'Session Expired') === false);

$targetEmail = (string)($db->users->findOne(['username' => 'sathish47'], ['projection' => ['email' => 1]])['email'] ?? '');
test("Profile owner email is not leaked", $targetEmail !== '' && strpos($body, $targetEmail) === false);

// ── Runtime: admin picks a fixed wallpaper ──
echo "\n--- Runtime: fixed wallpaper ---\n";

$savePublic(['public_bg_mode' => 'ironman']);
$pub = http_request('GET', '/sathish47');
test("Public profile follows the admin's fixed wallpaper",
    strpos($pub['body'], '/assets/Background_Img/IronMan/0.jpg') !== false,
    'status=' . $pub['status']);
test("Still locked for anonymous visitors",
    preg_match('/window\.BG_MODE_LOCKED\s*=\s*1/', $pub['body']) === 1);

// ── Runtime: profile owner's own choice ──
echo "\n--- Runtime: profile owner's choice ---\n";

$savePublic(['public_bg_mode' => 'owner']);
$pub = http_request('GET', '/sathish47');
test("sathish47's own background (War) is shown",
    strpos($pub['body'], '/assets/Background_Img/ninja/0.png') !== false,
    'status=' . $pub['status']);

$pub = http_request('GET', '/testuser1');
test("A user without a saved background falls back to the site default",
    strpos($pub['body'], '/assets/Background_Img/spiderman/0.png') !== false,
    'status=' . $pub['status']);

// ── Restore ──
$savePublic(['public_bg_mode' => 'ninja']);
$db->global_settings->deleteOne(['_id' => 'appearance']);
if ($originalAppearance !== null) {
    $db->global_settings->insertOne((array)$originalAppearance);
}
Appearance::forget();
cleanup_test_user($adminEmail);

test("Appearance settings restored",
    Appearance::get()['public_bg_mode'] === ((string)($originalAppearance['public_bg_mode'] ?? 'ninja')));

test_summary();
