<?php
require_once '../../load.php';

use TomLabs\Labs\Quiz;

header('Content-Type: application/json');

if (Session::getUser()) {
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

                echo json_encode([
                    'status' => 'success',
                    'question' => $q
                ]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Invalid question index']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Quiz not found']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Missing parameters']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
}
