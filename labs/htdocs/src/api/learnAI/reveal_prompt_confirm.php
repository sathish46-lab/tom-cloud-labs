<?php
/**
 * Learn AI - Confirm Reveal Generation Prompt API
 * Deducts 1 Jolt fuel from non-author user and returns JSON confirmation status
 */
require_once __DIR__ . '/../../load.php';

$user = AuthMiddleware::requireAuth();

$userId = (int)$user->getUserId();
$userEmail = $user->getEmail();
$username = $user->getUsername();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$lessonId = trim($input['lesson_id'] ?? $_POST['lesson_id'] ?? $_GET['lesson_id'] ?? '');

if (empty($lessonId)) {
api_json(['error' => 'lesson_id is required'], 400);
}

try {
    $db = DatabaseConnection::getDefaultDatabase();
    $lesson = $db->ai_lessons->findOne(['_id' => new MongoDB\BSON\ObjectId($lessonId)]);
    if (!$lesson) {
api_json(['error' => 'Lesson not found'], 404);
    }

    // Check if user is author/owner
    $isAuthor = false;
    if (!empty($lesson['author']) && strcasecmp($lesson['author'], $username) === 0) {
        $isAuthor = true;
    } elseif (!empty($lesson['author_email']) && strcasecmp($lesson['author_email'], $userEmail) === 0) {
        $isAuthor = true;
    } elseif (!empty($lesson['user_id']) && (int)$lesson['user_id'] === $userId && $userId > 0) {
        $isAuthor = true;
    }

    // Check if already revealed
    $existingReveal = $db->ai_unlocked_prompts->findOne(['user_id' => $userId, 'lesson_id' => (string)$lessonId]);

    if ($isAuthor) {
        api_json([
            'already_revealed' => true,
            'free_access' => true,
            'message' => 'Owner access - free'
        ], 200);
        exit;
    }

    if ($existingReveal) {
        api_json([
            'already_revealed' => true,
            'free_access' => false,
            'message' => 'Already revealed'
        ], 200);
        exit;
    }

    $stats = \TomLabs\Labs\Quiz::getUserStats($userEmail);
    $currentJolt = (int)($stats['jolt'] ?? 0);

    if ($currentJolt < 1) {
        api_json([
            'error' => 'Insufficient Jolt fuel. You need 1 Jolt to reveal this prompt, but currently have ' . $currentJolt . ' Jolt. Complete challenges or quizzes to earn more fuel!',
            'current_jolt' => $currentJolt,
            'required_jolt' => 1
        ], 402);
        exit;
    }

    \TomLabs\Labs\Quiz::updateUserStats($userEmail, 0, -1, 'Hint', 'Revealed a prompt (1 Jolt)');
    $db->ai_unlocked_prompts->updateOne(
        ['user_id' => $userId, 'lesson_id' => (string)$lessonId],
        ['$set' => ['unlocked_at' => time()]],
        ['upsert' => true]
    );

    $updatedStats = \TomLabs\Labs\Quiz::getUserStats($userEmail);
    $newJolt = (int)($updatedStats['jolt'] ?? ($currentJolt - 1));

    api_json([
        'already_revealed' => true,
        'free_access' => false,
        'message' => 'Prompt revealed successfully!',
        'new_jolt' => $newJolt
    ], 200);
} catch (Exception $e) {
    api_json(['error' => 'Failed to confirm reveal: ' . errors_from_exception($e, 'learnAI/reveal_prompt_confirm')['error']], 500);
}
