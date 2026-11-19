<?php
require_once '../../load.php';

use TomLabs\Labs\Quiz;

$user = AuthMiddleware::requireAuth();

$quizHash = $_POST['hash'] ?? null;
$questionIndex = $_POST['question_index'] ?? null;

if ($quizHash && $questionIndex !== null) {
    $quiz = Quiz::getByHash($quizHash);
    
    if ($quiz) {
        $quizData = $quiz['questions'] ?? $quiz['content'] ?? [];
        if (isset($quizData[$questionIndex])) {
            $q = $quizData[$questionIndex];
            
            // CRITICAL SECURE FIX: Strip the correct answer so it never hits the client!
            if (isset($q['correct'])) unset($q['correct']);

            api_json([
                'status' => 'success',
                'question' => $q
            ], 200);
        } else {
            api_json(['status' => 'error', 'message' => 'Invalid question index'], 400);
        }
    } else {
        api_json(['status' => 'error', 'message' => 'Quiz not found'], 400);
    }
} else {
    api_json(['status' => 'error', 'message' => 'Missing parameters'], 400);
}
