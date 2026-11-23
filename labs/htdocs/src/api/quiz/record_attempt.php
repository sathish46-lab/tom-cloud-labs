<?php
require_once '../../load.php';
require_once __DIR__ . '/../../utils/ratelimit.php';

use TomLabs\Labs\Quiz;

$user = AuthMiddleware::requireAuth();
AuthMiddleware::requireCsrf();

$userEmail = $user->getEmail();
$quizHash = $_POST['hash'] ?? null;
$answersJson = $_POST['answers'] ?? '[]';
$clientStatus = $_POST['status'] ?? null; // 'started' or 'completed' (client hint only, not trusted)

// Rate limit: 15 attempts per 5 minutes per user
rate_limit('quiz:record_attempt', 15, 300);

if (!$quizHash || !$userEmail) {
    api_json(['status' => 'error', 'message' => 'Missing quiz hash'], 400);
}

$db = DatabaseConnection::getDefaultDatabase();
$quiz = Quiz::getByHash($quizHash);

if (!$quiz) {
    api_json(['status' => 'error', 'message' => 'Quiz not found'], 400);
}

$quizData = $quiz['questions'] ?? $quiz['content'] ?? [];
$total = (int)count($quizData);
$userAnswers = json_decode($answersJson, true) ?? [];

// Tag attribution for per-user tag analytics
$tagId = $quiz['tag_id'] ?? $quiz['category_id'] ?? null;
$tagTitle = $quiz['tag_title'] ?? $quiz['title'] ?? null;

// Validate answers is an array and every index references a real question
if (!is_array($userAnswers)) {
    api_json(['status' => 'error', 'message' => 'Invalid answers format'], 400);
}

$answeredCount = 0;
foreach ($userAnswers as $idx => $ans) {
    $idx = (int)$idx;
    if (!isset($quizData[$idx])) {
        api_json(['status' => 'error', 'message' => 'Invalid question index: ' . $idx], 400);
    }
    // Validate the selected option index is within the range of available choices
    $choices = $quizData[$idx]['options'] ?? $quizData[$idx]['answers'] ?? [];
    if ($ans !== null && $ans !== '' && !is_numeric($ans)) {
        api_json(['status' => 'error', 'message' => 'Invalid option for question ' . $idx], 400);
    }
    $optIndex = (int)$ans;
    if ($optIndex < 0 || $optIndex >= count($choices)) {
        api_json(['status' => 'error', 'message' => 'Option out of range for question ' . $idx], 400);
    }
    $answeredCount++;
}

// Derive status server-side: 'completed' only when every question has an answer.
// Never trust client-sent status for reward eligibility.
$isCompleteSubmission = ($total > 0 && $answeredCount >= $total);
$status = $isCompleteSubmission ? 'completed' : 'started';

// Score is computed entirely server-side from the authoritative quiz data.
$score = 0;
foreach ($quizData as $qIdx => $q) {
    if (isset($userAnswers[$qIdx]) && (int)$userAnswers[$qIdx] === (int)($q['correct'] ?? -1)) {
        $score++;
    }
}

    // Record the attempt
    $result = Quiz::recordAttempt($userEmail, $quizHash, $score, $total, $status, $tagId, $tagTitle);

$rewarded = false;
$zealEarned = 0;
$joltEarned = 0;

// Reward: first perfect-score completion only. A prior *imperfect* completion must
// not permanently block a later perfect-score reward, so we check for a previous
// *perfect* (score === total) completion rather than any completion.
$hasPerfectBefore = $db->quiz_attempts->findOne([
    'user_email' => $userEmail,
    'quiz_hash' => $quizHash,
    'status' => 'completed',
    'score' => ['$eq' => $total],
]) !== null;

if (!$hasPerfectBefore && $status === 'completed' && (int)$score === $total && $total > 0) {
    $diff = strtolower($quiz['difficulty'] ?? 'normal');
    $basePoints = 25; // Default Normal
    if ($diff === 'easy') $basePoints = 15;
    elseif ($diff === 'hard') $basePoints = 50;

    $pointsPerCorrect = $quiz['points_per_correct'] ?? $basePoints;
    $zealEarned = (int)$pointsPerCorrect * (int)$total;

    if ($diff === 'easy') { $joltEarned = 1; }
    elseif ($diff === 'hard') { $joltEarned = 5; }
    else { $joltEarned = 2; } // Normal

    Quiz::updateUserStats(
        $userEmail,
        $zealEarned,
        $joltEarned,
        'Quiz Completion',
        trim(($quiz['title'] ?? $quiz['topic'] ?? 'Quiz') . ' · ' . $score . '/' . $total)
    );
    $rewarded = true;
}

// Get updated stats for the header
$newStats = Quiz::getUserStats($userEmail);

api_json([
    'status' => 'success',
    'message' => 'Attempt recorded',
    'rewarded' => $rewarded,
    'zeal' => $zealEarned,
    'jolt' => $joltEarned,
    'total_zeal' => $newStats['zeal'],
    'total_jolt' => $newStats['jolt']
], 200);
