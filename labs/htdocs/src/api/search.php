<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../src/load.php';
require_once __DIR__ . '/../../src/lib/core/SearchMatch.class.php';

$userId = 0;
if (AuthMiddleware::isAuthenticated()) {
    $user = AuthMiddleware::getUser();
    $userId = (int)$user->getUserId();
}

$isAdmin = false;
try {
    $isAdmin = AuthMiddleware::isAuthenticated() && AuthMiddleware::isAdmin();
} catch (\Throwable $e) {}

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$ql = mb_strtolower($q);
$tokens = SearchMatch::tokenize($q);

if ($ql === '' || $tokens === []) {
    echo json_encode(['result' => 'success', 'q' => '', 'groups' => (object)[]]);
    exit;
}

try {
    $mongoClient = DatabaseConnection::getClient();
    $db = $mongoClient->selectDatabase('tom_labs_db');
} catch (\Throwable $e) {
    echo json_encode(['result' => 'error', 'message' => 'Database connection failed']);
    exit;
}

$results = [
    'admin'     => [],
    'devices'   => [],
    'running'   => [],
    'catalog'   => [],
    'apps'      => [],
    'challenges'=> [],
    'quiz'      => [],
    'learn'     => [],
    'roadmaps'  => [],
    'syllabus'  => [],
];

/** Score a set of fields; null = no match. */
$rank = fn (array $fields): ?int => SearchMatch::scoreFields($fields, $ql, $tokens);

/** Collect a scored row into a group (drops it when the query doesn't match). */
$collect = function (string $group, array $row, ?int $score) use (&$results): void {
    if ($score === null) {
        return;
    }
    $row['_score'] = $score;
    $results[$group][] = $row;
};

/* ──────────── 0. ADMIN FEATURES (admin users only) ──────────── */
if ($isAdmin) {
    $adminPages = [
        ['Users',          '/admin/users',         'bx-group'],
        ['Deleted Users',  '/admin/deleted-users', 'bx-archive'],
        ['Access Control', '/admin/acl',           'bx-shield-quarter'],
        ['MCP Tools',      '/admin/mcp',           'bx-bot'],
        ['Storage Quotas', '/admin/storage',       'bx-hdd'],
        ['Transactions',   '/admin/transactions',  'bx-transfer'],
        ['Errors',         '/admin/errors',        'bx-error-circle'],
        ['Services',       '/admin/services',      'bx-desktop'],
        ['Modules',        '/admin/modules',       'bx-toggle-right'],
        ['Appearance',     '/admin/appearance',    'bx-palette'],
        ['Instances',      '/admin/instances',     'bx-server'],
    ];
    foreach ($adminPages as [$title, $url, $glyph]) {
        $collect('admin', [
            'type' => 'admin', 'label' => $title, 'sub' => 'Admin',
            'glyph' => $glyph, 'colour' => '#f43f5e', 'href' => $url,
        ], $rank([$title, $url, 'admin panel']));
    }
}

/* ──────────── 1. RUNNING LABS ──────────── */
try {
    $runningLabs = $db->machine_labs->find([
        'user_id' => (int)$userId,
        'status'  => ['$in' => ['running', 'paused']],
    ]);
    foreach ($runningLabs as $lab) {
        $labType = $lab['lab_type'] ?? '';
        $labName = $lab['lab_name'] ?? '';
        $hash    = $lab['instance_hash'] ?? '';
        $icon    = $lab['icon'] ?? null;
        if (empty($hash)) {
            continue;
        }
        $collect('running', [
            'type' => 'running', 'label' => $labName ?: ucfirst(str_replace('_', ' ', $labType)),
            'sub' => $labType, 'icon' => $icon, 'iid' => $hash, 'lab' => $labType,
            'glyph' => 'tom-terminal', 'colour' => '#22c55e',
            'action' => 'code', 'codeserver' => true,
        ], $rank([$labName, $labType, $hash]));
    }
} catch (\Throwable $e) {}

/* ──────────── 2. YOUR DEVICES ──────────── */
if ($userId) {
    try {
        $deviceCursor = $db->devices->find([
            'user_id' => (int)$userId,
            'status'  => ['$ne' => 'deleted'],
        ]);
        foreach ($deviceCursor as $dev) {
            $name = (string)($dev['device_name'] ?? '');
            $type = (string)($dev['device_type'] ?? '');
            $ip   = (string)($dev['assigned_ip'] ?? '');
            if ($name === '' && $type === '') {
                continue;
            }
            $collect('devices', [
                'type' => 'device', 'label' => $name ?: 'Device',
                'sub' => trim($type . ($ip !== '' ? ' · ' . $ip : '')),
                'glyph' => 'tom-devices', 'colour' => '#38bdf8', 'href' => '/devices',
            ], $rank([$name, $type, $ip]));
        }
    } catch (\Throwable $e) {}
}

/* ──────────── 3. LAB CATALOG ──────────── */
$labCatalog = [
    ['id' => 'essentials',     'name' => 'Essentials Lab',     'glyph' => 'tom-flask',          'colour' => '#ff6b1a'],
    ['id' => 'gui_essentials', 'name' => 'GUI Essentials Lab', 'glyph' => 'tom-desktop',        'colour' => '#ff6b1a'],
    ['id' => 'minio',          'name' => 'MinIO S3 Storage',   'glyph' => 'tom-database',       'colour' => '#c72c41'],
    ['id' => 'n8n',            'name' => 'n8n Workflow Lab',   'glyph' => 'tom-workflow',       'colour' => '#ff6d5a'],
    ['id' => 'docker_lab',     'name' => 'Docker Lab',         'glyph' => 'tom-package',        'colour' => '#0db7ed'],
];
foreach ($labCatalog as $lc) {
    $collect('catalog', [
        'type' => 'catalog', 'label' => $lc['name'],
        'sub' => 'Deploy · ' . $lc['id'],
        'icon' => ['kind' => 'glyph', 'glyph' => $lc['glyph'], 'colour' => $lc['colour']],
        'lab' => $lc['id'], 'href' => '/labs/dashboard/' . $lc['id'],
    ], $rank([$lc['name'], $lc['id'], 'lab catalog']));
}

/* ──────────── 4. APPS / PAGES ──────────── */
$pages = [
    ['title' => 'Dashboard',       'section' => 'Main',     'url' => '/dashboard',          'glyph' => 'tom-chart-pie-slice', 'colour' => '#0db7ed'],
    ['title' => 'Machine Labs',    'section' => 'Main',     'url' => '/labs',               'glyph' => 'tom-monitor',         'colour' => '#22c55e'],
    ['title' => 'Challenge Labs',  'section' => 'Main',     'url' => '/challenges',         'glyph' => 'tom-shield',          'colour' => '#ef4444'],
    ['title' => 'Spot Quiz',       'section' => 'Learn',    'url' => '/quiz',               'glyph' => 'tom-check-square',    'colour' => '#8b91f9'],
    ['title' => 'Code Arena',      'section' => 'Learn',    'url' => '/code',               'glyph' => 'tom-code',            'colour' => '#fbbf24'],
    ['title' => 'Learn AI',        'section' => 'Learn',    'url' => '/learn',              'glyph' => 'tom-book-open',       'colour' => '#06b6d4'],
    ['title' => 'Roadmaps',        'section' => 'Learn',    'url' => '/roadmaps',           'glyph' => 'tom-map-trifold',     'colour' => '#10b981'],
    ['title' => 'Syllabus AI',     'section' => 'Learn',    'url' => '/syllabus',           'glyph' => 'tom-note-blank',      'colour' => '#f472b6'],
    ['title' => 'Clubs',           'section' => 'Social',   'url' => '/clubs',              'glyph' => 'tom-users-three',     'colour' => '#ec4899'],
    ['title' => 'Clans',           'section' => 'Social',   'url' => '/clans',              'glyph' => 'tom-flag',            'colour' => '#ef4444'],
    ['title' => 'Leaderboard',     'section' => 'Social',   'url' => '/leaderboard-global', 'glyph' => 'tom-chart-bar',       'colour' => '#eab308'],
    ['title' => 'League of Ronin', 'section' => 'Social',   'url' => '/leagues',            'glyph' => 'tom-shield-checkered','colour' => '#f43f5e'],
    ['title' => 'Feeling Lucky',   'section' => 'Social',   'url' => '/lucky',              'glyph' => 'tom-lightning',       'colour' => '#a855f7'],
    ['title' => 'MCP Connections', 'section' => 'Network',  'url' => '/mcp',                'glyph' => 'tom-share-network',   'colour' => '#22d3ee'],
    ['title' => 'Devices',         'section' => 'Network',  'url' => '/devices',            'glyph' => 'tom-devices',         'colour' => '#38bdf8'],
    ['title' => 'Domains',         'section' => 'Network',  'url' => '/domains',            'glyph' => 'tom-globe',           'colour' => '#f59e0b'],
    ['title' => 'My Profile',      'section' => 'Settings', 'url' => '/account',            'glyph' => 'tom-user',            'colour' => '#6366f1'],
];
foreach ($pages as $p) {
    $collect('apps', [
        'type' => 'app', 'label' => $p['title'], 'sub' => $p['section'],
        'glyph' => $p['glyph'], 'colour' => $p['colour'], 'href' => $p['url'],
    ], $rank([$p['title'], $p['section'], $p['url'], 'pages apps']));
}

/* ──────────── 5. CHALLENGES ──────────── */
$challengesFile = __DIR__ . '/../config/challenges.json';
if (file_exists($challengesFile)) {
    $challenges = json_decode(file_get_contents($challengesFile), true) ?: [];
    foreach ($challenges as $ch) {
        $name = $ch['name'] ?? '';
        $tags = implode(' ', array_column($ch['tags'] ?? [], 'text'));
        $collect('challenges', [
            'type' => 'challenge', 'label' => $name,
            'sub' => ($ch['ribbon_text2'] ?? 'CTF') . ' · ' . ($ch['points'] ?? 0) . ' pts',
            'glyph' => 'tom-shield', 'colour' => '#ef4444', 'href' => '/challenges',
        ], $rank([$name, $tags, 'challenge ctf']));
    }
}

/* ──────────── 6. QUIZ ──────────── */
$quizCatsFile = __DIR__ . '/../data/quiz_categories.json';
$quizSubFile  = __DIR__ . '/../data/quiz_subtopics.json';
$quizCats = file_exists($quizCatsFile) ? (json_decode(file_get_contents($quizCatsFile), true) ?: []) : [];
$quizSubs = file_exists($quizSubFile)  ? (json_decode(file_get_contents($quizSubFile), true)  ?: []) : [];

foreach ($quizCats as $cat) {
    $collect('quiz', [
        'type' => 'quiz_category', 'label' => $cat['title'] ?? '',
        'sub' => 'Quiz · ' . ($cat['section'] ?? ''),
        'glyph' => 'tom-check-square', 'colour' => '#8b91f9', 'href' => '/quiz/' . ($cat['hash'] ?? ''),
    ], $rank([$cat['title'] ?? '', $cat['desc'] ?? '', $cat['section'] ?? '', 'quiz topic']));
}
foreach ($quizSubs as $sub) {
    $parentHash = '';
    foreach ($quizCats as $cat) {
        if (($cat['id'] ?? '') === ($sub['category_id'] ?? '')) {
            $parentHash = $cat['hash'] ?? '';
            break;
        }
    }
    $collect('quiz', [
        'type' => 'quiz_subtopic', 'label' => $sub['title'] ?? '',
        'sub' => 'Quiz · ' . ($sub['desc'] ?? ''),
        'glyph' => 'tom-check-square', 'colour' => '#8b91f9',
        'href' => $parentHash ? '/quiz/' . $parentHash : '/quiz',
    ], $rank([$sub['title'] ?? '', $sub['desc'] ?? '', 'quiz topic']));
}

/* ──────────── 7. LEARN AI LESSONS (token-OR in Mongo, scored in PHP) ──────────── */
try {
    $tokenOr = [];
    foreach ($tokens as $t) {
        $tEsc = preg_quote($t, '/');
        $tokenOr[] = ['$or' => [
            ['title'  => ['$regex' => $tEsc, '$options' => 'i']],
            ['tags'   => ['$regex' => $tEsc, '$options' => 'i']],
            ['author' => ['$regex' => $tEsc, '$options' => 'i']],
        ]];
    }
    $lessonCursor = $db->ai_lessons->find([
        '$and'      => $tokenOr,
        'visibility' => 'Public',
    ], ['limit' => 50]);
    foreach ($lessonCursor as $lesson) {
        $isSyllabus = $lesson['is_syllabus'] ?? false;
        $section = $isSyllabus ? 'syllabus' : 'learn';
        $tagText = '';
        foreach ((array)($lesson['tags'] ?? []) as $tg) {
            $tagText .= ' ' . (is_array($tg) ? (string)($tg['text'] ?? '') : (string)$tg);
        }
        $collect($section, [
            'type' => 'topic', 'label' => $lesson['title'] ?? '',
            'sub' => ($isSyllabus ? 'Syllabus' : 'Lesson') . ' · ' . ($lesson['level'] ?? ''),
            'glyph' => 'tom-book-open', 'colour' => $isSyllabus ? '#f472b6' : '#06b6d4',
            'href' => '/learn/lesson/' . (string)($lesson['_id']),
        ], $rank([$lesson['title'] ?? '', trim($tagText), $lesson['author'] ?? '', 'learn lesson']));
    }
} catch (\Throwable $e) {}

/* ──────────── 8. ROADMAPS ──────────── */
foreach ($quizSubs as $sub) {
    if (($sub['category_id'] ?? '') === 'roadmap') {
        $collect('roadmaps', [
            'type' => 'topic', 'label' => $sub['title'] ?? '',
            'sub' => 'Roadmap',             'glyph' => 'tom-map-trifold', 'colour' => '#10b981',
            'href' => '/roadmaps/' . ($sub['hash'] ?? ''),
        ], $rank([$sub['title'] ?? '', $sub['desc'] ?? '', 'roadmap learning path']));
    }
}

/* ──────────── 9. SYLLABUS ──────────── */
foreach ($quizSubs as $sub) {
    if (($sub['category_id'] ?? '') === 'syllabus') {
        $collect('syllabus', [
            'type' => 'topic', 'label' => $sub['title'] ?? '',
            'sub' => 'Syllabus', 'glyph' => 'tom-note-blank', 'colour' => '#f472b6',
            'href' => '/syllabus/' . ($sub['hash'] ?? ''),
        ], $rank([$sub['title'] ?? '', $sub['desc'] ?? '', 'syllabus course']));
    }
}

/* ──────────── Score-sort every group, cap the unbounded ones ──────────── */
$capped = ['learn' => 10, 'syllabus' => 10, 'quiz' => 20, 'roadmaps' => 20];
foreach ($results as $key => $rows) {
    $rows = SearchMatch::sortItems($rows);
    if (isset($capped[$key])) {
        $rows = array_slice($rows, 0, $capped[$key]);
    }
    $results[$key] = $rows;
}
$results = array_filter($results, function ($v) { return !empty($v); });

echo json_encode([
    'result' => 'success',
    'q'      => $q,
    'groups' => (object)$results,
]);
