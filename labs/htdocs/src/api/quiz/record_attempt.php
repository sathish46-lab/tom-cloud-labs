<?php
require_once '../../load.php';

use TomLabs\Labs\Quiz;

header('Content-Type: application/json');

if (Session::getUser()) {
    $userEmail = Session::getUser()->getEmail();
    $quizHash = $_POST['hash'] ?? null;
    $answersJson = $_POST['answers'] ?? '[]';
    $status = $_POST['status'] ?? 'completed';

    if ($quizHash && $userEmail) {
        $db = DatabaseConnection::getDefaultDatabase();
        
        // SECURE QUIZ FIX: Calculate score entirely on the server
        $score = 0;
        $total = 0;
        $quiz = Quiz::getByHash($quizHash);
        
        if ($quiz) {
            $quizData = $quiz['questions'] ?? $quiz['content'] ?? [];
            $total = count($quizData);
            $userAnswers = json_decode($answersJson, true) ?? [];
            
            foreach ($quizData as $idx => $q) {
                if (isset($userAnswers[$idx]) && (int)$userAnswers[$idx] === (int)($q['correct'] ?? -1)) {
                    $score++;
                }
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Quiz not found']);
            exit;
        }

        // Check if user has ever COMPLETED this quiz before
        $hasCompletedBefore = $db->quiz_attempts->findOne([
            'user_email' => $userEmail,
            'quiz_hash' => $quizHash,
            'status' => 'completed'
        ]) !== null;
        
        // Record the attempt
        $result = Quiz::recordAttempt($userEmail, $quizHash, $score, $total, $status);
        
        $rewarded = false;
        $zealEarned = 0;
        $joltEarned = 0;

        // Perfect score on the VERY FIRST completion gets rewards
        if (!$hasCompletedBefore && $status === 'completed' && (int)$score === (int)$total && $total > 0) {
            if ($quiz) {
                $diff = strtolower($quiz['difficulty'] ?? 'normal');
                
                // Base Points per difficulty
                $basePoints = 25; // Default Normal
                if ($diff === 'easy') $basePoints = 15;
                elseif ($diff === 'hard') $basePoints = 50;
                
                $pointsPerCorrect = $quiz['points_per_correct'] ?? $basePoints;
                
                // Zeal is based on points_per_correct * total
                $zealEarned = (int)$pointsPerCorrect * (int)$total;

                // Jolt remains based on difficulty
                if ($diff === 'easy') { $joltEarned = 1; }
                elseif ($diff === 'hard') { $joltEarned = 5; }
                else { $joltEarned = 2; } // Normal

                Quiz::updateUserStats($userEmail, $zealEarned, $joltEarned);
                $rewarded = true;
            } else {
                error_log("[Quiz API] Failed to find quiz for reward: $quizHash");
            }
        }

        // Get updated stats for the header
        $newStats = Quiz::getUserStats($userEmail);

        echo json_encode([
            'status' => 'success', 
            'message' => 'Attempt recorded',
            'rewarded' => $rewarded,
            'zeal' => $zealEarned,
            'jolt' => $joltEarned,
            'total_zeal' => $newStats['zeal'],
            'total_jolt' => $newStats['jolt']
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Missing quiz hash']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
}
