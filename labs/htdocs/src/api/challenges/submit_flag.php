<?php
require_once __DIR__ . '/../../load.php';

header('Content-Type: application/json');

if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    echo json_encode(['status' => 'error', 'error' => 'Unauthorized']); exit;
}

$user = Session::getUser();
$challengeId = $_POST['challenge_id'] ?? null;
$instanceHash = $_POST['hash'] ?? null;
$submittedFlag = isset($_POST['flag']) ? trim($_POST['flag']) : null;

if (!$challengeId || !$instanceHash || !$submittedFlag) {
    echo json_encode(['status' => 'error', 'error' => 'Missing parameters']); exit;
}

try {
    $db = DatabaseConnection::getClient()->selectDatabase('tom_labs_db');
    
    // Check if the instance belongs to the user
    $instance = $db->challenge_instances->findOne([
        'instance_hash' => $instanceHash,
        'username' => $user->getUsername()
    ]);
    
    if (!$instance) {
        echo json_encode([
            'status' => 'error', 
            'error' => 'Challenge instance not found.'
        ]); 
        exit;
    }
    
    // Retrieve the correct flag
    $correctFlag = $instance['flag'] ?? '';
    
    if (empty($correctFlag)) {
        echo json_encode(['status' => 'error', 'error' => 'No active flag found for this challenge.']); exit;
    }
    
    $challengesCompleted = $instance['challenges_completed'] ?? 0;
    
    // Compare flag
    if ($submittedFlag === trim($correctFlag)) {
        if ($challengesCompleted > 0) {
            // Already completed once before - practice mode, do not reward points again
            $db->challenge_instances->updateOne(
                ['instance_hash' => $instanceHash],
                ['$set' => [
                    'mission_started' => false
                ]]
            );
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Correct flag! (Practice Mode - No extra points awarded)'
            ]);
        } else {
            // First time completing! Award full rewards
            $failedAttempts = (int)($instance['failed_attempts'] ?? 0);
            $completedOnAttempt = $failedAttempts + 1;
            
            // Retrieve task zeal and jolt dynamically from challenge_tasks.json config
            $baseZeal = 500;
            $joltEarned = 10;
            $tasksJsonPath = __DIR__ . '/../../config/challenge_tasks.json';
            if (file_exists($tasksJsonPath)) {
                $tasksData = json_decode(file_get_contents($tasksJsonPath), true) ?? [];
                $normalizedId = str_replace('_', '-', $challengeId);
                $tasksList = $tasksData[$challengeId] ?? $tasksData[$normalizedId] ?? [];
                if (!empty($tasksList)) {
                    $baseZeal = (int)($tasksList[0]['zeal'] ?? 500);
                    $joltEarned = (int)($tasksList[0]['jolt'] ?? 10);
                }
            }
            
            $multiplier = 1.5 * pow(0.75, $failedAttempts);
            $zealEarned = (int)round($baseZeal * $multiplier);
            
            // Credit points to user stats!
            \TomLabs\Labs\Quiz::updateUserStats($user->getEmail(), $zealEarned, $joltEarned);
            
            $db->challenge_instances->updateOne(
                ['instance_hash' => $instanceHash],
                ['$set' => [
                    'challenges_completed' => 1,
                    'status' => 'completed',
                    'completed_at' => time(),
                    'mission_started' => false,
                    'completed_on_attempt' => $completedOnAttempt,
                    'zeal_earned' => $zealEarned,
                    'jolt_earned' => $joltEarned
                ]]
            );
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Congratulations! Correct flag submitted. ' . $zealEarned . ' Zeal credited!'
            ]);
        }
    } else {
        // Increment failed attempts and stop the active challenge session (but keep lab running)
        $failedAttempts = (int)($instance['failed_attempts'] ?? 0) + 1;
        
        // Retrieve task zeal value dynamically
        $baseZeal = 500;
        $tasksJsonPath = __DIR__ . '/../../config/challenge_tasks.json';
        if (file_exists($tasksJsonPath)) {
            $tasksData = json_decode(file_get_contents($tasksJsonPath), true) ?? [];
            $normalizedId = str_replace('_', '-', $challengeId);
            $tasksList = $tasksData[$challengeId] ?? $tasksData[$normalizedId] ?? [];
            if (!empty($tasksList)) {
                $baseZeal = (int)($tasksList[0]['zeal'] ?? 500);
            }
        }
        
        $multiplier = 1.5 * pow(0.75, $failedAttempts);
        $zealEarned = (int)round($baseZeal * $multiplier);
        
        $db->challenge_instances->updateOne(
            ['instance_hash' => $instanceHash],
            [
                '$inc' => ['failed_attempts' => 1],
                '$set' => [
                    'mission_started' => false,
                    'zeal_earned' => $zealEarned
                ]
            ]
        );
        
        echo json_encode([
            'status' => 'error',
            'error' => 'Incorrect flag. Attempt counted! Multiplier decreased by 25%.'
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => $e->getMessage()]);
}
