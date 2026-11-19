<?php
/**
 * Learn AI - Unlock AI Assist API
 * Unlocks AI Assist for a non-author viewing a public lesson using 25 Jolt fuel
 */
require_once __DIR__ . '/../../load.php';

$user = AuthMiddleware::requireAuth();

$userId = (int)$user->getUserId();
$userEmail = $user->getEmail();
$username = $user->getUsername();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$lessonId = trim($input['lesson_id'] ?? $_POST['lesson_id'] ?? '');

if (empty($lessonId)) {
api_json(['error' => 'lesson_id is required'], 400);
}

try {
    $db = DatabaseConnection::getDefaultDatabase();
    $lesson = $db->ai_lessons->findOne(['_id' => new MongoDB\BSON\ObjectId($lessonId)]);
    if (!$lesson) {
api_json(['error' => 'Lesson not found'], 404);
    }

    // Check if user is already author
    $isAuthor = false;
    if (!empty($lesson['author']) && strcasecmp($lesson['author'], $username) === 0) {
        $isAuthor = true;
    } elseif (!empty($lesson['author_email']) && strcasecmp($lesson['author_email'], $userEmail) === 0) {
        $isAuthor = true;
    } elseif (!empty($lesson['user_id']) && (int)$lesson['user_id'] === $userId && $userId > 0) {
        $isAuthor = true;
    }

    if ($isAuthor) {
api_json(['status' => 'success', 'message' => 'Lesson owner already has free access'], 200);
    }

    // Check if already unlocked
    $existingUnlock = $db->ai_unlocked_lessons->findOne(['user_id' => $userId, 'lesson_id' => (string)$lessonId]);
    if ($existingUnlock) {
api_json(['status' => 'success', 'message' => 'AI Assist already unlocked for this lesson'], 200);
    }

    // Check Jolt fuel (needs 25 Jolt)
    $stats = \TomLabs\Labs\Quiz::getUserStats($userEmail);
    $currentJolt = (int)($stats['jolt'] ?? 0);
    $requiredJolt = 25;

    if ($currentJolt < $requiredJolt) {
        api_json([
            'error' => "Insufficient Jolt fuel. You need {$requiredJolt} Jolt to unlock AI Assist, but currently have {$currentJolt} Jolt. Complete challenges or quizzes to earn more fuel!",
            'current_jolt' => $currentJolt,
            'required_jolt' => $requiredJolt
        ], 402);
        exit;
    }

    // Deduct 25 Jolt and record unlock
    \TomLabs\Labs\Quiz::updateUserStats(
        $userEmail, 0, -$requiredJolt,
        'AI Assist',
        'Unlocked AI Assist for a lesson (' . $requiredJolt . ' Jolt)'
    );
    $db->ai_unlocked_lessons->updateOne(
        ['user_id' => $userId, 'lesson_id' => (string)$lessonId],
        ['$set' => ['unlocked_at' => time()]],
        ['upsert' => true]
    );

    $updatedStats = \TomLabs\Labs\Quiz::getUserStats($userEmail);
    $newJolt = (int)($updatedStats['jolt'] ?? ($currentJolt - $requiredJolt));

    api_json([
        'status' => 'success',
        'message' => 'AI Assist successfully unlocked!',
        'new_jolt' => $newJolt,
        'lesson_id' => $lessonId
    ], 200);
} catch (Exception $e) {
    api_json(['error' => 'Failed to unlock AI Assist: ' . errors_from_exception($e, 'learnAI/unlock_ai_assist')['error']], 500);
}
