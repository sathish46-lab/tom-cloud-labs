<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../src/load.php';

$userId = 0;
if (AuthMiddleware::isAuthenticated()) {
    $user = AuthMiddleware::getUser();
    $userId = (int)$user->getUserId();
}

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$qEscaped = preg_quote($q, '/');

if (empty($q)) {
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
    'running'    => [],
    'catalog'    => [],
    'apps'       => [],
    'challenges' => [],
    'quiz'       => [],
    'learn'      => [],
    'roadmaps'   => [],
    'syllabus'   => [],
];

$ql = strtolower($q);

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
        if (!empty($hash) && (stripos($labType, $ql) !== false || stripos($labName, $ql) !== false || stripos($hash, $ql) !== false)) {
            $results['running'][] = [
                'type' => 'running', 'label' => $labName ?: ucfirst(str_replace('_', ' ', $labType)),
                'sub' => $labType, 'icon' => $icon, 'iid' => $hash, 'lab' => $labType,
                'glyph' => 'tom-terminal', 'colour' => '#22c55e',
                'action' => 'code', 'codeserver' => true,
            ];
        }
    }
} catch (\Throwable $e) {}

/* ──────────── 2. LAB CATALOG ──────────── */
$labCatalog = [
    ['id' => 'essentials',     'name' => 'Essentials Lab',     'glyph' => 'tom-flask',          'colour' => '#ff6b1a'],
    ['id' => 'gui_essentials', 'name' => 'GUI Essentials Lab', 'glyph' => 'tom-desktop',        'colour' => '#ff6b1a'],
    ['id' => 'minio',          'name' => 'MinIO S3 Storage',   'glyph' => 'tom-database',       'colour' => '#c72c41'],
    ['id' => 'n8n',            'name' => 'n8n Workflow Lab',   'glyph' => 'tom-workflow',       'colour' => '#ff6d5a'],
    ['id' => 'docker_lab',     'name' => 'Docker Lab',         'glyph' => 'tom-package',        'colour' => '#0db7ed'],
];
foreach ($labCatalog as $lc) {
    if (stripos($lc['name'], $ql) !== false || stripos($lc['id'], $ql) !== false) {
        $results['catalog'][] = [
            'type' => 'catalog', 'label' => $lc['name'],
            'sub' => 'Deploy · ' . $lc['id'],
            'icon' => ['kind' => 'glyph', 'glyph' => $lc['glyph'], 'colour' => $lc['colour']],
            'lab' => $lc['id'], 'href' => '/labs/dashboard/' . $lc['id'],
        ];
    }
}

/* ──────────── 3. APPS / PAGES ──────────── */
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
    ['title' => 'Feeling Lucky',   'section' => 'Social',   'url' => '/lucky',              'glyph' => 'tom-lightning',       'colour' => '#a855f7'],
    ['title' => 'MCP Connections', 'section' => 'Network',  'url' => '/mcp',                'glyph' => 'tom-share-network',   'colour' => '#22d3ee'],
    ['title' => 'Domains',         'section' => 'Network',  'url' => '/domains',            'glyph' => 'tom-globe',           'colour' => '#f59e0b'],
    ['title' => 'Account',         'section' => 'Settings', 'url' => '/account',            'glyph' => 'tom-user',            'colour' => '#6366f1'],
    ['title' => 'Admin Panel',     'section' => 'Settings', 'url' => '/admin/users',        'glyph' => 'tom-crown',           'colour' => '#ef4444'],
];
foreach ($pages as $p) {
    if (stripos($p['title'], $ql) !== false || stripos($p['section'], $ql) !== false) {
        $results['apps'][] = [
            'type' => 'app', 'label' => $p['title'], 'sub' => $p['section'],
            'glyph' => $p['glyph'], 'colour' => $p['colour'], 'href' => $p['url'],
        ];
    }
}

/* ──────────── 4. CHALLENGES ──────────── */
$challengesFile = __DIR__ . '/../config/challenges.json';
if (file_exists($challengesFile)) {
    $challenges = json_decode(file_get_contents($challengesFile), true) ?: [];
    foreach ($challenges as $ch) {
        $name = $ch['name'] ?? '';
        $tags = implode(' ', array_column($ch['tags'] ?? [], 'text'));
        if (stripos($name, $ql) !== false || stripos($tags, $ql) !== false) {
            $results['challenges'][] = [
                'type' => 'challenge', 'label' => $name,
                'sub' => ($ch['ribbon_text2'] ?? 'CTF') . ' · ' . ($ch['points'] ?? 0) . ' pts',
                'glyph' => 'tom-shield', 'colour' => '#ef4444', 'href' => '/challenges',
            ];
        }
    }
}

/* ──────────── 5. QUIZ ──────────── */
$quizCatsFile = __DIR__ . '/../data/quiz_categories.json';
$quizSubFile  = __DIR__ . '/../data/quiz_subtopics.json';
$quizCats = file_exists($quizCatsFile) ? (json_decode(file_get_contents($quizCatsFile), true) ?: []) : [];
$quizSubs = file_exists($quizSubFile)  ? (json_decode(file_get_contents($quizSubFile), true)  ?: []) : [];

foreach ($quizCats as $cat) {
    if (stripos($cat['title'] ?? '', $ql) !== false || stripos($cat['desc'] ?? '', $ql) !== false) {
        $results['quiz'][] = [
            'type' => 'quiz_category', 'label' => $cat['title'],
            'sub' => 'Quiz · ' . ($cat['section'] ?? ''),
            'glyph' => 'tom-check-square', 'colour' => '#8b91f9', 'href' => '/quiz/' . ($cat['hash'] ?? ''),
        ];
    }
}
foreach ($quizSubs as $sub) {
    if (stripos($sub['title'] ?? '', $ql) !== false || stripos($sub['desc'] ?? '', $ql) !== false) {
        $parentHash = '';
        foreach ($quizCats as $cat) {
            if (($cat['id'] ?? '') === ($sub['category_id'] ?? '')) {
                $parentHash = $cat['hash'] ?? '';
                break;
            }
        }
        $results['quiz'][] = [
            'type' => 'quiz_subtopic', 'label' => $sub['title'],
            'sub' => 'Quiz · ' . ($sub['desc'] ?? ''),
            'glyph' => 'tom-check-square', 'colour' => '#8b91f9',
            'href' => $parentHash ? '/quiz/' . $parentHash : '/quiz',
        ];
    }
}

/* ──────────── 6. LEARN AI LESSONS ──────────── */
try {
    $lessonCursor = $db->ai_lessons->find([
        '$or' => [
            ['title'  => ['$regex' => $qEscaped, '$options' => 'i']],
            ['tags'   => ['$regex' => $qEscaped, '$options' => 'i']],
            ['author' => ['$regex' => $qEscaped, '$options' => 'i']],
        ],
        'visibility' => 'Public',
    ], ['limit' => 10]);
    foreach ($lessonCursor as $lesson) {
        $isSyllabus = $lesson['is_syllabus'] ?? false;
        $section = $isSyllabus ? 'syllabus' : 'learn';
        $results[$section][] = [
            'type' => 'topic', 'label' => $lesson['title'] ?? '',
            'sub' => ($isSyllabus ? 'Syllabus' : 'Lesson') . ' · ' . ($lesson['level'] ?? ''),
            'glyph' => 'tom-book-open', 'colour' => $isSyllabus ? '#f472b6' : '#06b6d4',
            'href' => '/learn/lesson/' . (string)($lesson['_id']),
        ];
    }
} catch (\Throwable $e) {}

/* ──────────── 7. ROADMAPS ──────────── */
foreach ($quizSubs as $sub) {
    if (($sub['category_id'] ?? '') === 'roadmap' && stripos($sub['title'] ?? '', $ql) !== false) {
        $results['roadmaps'][] = [
            'type' => 'topic', 'label' => $sub['title'],
            'sub' => 'Roadmap',             'glyph' => 'tom-map-trifold', 'colour' => '#10b981',
            'href' => '/roadmaps/' . ($sub['hash'] ?? ''),
        ];
    }
}

/* ──────────── 8. SYLLABUS ──────────── */
foreach ($quizSubs as $sub) {
    if (($sub['category_id'] ?? '') === 'syllabus' && stripos($sub['title'] ?? '', $ql) !== false) {
        $results['syllabus'][] = [
            'type' => 'topic', 'label' => $sub['title'],
            'sub' => 'Syllabus', 'glyph' => 'tom-note-blank', 'colour' => '#f472b6',
            'href' => '/syllabus/' . ($sub['hash'] ?? ''),
        ];
    }
}

/* ──────────── Remove empty groups ──────────── */
$results = array_filter($results, function ($v) { return !empty($v); });

echo json_encode([
    'result' => 'success',
    'q'      => $q,
    'groups' => (object)$results,
]);
