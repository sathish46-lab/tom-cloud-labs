<?php
/**
 * Test: Mastery Hall — League of Ronin rank ladder + Global Leaderboard.
 *
 * Covers the pure ladder maths (src/utils/leagues.php), the wiring
 * (.htaccess rules, sidebar group, palette entry) and both rendered pages.
 *
 * Usage:
 *   php workspace/tests/test_mastery_hall.php
 */

require_once __DIR__ . '/bootstrap.php';
require_once SRC_PATH . '/utils/leagues.php';

echo "=== Mastery Hall Tests ===\n\n";

// ── Ladder maths ──
echo "--- Ladder maths ---\n";

test('25 leagues', LEAGUE_COUNT === 25);
test('100 levels', LEAGUE_LEVEL_COUNT === 100);
test('4 levels per league', LEAGUE_LEVELS_PER_LEAGUE === 4);
test('25 league definitions', count(LEAGUES) === 25);
test('requirement of level 1 is 10', league_requirement(1) === 10);
test('requirement of level 10 is 1000', league_requirement(10) === 1000);
test('requirement of level 92 is 84640', league_requirement(92) === 84640);
test('requirement of level 93 is 86490', league_requirement(93) === 86490);
test('requirement of level 100 is 100000', league_requirement(100) === 100000);

test('level 1 starts league I', league_index_for_level(1) === 1);
test('level 4 ends league I', league_index_for_level(4) === 1);
test('level 5 starts league II', league_index_for_level(5) === 2);
test('level 91 sits in league XXIII', league_index_for_level(91) === 23);
test('level 100 sits in league XXV', league_index_for_level(100) === 25);
test('league XXV covers 97..100', league_bounds(25) === [97, 100]);

test('level 1 title', league_level_title(1) === 'Ninja Recruit I');
test('level 91 title', league_level_title(91) === 'Digital Ronin Supreme III');
test('level 92 title', league_level_title(92) === 'Digital Ronin Supreme IV');
test('level label', league_level_label(91) === 'League XXIII · Level 91');
test('level badge for 91 uses league 23 grade 3',
    league_avatar_url(91) === LEAGUE_AVATAR_BASE . '/league_23/2.jpg');
test('lock badge', league_lock_url() === LEAGUE_AVATAR_BASE . '/lock.jpg');

// 83,677 zeal — the worked example from the design
$zeal = 83677;
test('level 91 completed at 83677 zeal', league_level_state(91, $zeal)['status'] === 'completed');
$state92 = league_level_state(92, $zeal);
test('level 92 in progress', $state92['status'] === 'progress');
test('level 92 progress is 98%', $state92['percent'] === 98, (string)$state92['percent']);
test('level 92 progress text', $state92['text'] === 'Progress (83,677 / 84,640) 🔥', $state92['text']);
test('level 93 locked', league_level_state(93, $zeal)['status'] === 'locked');
test('locked level shows 0%', league_level_state(93, $zeal)['percent'] === 0);

$cur = league_for_zeal($zeal);
test('83677 zeal completes 91 levels', $cur['level'] === 91);
test('works towards level 92', $cur['next_level'] === 92);
test('current league is XXIII', $cur['league'] === 23 && $cur['league_roman'] === 'XXIII');
test('current league name', $cur['league_name'] === 'Digital Ronin Supreme');
test('progress to next level is 98%', $cur['progress'] === 98, (string)$cur['progress']);
test('not maxed', $cur['is_max'] === false);

$zero = league_for_zeal(0);
test('zero zeal completes no level', $zero['level'] === 0);
test('zero zeal still targets level 1', $zero['next_level'] === 1);
test('level 1 is progress at 0 zeal', league_level_state(1, 0)['status'] === 'progress');
test('level 2 is locked at 0 zeal', league_level_state(2, 0)['status'] === 'locked');

$maxed = league_for_zeal(100000);
test('100000 zeal completes level 100', $maxed['level'] === 100);
test('100000 zeal is maxed', $maxed['is_max'] === true);

$ladder = leagues_ladder($zeal);
test('ladder has 25 leagues', count($ladder) === 25);
test('ladder has 100 levels', array_sum(array_map('count', array_column($ladder, 'levels'))) === 100);
$onlyCurrent = array_values(array_filter($ladder, static fn ($l) => $l['is_current']));
test('exactly one current league', count($onlyCurrent) === 1);
test('current league is XXIII', $onlyCurrent[0]['roman'] ?? '' === 'XXIII');
test('first league is current when not reached',
    league_index_for_level(max(1, league_for_zeal(0)['next_level'])) === 1);
test('ladder levels carry badge images',
    str_starts_with($ladder[0]['levels'][0]['image'], LEAGUE_AVATAR_BASE));

// ── Wiring ──
echo "\n--- Wiring ---\n";

$htAccess = file_get_contents(PROJECT_ROOT . '/htdocs/.htaccess');
$navSrc   = file_get_contents(SRC_PATH . '/template/_nav.php');
$searchSrc = file_get_contents(SRC_PATH . '/api/search.php');

test('.htaccess routes /leaderboard-global',
    strpos($htAccess, 'RewriteRule ^leaderboard-global/?$') !== false);
test('.htaccess routes /leagues',
    strpos($htAccess, 'RewriteRule ^leagues/?$') !== false);
test('rules sit before the username catch-all',
    strpos($htAccess, 'RewriteRule ^leagues/?$') < strpos($htAccess, 'app/profile.php?username=$1'));

test('sidebar has Mastery Hall group', strpos($navSrc, 'Mastery Hall') !== false);
test('sidebar links /leaderboard-global', strpos($navSrc, 'href="/leaderboard-global"') !== false);
test('sidebar links /leagues', strpos($navSrc, 'href="/leagues"') !== false);
test('palette has League of Ronin', strpos($searchSrc, "'/leagues'") !== false);
test('palette keeps Global Leaderboard', strpos($searchSrc, "'/leaderboard-global'") !== false);

// ── Rendered pages ──
echo "\n--- Rendered pages ---\n";

$email = 'mastery_hall_' . time() . '@example.com';
$token = create_test_user($email);
$resp  = http_request('GET', '/dashboard', ['cookie' => 'session_token=' . $token]);
$sid = '';
if (isset($resp['headers']['Set-Cookie'])
    && preg_match('/PHPSESSID=([^;]+)/', $resp['headers']['Set-Cookie'], $m)) {
    $sid = $m[1];
}
$cookie = 'session_token=' . $token . ($sid !== '' ? '; PHPSESSID=' . $sid : '');

$leagues = http_request('GET', '/leagues', ['cookie' => $cookie]);
test('GET /leagues returns 200', ($leagues['status'] ?? 0) === 200, 'status=' . ($leagues['status'] ?? '?'));
$lb = $leagues['body'] ?? '';
test('leagues page renders the header', strpos($lb, 'League of Ronin') !== false);
test('leagues page renders the accordion', strpos($lb, 'id="leagueFlush"') !== false);
test('leagues page renders 25 accordion headers',
    substr_count($lb, 'accordion-header') === 25, (string)substr_count($lb, 'accordion-header'));
test('first league present', strpos($lb, 'Ninja Recruit') !== false);
test('last league present', strpos($lb, 'Cyber Knight Supreme') !== false);
test('level badge images point at the public bucket',
    strpos($lb, 'https://s3.selfmade.ninja/labassets/level_avatars/league_1/0.jpg') !== false);
test('locked levels use the lock badge',
    strpos($lb, '/labassets/level_avatars/lock.jpg') !== false);
test('sidebar in leagues response links both pages',
    strpos($lb, 'href="/leaderboard-global"') !== false && strpos($lb, 'Mastery Hall') !== false);

$board = http_request('GET', '/leaderboard-global', ['cookie' => $cookie]);
test('GET /leaderboard-global returns 200', ($board['status'] ?? 0) === 200, 'status=' . ($board['status'] ?? '?'));
$bb = $board['body'] ?? '';
test('leaderboard renders the header', strpos($bb, 'Global Leaderboard') !== false);
test('leaderboard renders the table', strpos($bb, 'ninja-leaderboard') !== false);
test('leaderboard renders ranked rows', strpos($bb, '<tbody>') !== false);
test('leaderboard shows the zeal column', strpos($bb, 'Zeal') !== false);

// ── Load more: 15 ranks up front, +10 raw rows per click ──
$lbPageSrc = file_get_contents(SRC_PATH . '/template/pages/leaderboard-global.php');
test('initial window is 3 winners + 12 rows', strpos($lbPageSrc, 'LB_INITIAL_RANKS') !== false);
test('page renders the Load more button', strpos($lbPageSrc, 'id="lb-load-more"') !== false);
test('load-more steps by 10 ranks', strpos($lbPageSrc, 'data-step="<?= (int)LB_LOAD_MORE_ROWS ?>"') !== false);
test('load-more fetches /api/leaderboard_rows', strpos($lbPageSrc, "/api/leaderboard_rows?offset=") !== false);
test('page appends raw rows to tbody', strpos($lbPageSrc, 'insertAdjacentHTML') !== false);

$api = http_request('GET', '/api/leaderboard_rows?offset=4&limit=10', ['cookie' => $cookie]);
test('GET /api/leaderboard_rows returns 200', ($api['status'] ?? 0) === 200, 'status=' . ($api['status'] ?? '?'));
$apiBody = $api['body'] ?? '';
test('load-more endpoint returns raw <tr> rows',
    strpos($apiBody, '<tr class="align-middle') !== false);
test('first load-more row is rank 4',
    preg_match('/<h3>4<\/h3>/', $apiBody) === 1);
test('load-more rows carry the league badge',
    strpos($apiBody, 'level-avatar') !== false);

$apiTail = http_request('GET', '/api/leaderboard_rows?offset=99999&limit=10', ['cookie' => $cookie]);
test('load-more past the end returns empty body',
    ($apiTail['status'] ?? 0) === 200 && trim($apiTail['body'] ?? '') === '');

$apiOut = http_request('GET', '/api/leaderboard_rows?offset=4');
test('load-more endpoint requires a session', ($apiOut['status'] ?? 0) === 401,
    'status=' . ($apiOut['status'] ?? '?'));

$fallback = http_request('GET', '/leaderboard-global?page=99999', ['cookie' => $cookie]);
test('legacy ?page= still returns the full board', ($fallback['status'] ?? 0) === 200, 'status=' . ($fallback['status'] ?? '?'));
test('legacy page still renders rows',
    strpos($fallback['body'] ?? '', 'ninja-leaderboard') !== false);

$signedOut = http_request('GET', '/leagues');
test('signed-out /leagues does not leak the ladder',
    strpos($signedOut['body'] ?? '', 'Cyber Knight Supreme') === false);

cleanup_test_user($email);

test_summary();
