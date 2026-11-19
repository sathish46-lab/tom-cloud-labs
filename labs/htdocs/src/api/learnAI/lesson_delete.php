<?php
/**
 * Learn AI - Lesson Delete API
 * Deletes a lesson and all its associated chapters for the authorized author
 */
require_once __DIR__ . '/../../load.php';

$user = AuthMiddleware::requireAuth();

$currentUsername = $user ? $user->getUsername() : '';
$currentEmail = $user ? $user->getEmail() : '';
$currentUserId = $user ? (int)$user->getUserId() : 0;

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

    $isAuthor = false;
    if (!empty($lesson['author']) && strcasecmp($lesson['author'], $currentUsername) === 0) {
        $isAuthor = true;
    } elseif (!empty($lesson['author_email']) && strcasecmp($lesson['author_email'], $currentEmail) === 0) {
        $isAuthor = true;
    } elseif (!empty($lesson['user_id']) && (int)$lesson['user_id'] === $currentUserId && $currentUserId > 0) {
        $isAuthor = true;
    }

    if (!$isAuthor) {
api_json(['error' => 'Forbidden: Only the author can delete this lesson'], 403);
    }

    $db->ai_lessons->deleteOne(['_id' => new MongoDB\BSON\ObjectId($lessonId)]);
    $db->ai_chapters->deleteMany(['lesson_id' => new MongoDB\BSON\ObjectId($lessonId)]);

    api_json(['status' => 'success', 'lesson_id' => $lessonId], 200);
} catch (Exception $e) {
    api_json(['error' => 'Failed to delete lesson: ' . errors_from_exception($e, 'learnAI/lesson_delete')['error']], 500);
}
