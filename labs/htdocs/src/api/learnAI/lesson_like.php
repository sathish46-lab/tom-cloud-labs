<?php
/**
 * Learn AI - Lesson Like API
 * Toggles like/unlike status for a lesson and stores username + timestamp in database.
 */
require_once __DIR__ . '/../../load.php';

$user = AuthMiddleware::requireAuth();

$currentUsername = $user ? $user->getUsername() : '';
$currentUserId = $user ? (int)$user->getUserId() : 0;

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$lessonId = trim($input['lesson_id'] ?? $_POST['lesson_id'] ?? '');

if (empty($lessonId)) {
api_json(['error' => 'Invalid lesson_id'], 400);
}

try {
    $db = DatabaseConnection::getDefaultDatabase();
    $objectId = new MongoDB\BSON\ObjectId($lessonId);
    $lesson = $db->ai_lessons->findOne(['_id' => $objectId]);

    if (!$lesson) {
api_json(['error' => 'Lesson not found'], 404);
    }

    $likesArray = $lesson['likes'] ?? [];
    if (!is_array($likesArray)) {
        if (is_object($likesArray)) {
            $likesArray = method_exists($likesArray, 'getArrayCopy') ? $likesArray->getArrayCopy() : (array)$likesArray;
        } else {
            $likesArray = [];
        }
    }

    $existingLike = $db->ai_lesson_likes->findOne([
        'lesson_id' => $lessonId,
        'username' => $currentUsername
    ]);

    $isCurrentlyLiked = ($existingLike || in_array($currentUsername, $likesArray));

    if ($isCurrentlyLiked) {
        // Unlike action
        $db->ai_lesson_likes->deleteOne([
            'lesson_id' => $lessonId,
            'username' => $currentUsername
        ]);
        $db->ai_lessons->updateOne(
            ['_id' => $objectId],
            ['$pull' => ['likes' => $currentUsername]]
        );

        $likesCount = $db->ai_lesson_likes->countDocuments(['lesson_id' => $lessonId]);
        $db->ai_lessons->updateOne(
            ['_id' => $objectId],
            ['$set' => ['likes_count' => $likesCount]]
        );

        api_json([
            'result' => 'success',
            'liked' => false,
            'action' => 'unliked',
            'like_count' => $likesCount,
            'total_likes' => $likesCount,
            'message' => 'Lesson unliked!'
        ], 200);
    } else {
        // Like action
        $db->ai_lesson_likes->insertOne([
            'lesson_id' => $lessonId,
            'username' => $currentUsername,
            'user_id' => $currentUserId,
            'liked_at' => date('c')
        ]);
        $db->ai_lessons->updateOne(
            ['_id' => $objectId],
            ['$addToSet' => ['likes' => $currentUsername]]
        );

        $likesCount = $db->ai_lesson_likes->countDocuments(['lesson_id' => $lessonId]);
        $db->ai_lessons->updateOne(
            ['_id' => $objectId],
            ['$set' => ['likes_count' => $likesCount]]
        );

        api_json([
            'result' => 'success',
            'liked' => true,
            'action' => 'liked',
            'like_count' => $likesCount,
            'total_likes' => $likesCount,
            'message' => 'Lesson liked!'
        ], 200);
    }
} catch (Throwable $e) {
    api_json(['error' => 'Failed to process like: ' . errors_from_exception($e, 'learnAI/lesson_like')['error']], 500);
}
