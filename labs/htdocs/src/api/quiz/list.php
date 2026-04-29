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
    
    // Format for frontend
    $formatted = array_map(function($q) use ($subtopicId) {
        return [
            'hash' => $q['hash'],
            'title' => $q['title'],
            'desc' => $q['desc'] ?? "Explore the intricate mechanics of this domain through our AI-curated challenge.",
            'difficulty' => strtoupper($q['difficulty']),
            'created_at' => date('M j', (int)$q['created_at']),
            'points' => $q['points_per_correct'] ?? 25,
            'tags' => (isset($q['tags']) && is_array($q['tags'])) ? array_slice($q['tags'], 0, 3) : ['tech', 'cybersecurity']
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
