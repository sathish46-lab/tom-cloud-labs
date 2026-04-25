<?php
/**
 * src/api/challenges/leaderboard.php
 * GET /api/challenges/leaderboard?lab_id=shadow-partner
 * Returns sorted leaderboard entries for a given challenge lab.
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../load.php';

if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']); exit;
}

$labId = $_GET['lab_id'] ?? '';

if (empty($labId)) {
    echo json_encode(['status' => 'error', 'message' => 'lab_id required']); exit;
}

$db = DatabaseConnection::getDefaultDatabase();

// Fetch leaderboard entries sorted by zeal DESC, then time ASC
$entries = $db->challenge_leaderboard->find(
    ['lab_id' => $labId],
    ['sort' => ['zeal' => -1, 'time_spent_seconds' => 1], 'limit' => 100]
)->toArray();

$leaderboard = [];
$rank = 1;

foreach ($entries as $entry) {
    $leaderboard[] = [
        'rank'                  => $rank++,
        'username'              => $entry['username'] ?? 'Unknown',
        'avatar_url'            => $entry['avatar_url'] ?? '/assets/avatars/default.png',
        'challenges_completed'  => $entry['challenges_completed'] ?? 0,
        'total_challenges'      => $entry['total_challenges'] ?? 1,
        'zeal'                  => $entry['zeal'] ?? 0,
        'max_zeal'              => $entry['max_zeal'] ?? 2232,
        'time_spent_seconds'    => $entry['time_spent_seconds'] ?? 0,
        'time_display'          => gmdate('H:i:s', $entry['time_spent_seconds'] ?? 0),
    ];
}

echo json_encode([
    'status'      => 'ok',
    'lab_id'      => $labId,
    'total'       => count($leaderboard),
    'leaderboard' => $leaderboard,
]);
