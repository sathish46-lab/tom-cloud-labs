<?php
/**
 * Test: Admin → Settings → Appearance page.
 *
 * REAL RUNTIME TEST — renders the admin page, exercises the save API and
 * asserts the saved policy actually reaches the rendered app:
 *   1. Page reachable by superuser, redirected otherwise
 *   2. Save API requires superuser + CSRF, validates input
 *   3. Locked / hidden / forced-color settings show up in the page HTML
 *
 * Usage:
 *   php workspace/tests/test_admin_appearance.php
 */

require_once __DIR__ . '/bootstrap.php';

echo "=== Admin Appearance Tests ===\n\n";

$controller  = PROJECT_ROOT . '/htdocs/app/admin/appearance.php';
$saveApi     = SRC_PATH . '/api/admin/appearance_save.php';
$htaccessSrc = file_get_contents(PROJECT_ROOT . '/htdocs/.htaccess');
$navSrc      = file_get_contents(SRC_PATH . '/template/_nav.php');
$masterSrc   = file_get_contents(SRC_PATH . '/template/_master.php');
$siteNavSrc  = file_get_contents(SRC_PATH . '/template/_sitenav.php');
$changeBgSrc = file_get_contents(SRC_PATH . '/api/user/change_bg.php');
$appJs       = file_get_contents(PROJECT_ROOT . '/htdocs/assets/js/app.js');

/** Collapse whitespace so assertions survive source reformatting. */
function flat(string $s): string {
    return preg_replace('/\s+/', ' ', $s);
}

/** Mongo hands back BSONArray for array fields — normalize to a PHP list. */
function list_of($v): array {
    if ($v instanceof Traversable) return array_values(iterator_to_array($v, false));
    return is_array($v) ? array_values($v) : [];
}

// ── Static wiring ──
echo "--- Wiring ---\n";

test("controller app/admin/appearance.php exists", file_exists($controller));
test("page template pages/admin/appearance.php exists", file_exists(SRC_PATH . '/template/pages/admin/appearance.php'));
test("save API src/api/admin/appearance_save.php exists", file_exists($saveApi));
test("Appearance helper class exists", file_exists(SRC_PATH . '/lib/core/Appearance.class.php'));
test(".htaccess routes /admin/appearance", strpos($htaccessSrc, 'RewriteRule ^admin/appearance/?$') !== false);
test("sidebar links to /admin/appearance", strpos($navSrc, '/admin/appearance') !== false);
test("controller enforces admin", strpos(file_get_contents($controller), 'AuthMiddleware::isAdmin()') !== false);
test("save API enforces admin", strpos(file_get_contents($saveApi), 'AuthMiddleware::requireAdmin()') !== false);
test("save API enforces CSRF", strpos(file_get_contents($saveApi), 'AuthMiddleware::requireCsrf()') !== false);

echo "\n--- Enforcement Hooks ---\n";

test("_master.php resolves mode through Appearance::resolveMode", substr_count($masterSrc, 'Appearance::resolveMode(') >= 2);
test("_master.php emits FORCED_BG_MODE", strpos($masterSrc, 'window.FORCED_BG_MODE') !== false);
test("_master.php emits DISABLED_BG_MODES", strpos($masterSrc, 'window.DISABLED_BG_MODES') !== false);
test("_master.php emits FORCED_COLOR_MODE", strpos($masterSrc, 'window.FORCED_COLOR_MODE') !== false);
test("_master.php honors locked color mode in the rendered theme",
    strpos($masterSrc, "force_color_mode'] !== '' ? \$appearance['force_color_mode']") !== false);
test("_sitenav.php hides the picker when the background is locked", strpos($siteNavSrc, '$appearanceLocked') !== false);
test("_sitenav.php filters hidden backgrounds from the picker", strpos($siteNavSrc, 'in_array($id, $appearanceDisabled, true)') !== false);
test("change_bg.php filters hidden templates", strpos($changeBgSrc, "disabled_modes") !== false);
test("app.js blocks picking a locked/hidden background", strpos(flat($appJs), 'window.DISABLED_BG_MODES && window.DISABLED_BG_MODES.indexOf(mode)') !== false);
test("app.js blocks a locked color mode", strpos(flat($appJs), 'window.FORCED_COLOR_MODE && themeName !== window.FORCED_COLOR_MODE') !== false);

// ── Runtime: access control ──
echo "\n--- Access Control ---\n";

$db = DatabaseConnection::getDefaultDatabase();
$db->global_settings->deleteOne(['_id' => 'appearance']);

$adminEmail = 'appearance_admin_' . time() . '@example.com';
$userEmail  = 'appearance_user_' . time() . '@example.com';
$adminToken = create_test_user($adminEmail, 'superuser');
$userToken  = create_test_user($userEmail, 'user');

// The CSRF token lives in the PHP session, so the session cookie has to travel
// with every request that must satisfy requireCsrf().
function session_cookie(string $sessionToken, string $path = '/dashboard'): array {
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

$admin = session_cookie($adminToken);
$user  = session_cookie($userToken);

$resp = http_request('GET', '/admin/appearance', ['cookie' => $admin['cookie']]);
test("Superuser gets 200 on /admin/appearance", $resp['status'] === 200, 'status=' . $resp['status']);
test("Page shows the Appearance banner", strpos($resp['body'], 'Appearance') !== false);
test("Page lists every background as the default choice",
    strpos($resp['body'], 'value="spiderman"') !== false && strpos($resp['body'], 'Spidey') !== false);

$resp = http_request('GET', '/admin/appearance', ['cookie' => $user['cookie']]);
test("Normal user is redirected away", in_array($resp['status'], [301, 302, 403], true), 'status=' . $resp['status']);

$resp = http_request('GET', '/admin/appearance');
test("Anonymous visitor is sent to sign in",
    in_array($resp['status'], [301, 302], true)
    && isset($resp['headers']['Location'])
    && strpos($resp['headers']['Location'], '/signin') !== false,
    'status=' . $resp['status'] . ' loc=' . ($resp['headers']['Location'] ?? ''));

$resp = http_request('GET', '/dashboard', ['cookie' => $admin['cookie']]);
test("Superuser sidebar shows the Appearance link", strpos($resp['body'], 'href="/admin/appearance"') !== false);

test("CSRF token available for save API", $admin['csrf'] !== null);

// ── Rendered layout ──
echo "\n--- Rendered Layout ---\n";

$page = http_request('GET', '/admin/appearance', ['cookie' => $admin['cookie']])['body'];
$marker = strpos($page, '<!-- ── Page action bar ──');
$bar = $marker === false ? '' : substr($page, $marker, strpos($page, '<script>', $marker) - $marker);
test("Action bar markup present", $marker !== false);
test("Last saved, Reset and Save share one card",
    strpos($bar, 'class="card ') !== false
    && strpos($bar, 'id="apUpdated"') !== false
    && strpos($bar, 'id="apResetBtn"') !== false
    && strpos($bar, 'id="apSaveBtn"') !== false,
    'marker=' . var_export($marker, true));
test("Action controls are not duplicated elsewhere on the page",
    substr_count($page, 'id="apSaveBtn"') === 1 && substr_count($page, 'id="apUpdated"') === 1);
test("Page container leaves bottom space above the footer",
    strpos($page, '<div class="container-fluid px-4 pb-4">') !== false);

$save = function (array $fields, string $cookie, ?string $csrf = null) {
    $headers = [];
    if ($csrf !== null) $headers[] = 'X-CSRF-Token: ' . $csrf;
    return http_request('POST', '/api/admin/appearance_save', [
        'cookie'  => $cookie,
        'headers' => $headers,
        'body'    => $fields,
    ]);
};

// ── Save API ──
echo "\n--- Save API ---\n";

$resp = $save([
    'default_mode'     => 'spiderman',
    'lock_bg'          => '1',
    'force_mode'       => 'robotower',
    'force_color_mode' => 'dark',
    'disabled_modes'   => ['robo', 'ninja', 'robotower'],
], $admin['cookie'], $admin['csrf']);
$json = json_decode($resp['body'], true);
test("Save succeeds", ($json['status'] ?? '') === 'success', $resp['body']);

$doc = (array)$db->global_settings->findOne(['_id' => 'appearance']);
test("Default background persisted", ($doc['default_mode'] ?? '') === 'spiderman', var_export($doc['default_mode'] ?? null, true));
test("Locked background persisted", ($doc['force_mode'] ?? '') === 'robotower', var_export($doc['force_mode'] ?? null, true));
test("Locked color mode persisted", ($doc['force_color_mode'] ?? '') === 'dark');
test("Hidden backgrounds persisted", list_of($doc['disabled_modes'] ?? []) === ['robo', 'ninja'], json_encode(list_of($doc['disabled_modes'] ?? [])));
test("Locked background is never hidden", !in_array('robotower', list_of($doc['disabled_modes'] ?? []), true));

$resp = $save(['default_mode' => 'does-not-exist'], $admin['cookie'], $admin['csrf']);
$json = json_decode($resp['body'], true);
test("Unknown default background rejected", ($json['status'] ?? '') === 'error' && stripos($resp['body'], 'Unknown default background') !== false, $resp['body']);

$resp = $save(['default_mode' => 'robo', 'disabled_modes' => ['robo']], $admin['cookie'], $admin['csrf']);
$json = json_decode($resp['body'], true);
test("Default background cannot be hidden", ($json['status'] ?? '') === 'error' && stripos($resp['body'], 'cannot be hidden') !== false, $resp['body']);

$resp = $save(['default_mode' => 'robo', 'force_color_mode' => 'neon'], $admin['cookie'], $admin['csrf']);
$json = json_decode($resp['body'], true);
test("Invalid color mode rejected", ($json['status'] ?? '') === 'error' && stripos($resp['body'], 'Color mode') !== false, $resp['body']);

$resp = $save(['default_mode' => 'spiderman'], $admin['cookie'], null);
test("Save without CSRF token is forbidden", $resp['status'] === 403, 'status=' . $resp['status']);

$resp = $save(['default_mode' => 'spiderman'], $user['cookie'], $admin['csrf']);
test("Save by non-admin is forbidden", $resp['status'] === 403, 'status=' . $resp['status']);

$doc = (array)$db->global_settings->findOne(['_id' => 'appearance']);
test("Failed saves left the settings untouched",
    ($doc['default_mode'] ?? '') === 'spiderman' && ($doc['force_mode'] ?? '') === 'robotower');

// ── Rendered effect of the saved policy ──
echo "\n--- Rendered Policy ---\n";

$db->users->updateOne(['email' => $userEmail], ['$set' => ['theme_preferences' => ['mode' => 'robo', 'theme' => 'dark']]]);
$render = http_request('GET', '/dashboard', ['cookie' => $user['cookie']]);
$html = flat($render['body']);
$layerRegex = '#<div class="bg-cover bg-img-(\d+)"#';
$layerCount = preg_match_all($layerRegex, $render['body']);
test("Locked background overrides the user's own pick (robotower = 5 layers)", $layerCount === 5, 'layers=' . $layerCount);
test("Page announces FORCED_BG_MODE", strpos($html, 'window.FORCED_BG_MODE = "robotower"') !== false);
test("Page announces DISABLED_BG_MODES", strpos($html, 'window.DISABLED_BG_MODES = ["robo","ninja"]') !== false);
test("Page announces FORCED_COLOR_MODE", strpos($html, 'window.FORCED_COLOR_MODE = "dark"') !== false);
test("Locked background picker is gone from the sidebar", strpos($render['body'], "TomBG.setMode('robo')") === false);

$resp = http_request('GET', '/api/user/change_bg', ['cookie' => $user['cookie']]);
test("Background dialog only offers the locked background",
    strpos($resp['body'], 'data-mode="robo"') === false
    && strpos($resp['body'], 'data-mode="robotower"') !== false,
    'robo present: ' . var_export(strpos($resp['body'], 'data-mode="robo"') !== false, true));

// hidden (but not locked) mode disappears from the picker while the user's own choice still works
$resp = $save([
    'default_mode'     => 'spiderman',
    'force_color_mode' => '',
    'disabled_modes'   => ['robo'],
], $admin['cookie'], $admin['csrf']);
test("Unlocking the background succeeds", (json_decode($resp['body'], true)['status'] ?? '') === 'success', $resp['body']);
$doc = (array)$db->global_settings->findOne(['_id' => 'appearance']);
test("Unlocking the background clears force_mode", ($doc['force_mode'] ?? 'x') === '', var_export($doc['force_mode'] ?? null, true));

$render = http_request('GET', '/dashboard', ['cookie' => $user['cookie']]);
$html = flat($render['body']);
test("No forced background when unlocked", strpos($html, 'window.FORCED_BG_MODE = null') !== false);
test("Hidden background still filtered from the picker", strpos($render['body'], "TomBG.setMode('robo')") === false);
test("Visible backgrounds still rendered", strpos($render['body'], "TomBG.setMode('spiderman')") !== false);

// forced light color mode wins over the user's dark preference
$resp = $save(['default_mode' => 'spiderman', 'force_color_mode' => 'light', 'disabled_modes' => []], $admin['cookie'], $admin['csrf']);
test("Saving a locked color mode succeeds", (json_decode($resp['body'], true)['status'] ?? '') === 'success', $resp['body']);
$render = http_request('GET', '/dashboard', ['cookie' => $user['cookie']]);
$html = flat($render['body']);
test("Locked color mode forces light theme in markup", strpos($render['body'], 'data-coreui-theme="light"') !== false);
test("Locked color mode announced to JS", strpos($html, 'window.FORCED_COLOR_MODE = "light"') !== false);

// ── Reset ──
echo "\n--- Reset ---\n";

$resp = $save(['reset' => '1'], $admin['cookie'], $admin['csrf']);
test("Reset succeeds", (json_decode($resp['body'], true)['status'] ?? '') === 'success', $resp['body']);
$doc = (array)$db->global_settings->findOne(['_id' => 'appearance']);
test("Reset restores defaults",
    ($doc['default_mode'] ?? '') === 'spiderman'
    && ($doc['force_mode'] ?? '') === ''
    && ($doc['force_color_mode'] ?? '') === ''
    && list_of($doc['disabled_modes'] ?? []) === [],
    json_encode($doc));

$render = http_request('GET', '/dashboard', ['cookie' => $user['cookie']]);
$userLayers = preg_match_all($layerRegex, $render['body']);
test("User's own background choice is honored again (robo = 3 layers)", $userLayers === 3, 'layers=' . $userLayers);
test("Picker lists every background again", strpos($render['body'], "TomBG.setMode('robo')") !== false);

// ── Browser encoding: the page sends repeated disabled_modes[] keys ──
echo "\n--- Multi-select Save ---\n";

$raw = 'default_mode=spiderman&disabled_modes[]=robo&disabled_modes[]=ninja&disabled_modes[]=ironman';
$resp = http_request('POST', '/api/admin/appearance_save', [
    'cookie'  => $admin['cookie'],
    'headers' => ['X-CSRF-Token: ' . $admin['csrf'], 'Content-Type: application/x-www-form-urlencoded'],
    'body'    => $raw,
]);
$json = json_decode($resp['body'], true);
test("Multi-select save succeeds", ($json['status'] ?? '') === 'success', $resp['body']);
$doc = (array)$db->global_settings->findOne(['_id' => 'appearance']);
test("Every checked background is hidden, not just the last one",
    list_of($doc['disabled_modes'] ?? []) === ['robo', 'ninja', 'ironman'],
    json_encode(list_of($doc['disabled_modes'] ?? [])));

$render = http_request('GET', '/dashboard', ['cookie' => $user['cookie']]);
foreach (['robo', 'ninja', 'ironman'] as $hidden) {
    test("Picker hides $hidden", strpos($render['body'], 'data-mode="' . $hidden . '"') === false);
}
foreach (['robotower', 'spiderman'] as $visible) {
    test("Picker still shows $visible", strpos($render['body'], 'data-mode="' . $visible . '"') !== false);
}

// leave global state clean for the rest of the suite
$db->global_settings->deleteOne(['_id' => 'appearance']);
cleanup_test_user($adminEmail);
cleanup_test_user($userEmail);

test_summary();
