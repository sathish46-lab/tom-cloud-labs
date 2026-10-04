<?php
/**
 * Profile achievements — rule engine over data the platform already stores.
 *
 * Every rule reads a counter we already collect (or a collection we already
 * write). Nothing here is stored per-user: badges are derived on render, so
 * they can never drift out of sync with the underlying stats.
 *
 * Groups:
 *   platform  — account, activity, labs, domains, MCP, learning  (real data today)
 *   quiz      — quiz_attempts                                    (unlocks as people take quizzes)
 *   ctf       — challenge_submissions                            (unlocks as people solve challenges)
 *
 * `metrics` keys are produced by achievements_metrics().
 */

function achievements_metrics(array $user, $db): array
{
    $email = (string)($user['email'] ?? '');
    $uid   = $user['user_id'] ?? null;
    $idIn = array_values(array_filter([$uid, (string)$uid], static fn ($v) => $v !== null && $v !== ''));
    $byUid = ['user_id' => ['$in' => $idIn]];

    $count = static function ($name, array $filter) use ($db): int {
        try {
            return (int)$db->selectCollection($name)->countDocuments($filter);
        } catch (Throwable $e) {
            return 0;
        }
    };

    $stats = $email ? $db->user_stats->findOne(['user_email' => $email]) : null;

    $first = $db->user_activity->findOne(['user_id' => $byUid['user_id']], ['sort' => ['timestamp' => 1], 'projection' => ['timestamp' => 1]]);
    $last  = $db->user_activity->findOne(['user_id' => $byUid['user_id']], ['sort' => ['timestamp' => -1], 'projection' => ['timestamp' => 1]]);
    $events = $count('user_activity', ['user_id' => $byUid['user_id']]);
    $activeDays = 0;
    foreach ($db->user_activity->aggregate([
        ['$match' => ['user_id' => $byUid['user_id']]],
        ['$group' => ['_id' => '$date']],
        ['$count' => 'n'],
    ]) as $row) {
        $activeDays = (int)($row['n'] ?? 0);
    }

    $weekAgo = time() - 7 * 86400;
    $thisWeek = $count('user_activity', ['user_id' => $byUid['user_id'], 'timestamp' => ['$gte' => $weekAgo]]);

    $quizFilter = $email ? ['user_email' => $email] : [];
    $quizAttempts = $count('quiz_attempts', $quizFilter);
    $quizPerfect  = $count('quiz_attempts', $quizFilter + ['$expr' => ['$eq' => ['$score', '$total']]]);
    $ctfSolved    = $count('challenge_submissions', $email ? ['user_email' => $email, 'status' => 'solved'] : []);

    $progressRoadmapIds = [];
    foreach ($db->ai_roadmap_progress->find(['user_id' => $byUid['user_id']], ['projection' => ['roadmap_id' => 1]]) as $p) {
        $progressRoadmapIds[] = $p['roadmap_id'];
    }
    $roadmapsStarted = count($progressRoadmapIds);
    $roadmapsCompleted = 0;
    if ($progressRoadmapIds) {
        foreach ($db->ai_roadmaps->find(['_id' => ['$in' => $progressRoadmapIds]], ['projection' => ['checkpoints_total' => 1, 'checkpoints_completed' => 1]]) as $r) {
            $total = (int)($r['checkpoints_total'] ?? 0);
            if ($total > 0 && (int)($r['checkpoints_completed'] ?? 0) >= $total) {
                $roadmapsCompleted++;
            }
        }
    }

    $created = (int)($user['created_at'] ?? 0);
    $accountDays = $created > 0 ? (int)floor((time() - $created) / 86400) : 0;

    $storage = $email ? $db->storage_usage->findOne(['user_email' => $email]) : null;

    return [
        'events'            => $events,
        'active_days'       => $activeDays,
        'this_week'         => $thisWeek,
        'first_event'       => $first ? (int)$first['timestamp'] : 0,
        'last_event'        => $last ? (int)$last['timestamp'] : 0,
        'zeal'              => (int)($stats['zeal'] ?? $user['zeal_stats']['zeal'] ?? 0),
        'jolt'              => (int)($stats['jolt'] ?? $user['zeal_stats']['jolt'] ?? 0),
        'labs'              => $count('machine_labs', ['user_id' => $byUid['user_id']]),
        'domains'           => $count('domains', ['user_id' => $byUid['user_id']]),
        'ssh_keys'          => $count('ssh_keys', ['user_id' => $byUid['user_id']]),
        'mcp_clients'       => $count('mcp_clients', ['user_id' => $byUid['user_id']]),
        'mcp_tokens'        => $count('mcp_tokens', ['user_id' => $byUid['user_id']]),
        'roadmaps_started'  => $roadmapsStarted,
        'roadmaps_done'     => $roadmapsCompleted,
        'roadmap_likes'     => $count('ai_roadmap_likes', ['user_id' => $byUid['user_id']]),
        'lesson_likes'      => $count('ai_lesson_likes', ['user_id' => $byUid['user_id']]),
        'highlights'        => $count('ai_highlights', ['user_id' => $byUid['user_id']]),
        'audit_actions'     => $count('audit_log', ['user_id' => $byUid['user_id']]),
        'quiz_attempts'     => $quizAttempts,
        'quiz_perfect'      => $quizPerfect,
        'ctf_solved'        => $ctfSolved,
        'account_days'      => $accountDays,
        'verified'          => (bool)($user['is_verified'] ?? false),
        'two_factor'        => (bool)($user['two_factor_enabled'] ?? false),
        'is_admin'          => in_array($user['role'] ?? '', ['admin', 'superuser'], true),
        'storage_bytes'     => (int)($storage['bytes'] ?? 0),
    ];
}

function achievement_badge(string $name, string $desc, string $icon, string $color, bool $unlocked, string $hint = ''): array
{
    return [
        'name'     => $name,
        'desc'     => $desc,
        'icon'     => $icon,
        'color'    => $color,
        'unlocked' => $unlocked,
        'hint'     => $hint,
    ];
}

/**
 * @return array<int, array{key:string,label:string,icon:string,color:string,items:array[]}>
 */
function achievement_groups(array $m): array
{
    $globalRank = isset($m['global_rank']) && $m['global_rank'] !== null ? (int)$m['global_rank'] : null;

    $platform = [
        achievement_badge('First Steps',    'Record your first activity',              'bx-foot',            '#10b981', $m['events'] >= 1),
        achievement_badge('Week One',       'Active on 7 different days',              'bx-calendar-heart',  '#06b6d4', $m['active_days'] >= 7),
        achievement_badge('Regular',        'Active on 30 different days',             'bx-calendar-check',  '#6366f1', $m['active_days'] >= 30, $m['active_days'] . '/30 days'),
        achievement_badge('Power User',     'Log 1,000 actions',                       'bx-bolt-circle',     '#f59e0b', $m['events'] >= 1000, number_format($m['events']) . '/1,000'),
        achievement_badge('Zeal 1K',        'Reach 1,000 Zeal',                        'bx-flame',           '#ef4444', $m['zeal'] >= 1000),
        achievement_badge('Zeal 50K',       'Reach 50,000 Zeal',                       'bx-burn',            '#ec4899', $m['zeal'] >= 50000),
        achievement_badge('Top Ten',        'Break into the global top 10',            'bx-trophy',          '#fbbf24', $globalRank !== null && $globalRank <= 10, $globalRank !== null ? '#' . number_format($globalRank) . ' global' : ''),
        achievement_badge('Veteran',        'Keep your account for 90 days',           'bx-shield-quarter',  '#8b5cf6', $m['account_days'] >= 90, $m['account_days'] . '/90 days'),
        achievement_badge('Verified',       'Verify your account',                     'bx-check-shield',    '#10b981', $m['verified']),
        achievement_badge('Guardian',       'Turn on two-factor authentication',       'bx-lock',            '#06b6d4', $m['two_factor']),
        achievement_badge('First Deploy',   'Launch your first container',             'bx-rocket',          '#6366f1', $m['labs'] >= 1),
        achievement_badge('Fleet Builder',  'Run 10 containers',                       'bx-server',          '#f59e0b', $m['labs'] >= 10, $m['labs'] . '/10'),
        achievement_badge('Domain Baron',   'Point 5 domains at your labs',            'bx-globe',           '#ec4899', $m['domains'] >= 5, $m['domains'] . '/5'),
        achievement_badge('Keyholder',      'Register an SSH key',                     'bx-key',             '#94a3b8', $m['ssh_keys'] >= 1),
        achievement_badge('Connector',      'Register an MCP client',                  'bx-plug',            '#14b8a6', $m['mcp_clients'] >= 1),
        achievement_badge('Scholar',        'Start 10 learning roadmaps',              'bx-book-open',       '#8b5cf6', $m['roadmaps_started'] >= 10, $m['roadmaps_started'] . '/10'),
        achievement_badge('Polymath',       'Start 20 learning roadmaps',              'bx-graduation',      '#06b6d4', $m['roadmaps_started'] >= 20, $m['roadmaps_started'] . '/20'),
        achievement_badge('Highlighter',    'Save a highlight while reading',          'bx-highlight',       '#fbbf24', $m['highlights'] >= 1),
    ];

    $quiz = [
        achievement_badge('First Attempt',  'Finish your first quiz',                  'bx-pencil',          '#f59e0b', $m['quiz_attempts'] >= 1),
        achievement_badge('Quiz Regular',   'Finish 10 quizzes',                       'bx-check-double',    '#10b981', $m['quiz_attempts'] >= 10, $m['quiz_attempts'] . '/10'),
        achievement_badge('Quiz Master',    'Finish 100 quizzes',                      'bx-award',           '#6366f1', $m['quiz_attempts'] >= 100, $m['quiz_attempts'] . '/100'),
        achievement_badge('Perfect Score',  'Score 100% on a quiz',                    'bx-star',            '#fbbf24', $m['quiz_perfect'] >= 1),
        achievement_badge('Flawless',       'Score 100% ten times',                    'bx-sparkles',        '#ec4899', $m['quiz_perfect'] >= 10, $m['quiz_perfect'] . '/10'),
        achievement_badge('Marathon',       'Finish 500 quizzes',                      'bx-run',             '#ef4444', $m['quiz_attempts'] >= 500, $m['quiz_attempts'] . '/500'),
    ];

    $ctf = [
        achievement_badge('First Blood',    'Solve your first challenge',              'bx-drop',            '#ef4444', $m['ctf_solved'] >= 1),
        achievement_badge('Capture Crew',   'Solve 5 challenges',                      'bx-flag',            '#f59e0b', $m['ctf_solved'] >= 5, $m['ctf_solved'] . '/5'),
        achievement_badge('Root King',      'Solve 20 challenges',                     'bx-wrench',          '#6366f1', $m['ctf_solved'] >= 20, $m['ctf_solved'] . '/20'),
        achievement_badge('Ghost',          'Solve 50 challenges',                     'bx-ghost',           '#8b5cf6', $m['ctf_solved'] >= 50, $m['ctf_solved'] . '/50'),
        achievement_badge('Speed Demon',    'Solve 100 challenges',                    'bx-time-five',       '#06b6d4', $m['ctf_solved'] >= 100, $m['ctf_solved'] . '/100'),
        achievement_badge('Immortal',       'Solve 250 challenges',                    'bx-infinite',        '#ec4899', $m['ctf_solved'] >= 250, $m['ctf_solved'] . '/250'),
    ];

    return [
        ['key' => 'platform',    'label' => 'Platform',    'icon' => 'bx-rocket',      'color' => '#6366f1', 'items' => $platform],
        ['key' => 'quiz',        'label' => 'Quiz',        'icon' => 'bx-pencil',      'color' => '#f59e0b', 'items' => $quiz],
        ['key' => 'ctf',         'label' => 'CTF',         'icon' => 'bx-flag',        'color' => '#ef4444', 'items' => $ctf],
    ];
}

/** Counts for the achievements header progress bar. */
function achievement_summary(array $groups): array
{
    $unlocked = 0;
    $total = 0;
    foreach ($groups as $group) {
        foreach ($group['items'] as $item) {
            $total++;
            if ($item['unlocked']) {
                $unlocked++;
            }
        }
    }
    return [
        'unlocked' => $unlocked,
        'total'    => $total,
        'percent'  => $total > 0 ? (int)round(($unlocked / $total) * 100) : 0,
    ];
}
