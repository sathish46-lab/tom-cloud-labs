<?php
require_once '../../load.php';

use TomLabs\Labs\Quiz;

$user = AuthMiddleware::requireAuth();

$userEmail = $user->getEmail();
$quizHash = $_GET['hash'] ?? $_POST['hash'] ?? null;

if (!$quizHash || !$userEmail) {
    api_json(['status' => 'error', 'message' => 'Missing quiz hash'], 400);
}

$quiz = Quiz::getByHash($quizHash);

if (!$quiz) {
    api_json(['status' => 'error', 'message' => 'Quiz not found'], 400);
}

$db = DatabaseConnection::getDefaultDatabase();

// First completed attempt for this user + quiz (earliest completed timestamp)
$first = $db->quiz_attempts->findOne(
    [
        'user_email' => $userEmail,
        'quiz_hash'  => $quizHash,
        'status'     => 'completed',
    ],
    [
        'sort' => ['attempted_at' => 1],
    ]
);

if (!$first) {
    api_json([
        'status' => 'success',
        'has_completed' => false,
        'message' => 'No completed attempt found',
    ], 200);
}

$attemptedAt = (int)($first['attempted_at'] ?? 0);

api_json([
    'status' => 'success',
    'has_completed' => true,
    'quiz_hash' => $quizHash,
    'score' => (int)($first['score'] ?? 0),
    'total' => (int)($first['total'] ?? 0),
    'attempted_at' => $attemptedAt,
    'completed_at' => date('Y-m-d H:i:s', $attemptedAt),
], 200);
