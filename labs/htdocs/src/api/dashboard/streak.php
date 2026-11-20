<?php

/**
 * Streak API — GET/POST /api/dashboard/streak
 *
 * GET:  returns the current user's streak state + recent activity + recent activity points.
 * POST: records a streak activity (award zeal/jolt + advance streak) for the current user.
 *       Body JSON: { activity_type, zeal, jolt, description }
 *       Admin-only backfill: { backfill: true } (recomputes all users from history).
 */

require_once __DIR__ . '/../../load.php';
require_once __DIR__ . '/../../lib/core/Streak.class.php';

$user = AuthMiddleware::requireAuth();
$email = $user->getEmail();
$db = DatabaseConnection::getDefaultDatabase();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $state = Streak::get($email);

    // Recent activity for the streak card (last 5 activities with points/penalty)
    $ninetyDaysAgo = Streak::now() - (90 * 24 * 60 * 60);
    $recent = $db->user_activity->find(
        ['email' => $email, 'timestamp' => ['$gte' => $ninetyDaysAgo]],
        ['sort' => ['timestamp' => -1], 'limit' => 10]
    );

    $activityItems = [];
    foreach ($recent as $a) {
        $page = $a['page'] ?? '/';
        $activityType = Streak::activityTypeFromPage($page);
        $pts = Streak::pointsFor($activityType);
        $penalty = $state['penalty_pct'];

        $activityItems[] = [
            'icon'        => 'bx bx-server fs-6',
            'color'       => '#2e86de',
            'bg'          => 'rgba(46, 134, 222, 0.15)',
            'border'      => 'rgba(46, 134, 222, 0.30)',
            'desc'        => htmlspecialchars(ucfirst(str_replace('_', ' ', $activityType)) . ' activity'),
            'time'        => isset($a['timestamp']) ? formatActivityTime((int)$a['timestamp']) : 'Just now',
            'points'      => (int)$pts['zeal'] > 0 ? '+' . (int)$pts['zeal'] . ' 🔥' : '',
            'jolt'        => (int)$pts['jolt'] > 0 ? '+' . (int)$pts['jolt'] . ' ⚡' : '',
            'penalty_pct' => $penalty > 0 ? '-' . $penalty . '%' : '',
            'has_penalty' => $penalty > 0,
        ];
    }

    api_json([
        'success'           => true,
        'current_streak'    => (int)$state['current_streak'],
        'longest_streak'    => (int)$state['longest_streak'],
        'current_day'       => (int)$state['current_day'],
        'misses_left'       => (int)$state['misses_left'],
        'misses_this_cycle' => (int)$state['misses_this_cycle'],
        'penalty_pct'       => (int)$state['penalty_pct'],
        'daily_zeal'        => Streak::DAILY_ZEAL,
        'last_activity_date'=> $state['last_activity_date'],
        'has_activity_today'=> (bool)$state['has_activity_today'],
        'total_zeal_today'  => (int)($state['total_zeal_today'] ?? 0),
        'total_jolt_today'  => (int)($state['total_jolt_today'] ?? 0),
        'max_zeal'          => 1500,
        'max_jolt'          => 500,
        'milestones'        => Streak::MILESTONES,
        'milestone_rewards' => Streak::MILESTONE_REWARDS,
        'milestones_reached'=> array_values((array)($state['milestones_reached'] ?? [])),
        'recent_activity'   => $activityItems,
    ], 200);
}

// POST — record activity or backfill
AuthMiddleware::requireCsrf();
$input = json_decode(file_get_contents('php://input'), true) ?: [];

if (!is_array($input)) {
    api_json(['success' => false, 'error' => 'Invalid JSON body'], 400);
}

// Admin backfill: recompute streak for all users from history
if (!empty($input['backfill'])) {
    $isAdmin = ($user->getRole() ?? '') === 'superuser';
    if (!$isAdmin) {
        api_json(['success' => false, 'error' => 'Admin only'], 403);
    }
    $limit = (int)($input['limit'] ?? 0);
    $result = Streak::backfillAll($limit);
    api_json([
        'success'  => true,
        'processed'=> $result['processed'],
        'errors'   => $result['errors'],
    ], 200);
}

// Record a single activity for the current user
$activityType = (string)($input['activity_type'] ?? 'default');
$zeal         = (int)($input['zeal'] ?? 0);
$jolt         = (int)($input['jolt'] ?? 0);
$description  = (string)($input['description'] ?? '');

$result = Streak::recordActivity($email, $activityType, $zeal, $jolt, $description);
if (!$result['success']) {
    api_json(['success' => false, 'error' => $result['error']], 400);
}

api_json([
    'success' => true,
    'streak'  => $result['streak'],
], 200);

/**
 * Format activity elapsed time (mirrors dashboard helper).
 */
function formatActivityTime($timestamp) {
    $diff = time() - $timestamp;
    if ($diff < 0) return 'Just now';
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return round($diff / 60) . 'm ago';
    if ($diff < 86400) return round($diff / 3600) . 'h ago';
    if ($diff < 604800) return round($diff / 86400) . 'd ago';
    return date('M j', $timestamp);
}
