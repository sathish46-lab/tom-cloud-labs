<?php
/**
 * Learn AI - Unlock AI Assist API
 * Unlocks AI Assist for a non-author viewing a public lesson using 25 Jolt fuel
 */
require_once __DIR__ . '/../../load.php';

header('Content-Type: application/json');

if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user = Session::getUser();
$userId = (int)$user->getUserId();
$userEmail = $user->getEmail();
$username = $user->getUsername();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$lessonId = trim($input['lesson_id'] ?? $_POST['lesson_id'] ?? '');

if (empty($lessonId)) {
    http_response_code(400);
    echo json_encode(['error' => 'lesson_id is required']);
    exit;
}

try {
    $db = DatabaseConnection::getDefaultDatabase();
    $lesson = $db->ai_lessons->findOne(['_id' => new MongoDB\BSON\ObjectId($lessonId)]);
    if (!$lesson) {
        http_response_code(404);
        echo json_encode(['error' => 'Lesson not found']);
        exit;
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
        echo json_encode(['status' => 'success', 'message' => 'Lesson owner already has free access']);
        exit;
    }

    // Check if already unlocked
    $existingUnlock = $db->ai_unlocked_lessons->findOne(['user_id' => $userId, 'lesson_id' => (string)$lessonId]);
    if ($existingUnlock) {
        echo json_encode(['status' => 'success', 'message' => 'AI Assist already unlocked for this lesson']);
        exit;
    }

    // Check Jolt fuel (needs 25 Jolt)
    $stats = \TomLabs\Labs\Quiz::getUserStats($userEmail);
    $currentJolt = (int)($stats['jolt'] ?? 0);
    $requiredJolt = 25;

    if ($currentJolt < $requiredJolt) {
        http_response_code(402);
        echo json_encode([
            'error' => "Insufficient Jolt fuel. You need {$requiredJolt} Jolt to unlock AI Assist, but currently have {$currentJolt} Jolt. Complete challenges or quizzes to earn more fuel!",
            'current_jolt' => $currentJolt,
            'required_jolt' => $requiredJolt
        ]);
        exit;
    }

    // Deduct 25 Jolt and record unlock
    \TomLabs\Labs\Quiz::updateUserStats($userEmail, 0, -$requiredJolt);
    $db->ai_unlocked_lessons->updateOne(
        ['user_id' => $userId, 'lesson_id' => (string)$lessonId],
        ['$set' => ['unlocked_at' => time()]],
        ['upsert' => true]
    );

    $updatedStats = \TomLabs\Labs\Quiz::getUserStats($userEmail);
    $newJolt = (int)($updatedStats['jolt'] ?? ($currentJolt - $requiredJolt));

    echo json_encode([
        'status' => 'success',
        'message' => 'AI Assist successfully unlocked!',
        'new_jolt' => $newJolt,
        'lesson_id' => $lessonId
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to unlock AI Assist: ' . $e->getMessage()]);
}
