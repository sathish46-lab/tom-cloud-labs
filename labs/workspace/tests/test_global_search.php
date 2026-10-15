<?php
/**
 * Test: Global search palette (/api/search).
 *
 * REAL RUNTIME TEST against the API:
 *   1. SearchMatch unit — multi-token advanced matching ladder
 *   2. "My Device" still suggests device matches (partial-token rank)
 *   3. Admin features group appears ONLY for superusers; /admin never
 *      leaks to regular users
 *   4. Exact matches rank above partial matches
 */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../htdocs/src/lib/core/SearchMatch.class.php';

/* ── 1. Unit: tokenize ── */
test('tokenize splits, lowercases, dedupes', SearchMatch::tokenize('My  Device my') === ['my', 'device']);
test('tokenize empty', SearchMatch::tokenize('   ') === []);

/* ── 1b. Unit: scoring ladder ── */
$t1 = SearchMatch::tokenize('my device');
test('exact scores 0', SearchMatch::score('my device', 'my device', $t1) === 0);
test('prefix scores 10', SearchMatch::score('devices page', 'device', SearchMatch::tokenize('device')) === 10);
test('contiguous scores 20', SearchMatch::score('the my device hub', 'my device', $t1) === 20);
test('scattered word-start scores 30', SearchMatch::score('lab hub device', 'hub lab', SearchMatch::tokenize('hub lab')) === 30);
test('scattered non-boundary scores 40', SearchMatch::score('mydevice hub', 'my device', $t1) === 40);
test('partial token still matches (your ask)', SearchMatch::score('devices', 'my device', $t1) === 49);
test('partial ranks below every full match', SearchMatch::score('devices', 'my device', $t1) > SearchMatch::score('my device hub', 'my device', $t1));
test('no shared token → null', SearchMatch::score('machine labs', 'device', SearchMatch::tokenize('device')) === null);
test('sortItems sorts ascending and strips _score', (function () {
    $out = SearchMatch::sortItems([['_score' => 40, 'label' => 'b'], ['_score' => 0, 'label' => 'a'], ['label' => 'c']]);
    return $out[0]['label'] === 'a' && $out[1]['label'] === 'b' && !isset($out[0]['_score']) && $out[2]['label'] === 'c';
})());

/* ── 2. API: real users ── */
$regEmail = 'gsearch_regular@test.tomweb.in';
$admEmail = 'gsearch_admin@test.tomweb.in';
$regToken = create_test_user($regEmail, 'user');
$admToken = create_test_user($admEmail, 'superuser');

$api = function (string $token, string $q) {
    return http_request('GET', '/api/search?q=' . urlencode($q), ['cookie' => "session_token=$token"]);
};

$flatten = function ($groups): array {
    $labels = [];
    foreach ((array)($groups ?? []) as $rows) {
        foreach ((array)$rows as $row) {
            $labels[] = $row['label'] ?? '';
        }
    }
    return $labels;
};

/* "My Device" must still suggest the Devices page (partial token) */
$r = $api($regToken, 'My Device');
$j = $r['body_json'];
test('API success', ($j['result'] ?? '') === 'success', substr($r['body'], 0, 200));
$labels = $flatten($j['groups'] ?? []);
test('"My Device" suggests Devices page', in_array('Devices', $labels, true), implode(', ', $labels));
test('regular user: no admin group', !isset(($j['groups'] ?? [])['admin']));

/* Regular user must never see admin URLs */
$r = $api($regToken, 'admin');
$j = $r['body_json'];
test('regular "admin" → success', ($j['result'] ?? '') === 'success', substr($r['body'], 0, 200));
test('regular user: no /admin/ URL leaks', stripos($r['body'], '/admin/') === false, substr($r['body'], 0, 300));
test('regular user: no admin group for "admin"', !isset(($j['groups'] ?? [])['admin']));

/* Superuser gets the admin group, scoped and correct */
$r = $api($admToken, 'modules');
$j = $r['body_json'];
$adminRows = ($j['groups'] ?? [])['admin'] ?? [];
test('admin "modules" → admin group', count($adminRows) > 0, json_encode(array_column($adminRows, 'label')));
test('admin group first item = Modules → /admin/modules',
    ($adminRows[0]['label'] ?? '') === 'Modules' && ($adminRows[0]['href'] ?? '') === '/admin/modules',
    json_encode($adminRows[0] ?? null));

$r = $api($admToken, 'admin');
$j = $r['body_json'];
$adminRows = ($j['groups'] ?? [])['admin'] ?? [];
test('admin "admin" → admin group non-empty', count($adminRows) > 0, json_encode(array_column($adminRows, 'label')));
test('admin "admin" → has Users', in_array('Users', array_column($adminRows, 'label'), true));

/* Ranking: exact match first */
$r = $api($regToken, 'Devices');
$j = $r['body_json'];
$apps = ($j['groups'] ?? [])['apps'] ?? [];
test('exact "Devices" ranks first in apps', ($apps[0]['label'] ?? '') === 'Devices', json_encode(array_column($apps, 'label')));

/* Escaping: regex metacharacters must not blow up */
$r = $api($regToken, 'My (Device) * [x]');
test('regex chars handled safely', ($r['body_json']['result'] ?? '') === 'success', substr($r['body'], 0, 200));

cleanup_test_user($regEmail);
cleanup_test_user($admEmail);

test_summary();
