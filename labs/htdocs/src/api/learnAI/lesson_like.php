<?php
/**
 * Learn AI - Lesson Like API
 * Toggles like/unlike status for a lesson and stores username + timestamp in database.
 */
require_once __DIR__ . '/../../load.php';

header('Content-Type: application/json');

if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user = Session::getUser();
$currentUsername = $user ? $user->getUsername() : '';
$currentUserId = $user ? (int)$user->getUserId() : 0;

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$lessonId = trim($input['lesson_id'] ?? $_POST['lesson_id'] ?? '');

if (empty($lessonId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid lesson_id']);
    exit;
}

try {
    $db = DatabaseConnection::getDefaultDatabase();
    $objectId = new MongoDB\BSON\ObjectId($lessonId);
    $lesson = $db->ai_lessons->findOne(['_id' => $objectId]);

    if (!$lesson) {
        http_response_code(404);
        echo json_encode(['error' => 'Lesson not found']);
        exit;
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

        echo json_encode([
            'result' => 'success',
            'liked' => false,
            'action' => 'unliked',
            'like_count' => $likesCount,
            'total_likes' => $likesCount,
            'message' => 'Lesson unliked!'
        ]);
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

        echo json_encode([
            'result' => 'success',
            'liked' => true,
            'action' => 'liked',
            'like_count' => $likesCount,
            'total_likes' => $likesCount,
            'message' => 'Lesson liked!'
        ]);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to process like: ' . $e->getMessage()]);
}
