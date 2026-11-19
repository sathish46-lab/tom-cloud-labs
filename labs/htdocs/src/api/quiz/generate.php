<?php
/**
 * API: Trigger AI Quiz Generation
 */

require_once __DIR__ . '/../../load.php';
use TomLabs\Labs\Quiz;

AuthMiddleware::requireAuth();

$user = Session::getUser();
$userEmail = $user ? $user->getEmail() : null;

$topicId = $_GET['topic'] ?? null;
$subtopicId = $_GET['subtopic'] ?? null;
$diff = $_GET['diff'] ?? 'normal';

if (!$topicId || !$subtopicId) {
api_json(['error' => 'Missing required parameters'], 400);
}

try {
    // 1. Check for Jolt
    $stats = Quiz::getUserStats($userEmail);
    if (($stats['jolt'] ?? 0) < 1) {
api_json(['error' => 'Insufficient Jolt fuel. Earn more by completing quizzes!'], 400);
    }

    // 2. Deduct 1 Jolt
    Quiz::updateUserStats($userEmail, 0, -1, 'Generated Problem', 'Generated a quiz set (1 Jolt)');

    // 3. Trigger Generation
    $userId = $user ? $user->getUserId() : null;
    $response = Quiz::startGeneration($topicId, $subtopicId, $diff, $userId);
    
    // Add updated balance to response
    $response['new_jolt'] = ($stats['jolt'] ?? 1) - 1;
    
    api_json($response, 200);
} catch (Exception $e) {
    api_json(['error' => errors_from_exception($e, 'quiz/generate')['error']], 500);
}
