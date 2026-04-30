<?php
/**
 * API: Trigger AI Quiz Generation
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../../load.php';
use TomLabs\Labs\Quiz;

// Auth Protection
if (!Session::getAuthStatus()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$topicId = $_GET['topic'] ?? null;
$subtopicId = $_GET['subtopic'] ?? null;
$diff = $_GET['diff'] ?? 'normal';

if (!$topicId || !$subtopicId) {
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

try {
    $user = Session::getUser();
    $userEmail = $user ? $user->getEmail() : null;
    
    // 1. Check for Jolt
    $stats = Quiz::getUserStats($userEmail);
    if (($stats['jolt'] ?? 0) < 1) {
        echo json_encode(['error' => 'Insufficient Jolt fuel. Earn more by completing quizzes!']);
        exit;
    }

    // 2. Deduct 1 Jolt
    Quiz::updateUserStats($userEmail, 0, -1);

    // 3. Trigger Generation
    $response = Quiz::startGeneration($topicId, $subtopicId, $diff);
    
    // Add updated balance to response
    $response['new_jolt'] = ($stats['jolt'] ?? 1) - 1;
    
    echo json_encode($response);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
