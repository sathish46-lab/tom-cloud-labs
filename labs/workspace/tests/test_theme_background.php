<?php
/**
 * Test: Background image (parallax scene) rendering + appearance preferences.
 *
 * REAL RUNTIME TEST — Renders an authenticated page and asserts:
 *   1. One parallax layer per theme asset (no 4-layer cap)
 *   2. Layer assets are actually served
 *   3. Light/Dark/Auto preference persists server-side and renders
 *   4. The dead/broken appearance controls are gone
 *
 * Usage:
 *   php workspace/tests/test_theme_background.php
 */

require_once __DIR__ . '/bootstrap.php';

echo "=== Background Image / Appearance Tests ===\n\n";

$masterPath  = SRC_PATH . '/template/_master.php';
$savePath    = SRC_PATH . '/api/account/theme_save.php';
$modalPath   = SRC_PATH . '/template/partials/_account_settings_modal.php';
$appJsPath   = PROJECT_ROOT . '/htdocs/assets/js/app.js';
$cssPath     = PROJECT_ROOT . '/htdocs/assets/css/app.css';

// ── Static source checks ──
echo "--- Template / Client Source ---\n";

$masterSrc = file_get_contents($masterPath);
test("_master.php emits layers from a loop (no fixed 4-layer markup)",
    strpos($masterSrc, 'foreach (array_values($assets)') !== false);
test("_master.php no hard-coded 4th layer div",
    strpos($masterSrc, 'bg-img-4" data-depth="0.1"') === false);
test("_master.php falls back to server theme when localStorage is empty",
    strpos($masterSrc, "localStorage.getItem('tom-labs-theme') || serverTheme.theme") !== false);

$saveSrc = file_get_contents($savePath);
test("theme_save writes theme_preferences.theme", strpos($saveSrc, 'theme_preferences.theme') !== false);
test("theme_save whitelists light/dark/auto", strpos($saveSrc, "['light', 'dark', 'auto']") !== false);

$appJs = file_get_contents($appJsPath);
test("app.js creates missing parallax layers", strpos($appJs, 'while (layers.length < assets.length)') !== false);
test("app.js changeTheme persists theme to server",
    strpos($appJs, "window.changeTheme = function") !== false
    && substr_count($appJs, "'/api/account/theme_save'") >= 3);

$modalSrc = file_get_contents($modalPath);
test("account settings no longer links to missing /theme/editor",
    strpos($modalSrc, 'href="/theme/editor"') === false);
test("account settings opens background modal directly",
    strpos($modalSrc, "new coreui.Modal(document.getElementById('bgSelectModal'))") !== false);

echo "\n--- Assets / Dead Code ---\n";

test("orphan endpoint get_themes.php removed", !file_exists(SRC_PATH . '/api/user/get_themes.php'));
test("orphan endpoint change_bg_new.php removed", !file_exists(SRC_PATH . '/api/user/change_bg_new.php'));
test("carbon-fibre texture served locally",
    file_exists(PROJECT_ROOT . '/htdocs/assets/img/patterns/carbon-fibre.png'));
test("app.css has no external texture dependency",
    strpos(file_get_contents($cssPath), 'transparenttextures.com') === false);

// ── Runtime: authenticated render ──
echo "\n--- Runtime: Scene Rendering ---\n";

$testEmail = 'theme_bg_' . time() . '@example.com';
$sessionToken = create_test_user($testEmail, 'user');
$db = DatabaseConnection::getDefaultDatabase();

$GLOBALS['layerRegex'] = '#<div class="bg-cover bg-img-(\d+)" data-depth="([\d.]+)" style="background-image: url\(\'([^\']+)\'\); display: block;"></div>#';

function fetch_layers(string $sessionToken): array {
    $resp = http_request('GET', '/dashboard', ['cookie' => "session_token=$sessionToken"]);
    $layers = [];
    if (preg_match_all($GLOBALS['layerRegex'], $resp['body'], $m, PREG_SET_ORDER)) {
        foreach ($m as $match) {
            $layers[] = ['index' => (int)$match[1], 'depth' => (float)$match[2], 'src' => $match[3]];
        }
    }
    return ['status' => $resp['status'], 'body' => $resp['body'], 'layers' => $layers];
}

// robotower declares 5 assets — the old markup silently dropped the 5th
$db->users->updateOne(['email' => $testEmail], ['$set' => ['theme_preferences.mode' => 'robotower']]);
$render = fetch_layers($sessionToken);
test("Dashboard renders authenticated", $render['status'] === 200, 'status=' . $render['status']);
test("RoboTower renders all 5 parallax layers", count($render['layers']) === 5,
    'got ' . count($render['layers']));

if (count($render['layers']) === 5) {
    $depths = array_column($render['layers'], 'depth');
    $strictlyDecreasing = true;
    for ($i = 1; $i < count($depths); $i++) {
        if ($depths[$i] >= $depths[$i - 1]) $strictlyDecreasing = false;
    }
    test("Layer depths decrease front-to-back (0.8 … 0.05)", $strictlyDecreasing, implode(',', $depths));
    test("5th layer is RoboTower/4.png", $render['layers'][4]['src'] === '/assets/Background_Img/RoboTower/4.png',
        $render['layers'][4]['src'] ?? 'missing');

    foreach ($render['layers'] as $layer) {
        $assetResp = http_request('GET', $layer['src']);
        test("Asset served: " . $layer['src'], $assetResp['status'] === 200, 'status=' . $assetResp['status']);
    }
}

// spiderman declares 2 assets → exactly 2 layers, no empty placeholders
$db->users->updateOne(['email' => $testEmail], ['$set' => ['theme_preferences.mode' => 'spiderman']]);
$render = fetch_layers($sessionToken);
test("Spiderman renders exactly 2 parallax layers", count($render['layers']) === 2,
    'got ' . count($render['layers']));

// plain mode → scene hidden and empty
$db->users->updateOne(['email' => $testEmail], ['$set' => ['theme_preferences.mode' => 'plain']]);
$render = fetch_layers($sessionToken);
test("Plain mode renders 0 parallax layers", count($render['layers']) === 0,
    'got ' . count($render['layers']));
test("Plain mode keeps an empty #scene for JS switching", strpos($render['body'], '<div id="scene"') !== false);

// ── Runtime: light/dark/auto persistence ──
echo "\n--- Runtime: Color Mode Persistence ---\n";

$db->users->updateOne(['email' => $testEmail], ['$set' => ['theme_preferences.theme' => 'dark']]);

$resp = http_request('POST', '/api/account/theme_save', [
    'cookie'   => "session_token=$sessionToken",
    'headers'  => ['Content-Type: application/json'],
    'body'     => json_encode(['theme' => 'light']),
]);
$respJson = json_decode($resp['body'], true);
test("theme_save accepts theme=light", ($respJson['status'] ?? '') === 'success', $resp['body']);
$userDoc = $db->users->findOne(['email' => $testEmail]);
test("theme_preferences.theme persisted as light",
    ($userDoc['theme_preferences']['theme'] ?? null) === 'light',
    var_export($userDoc['theme_preferences']['theme'] ?? null, true));

$render = fetch_layers($sessionToken);
test("Page renders data-coreui-theme=\"light\"",
    strpos($render['body'], 'data-coreui-theme="light"') !== false);

$resp = http_request('POST', '/api/account/theme_save', [
    'cookie'   => "session_token=$sessionToken",
    'headers'  => ['Content-Type: application/json'],
    'body'     => json_encode(['theme' => 'neon-zebra']),
]);
$userDoc = $db->users->findOne(['email' => $testEmail]);
test("Invalid theme value rejected (still light)",
    ($userDoc['theme_preferences']['theme'] ?? null) === 'light',
    var_export($userDoc['theme_preferences']['theme'] ?? null, true));

$resp = http_request('POST', '/api/account/theme_save', [
    'cookie'   => "session_token=$sessionToken",
    'headers'  => ['Content-Type: application/json'],
    'body'     => json_encode(['theme' => 'auto']),
]);
$userDoc = $db->users->findOne(['email' => $testEmail]);
test("theme=auto persisted", ($userDoc['theme_preferences']['theme'] ?? null) === 'auto');

// mode still saves alongside theme (regression guard)
$resp = http_request('POST', '/api/account/theme_save', [
    'cookie'   => "session_token=$sessionToken",
    'headers'  => ['Content-Type: application/json'],
    'body'     => json_encode(['mode' => 'robo', 'theme' => 'dark']),
]);
$userDoc = $db->users->findOne(['email' => $testEmail]);
test("mode + theme save together",
    ($userDoc['theme_preferences']['mode'] ?? null) === 'robo'
    && ($userDoc['theme_preferences']['theme'] ?? null) === 'dark');

cleanup_test_user($testEmail);

test_summary();
