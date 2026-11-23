<?php
require_once '../../load.php';
require_once __DIR__ . '/../../utils/ratelimit.php';

use TomLabs\Labs\Quiz;

$user = AuthMiddleware::requireAuth();
AuthMiddleware::requireCsrf();

// Rate limit: 60 answer checks per minute per user (prevents brute-force probing)
rate_limit('quiz:check_answer', 60, 60);

$quizHash = $_POST['hash'] ?? null;
$questionIndex = $_POST['question_index'] ?? null;
$selectedOption = $_POST['selected_option'] ?? null;

if ($quizHash === null || $questionIndex === null || $selectedOption === null) {
    api_json(['status' => 'error', 'message' => 'Missing parameters'], 400);
}

$quiz = Quiz::getByHash($quizHash);

if (!$quiz) {
    api_json(['status' => 'error', 'message' => 'Quiz not found'], 400);
}

$quizData = $quiz['questions'] ?? $quiz['content'] ?? [];
$total = count($quizData);

// question_index must be a valid integer within the quiz
$qIdx = (int)$questionIndex;
if ((string)$qIdx !== (string)$questionIndex && $questionIndex !== '0') {
    api_json(['status' => 'error', 'message' => 'Invalid question index'], 400);
}
if (!isset($quizData[$qIdx])) {
    api_json(['status' => 'error', 'message' => 'Invalid question index: ' . $qIdx], 400);
}

$q = $quizData[$qIdx];
$correctOption = $q['correct'] ?? -1;
$isCorrect = (int)$selectedOption === (int)$correctOption;

$correctMessages = [
    "Outstanding! Your technical intuition is razor-sharp! ⚡",
    "Brilliant! You've successfully decoded that complexity. 🧠",
    "Legendary! That's how a true Ninja approaches a challenge. 🥷",
    "Exceptional work! You're dominating this topic. 🔥",
    "Perfect! Your understanding of this domain is impressive. 💎",
    "Boom! Knowledge is power, and you've got plenty of it! 🚀",
    "Spot on! You make even the toughest problems look easy. ✨",
    "Incredible! Your skills are evolving at a rapid pace. 📈",
    "Absolute mastery! Keep this momentum going. 👑",
    "Phenomenal! That's another milestone conquered. 🚩",
    "Great job! You're building a formidable expertise here. 🛡️",
    "Fantastic! Your attention to detail is your superpower. 🔍",
    "Superb! You've got the logic of a high-performance machine. 🤖",
    "Magnificent! Your technical growth is inspiring to watch. 🌟",
    "Top-tier performance! You're reaching new heights today. 🏔️",
    "Majestic! Your progress is a testament to your dedication. 🏛️",
    "Victory! You've claimed another triumph in this arena. ⚔️"
];

$wrongMessages = [
    "Not quite, but every mistake is a step toward mastery! 📚",
    "Close! Re-analyze the logic and you'll crush it next time. 🔄",
    "Don't stop now! Even the best Ninjas fail before they succeed. 🥷",
    "A tough one, but your persistence is what counts. Keep going! 💪",
    "Mistakes are just data points for your future success. 📊",
    "Keep your head up! You're learning things most people don't. 🎓",
    "Almost there! Refine your approach and try again. 🛠️",
    "That was a tricky one. Take a breath and dive back in! 🌊",
    "Failure is the mother of success. Stay hungry! 🍕",
    "Every wrong answer is an opportunity to strengthen your mind. 🧠",
    "Consistency is key! You'll get it on the next attempt. 🔑",
    "Don't be discouraged. The path to expert is paved with errors. 🛣️",
    "Stay focused! You're much closer than you think. 🎯",
    "It's a learning curve, and you're already halfway up! 🎢",
    "Analyze, adapt, and overcome. You've got this! 🦾",
    "Keep swinging! Each miss makes you stronger for the hit. ⚾",
    "Persistence is your ally. One more try might be the one! 🔄"
];

$toastMessage = $isCorrect
    ? $correctMessages[array_rand($correctMessages)]
    : $wrongMessages[array_rand($wrongMessages)];
$userAnswersJson = $_POST['answers'] ?? '[]';
$userAnswers = json_decode($userAnswersJson, true) ?? [];

$answersArray = [];
$correctCount = 0;
$totalAnswers = 0;
foreach ($quizData as $idx => $qa) {
    if (isset($userAnswers[$idx])) {
        $totalAnswers++;
        $isAnsCorrect = (int)$userAnswers[$idx] === (int)($qa['correct'] ?? -1);
        $answersArray[] = $isAnsCorrect;
        if ($isAnsCorrect) $correctCount++;
    } else {
        $answersArray[] = false;
    }
}

$parentTopic = Session::get('parent_topic');
$majorId = $parentTopic ? ($parentTopic['id'] ?? $parentTopic['_id'] ?? 'unknown') : 'unknown';

api_json([
    'status' => 'success',
    'correct' => $isCorrect,
    'toast_message' => $toastMessage,
    'toast_subtitle' => $isCorrect ? 'You got it right!' : 'Incorrect',
    'toast_title' => $isCorrect ? 'Correct Answer' : 'Keep Going',

    // Matching requested JSON structure
    'quiz_id' => $quizHash,
    'id' => $q['id'] ?? (string)$qIdx,
    'major_id' => (string)$majorId,
    'minor_id' => (string)($quiz['subtopic_id'] ?? 'unknown'),
    'for' => (int)$qIdx,
    'question_index' => (int)$qIdx,
    'question' => $q['text'] ?? $q['question'] ?? '',
    // NOTE: choices are returned so the client can render options; the correct
    // answer index is never leaked to the client.
    'choices' => $q['options'] ?? $q['answers'] ?? [],
    'finished' => false,
    'completed' => false,
    'started' => true,
    'started_at' => time() - 120, // Estimated start time
    'finished_at' => null,
    'rewarded' => false,
    'rewards' => [
        'jolt' => 0,
        'zeal' => 0
    ],
    'total_questions' => count($quizData),
    'total_answers' => $totalAnswers,
    'correct_answers' => $correctCount,
    'answers' => $answersArray
], 200);
