<?php
require_once '../../load.php';
use TomLabs\Labs\Quiz;

header('Content-Type: application/json');

// Auth Protection
if (!Session::getAuthStatus()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$subtopicId = $_GET['subtopic_id'] ?? null;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 12;

if (!$subtopicId) {
    echo json_encode(['error' => 'Missing subtopic ID']);
    exit;
}

try {
    $quizzes = Quiz::getRecentForSubtopic($subtopicId, $limit, $offset);
    $user = Session::getUser();
    $userEmail = $user ? $user->getEmail() : null;
    // Format for frontend
    $formatted = array_map(function($q) use ($subtopicId, $userEmail) {
        $diff = strtolower($q['difficulty'] ?? 'normal');
        $joltReward = 2; // Default Normal
        if ($diff === 'easy') $joltReward = 1;
        elseif ($diff === 'hard') $joltReward = 5;

        $isAttempted = Quiz::hasAttempted($userEmail, $q['hash']);

        return [
            'hash' => $q['hash'],
            'title' => $q['title'],
            'desc' => $q['desc'] ?? "Explore the intricate mechanics of this domain through our AI-curated challenge.",
            'difficulty' => strtoupper($q['difficulty']),
            'created_at' => date('M j', (int)$q['created_at']),
            'points' => $isAttempted ? 0 : ($q['points_per_correct'] ?? 25),
            'jolt_reward' => $isAttempted ? 0 : $joltReward,
            'view_count' => $q['view_count'] ?? 0,
            'tags' => (isset($q['tags']) && is_array($q['tags'])) ? array_slice($q['tags'], 0, 3) : ['tech', 'cybersecurity'],
            'is_attempted' => $isAttempted
        ];
    }, $quizzes);

    echo json_encode([
        'status' => 'success',
        'data' => $formatted,
        'count' => count($formatted),
        'has_more' => count($formatted) === $limit
    ]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
