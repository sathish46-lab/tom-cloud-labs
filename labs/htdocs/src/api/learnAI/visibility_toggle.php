<?php
/**
 * Learn AI - Toggle Lesson Visibility API
 * Toggles a lesson's visibility between 'Public' and 'Private' for the authorized author
 */
require_once __DIR__ . '/../../load.php';

$user = AuthMiddleware::requireAuth();

$currentUsername = $user ? $user->getUsername() : '';
$currentEmail = $user ? $user->getEmail() : '';
$currentUserId = $user ? (int)$user->getUserId() : 0;

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$lessonId = trim($input['lesson_id'] ?? $_POST['lesson_id'] ?? '');
$targetVisibility = trim($input['visibility'] ?? $_POST['visibility'] ?? '');

if (empty($lessonId) || !in_array($targetVisibility, ['Public', 'Private'])) {
api_json(['error' => 'Invalid lesson_id or visibility'], 400);
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
api_json(['error' => 'Forbidden: Only the author can change lesson visibility'], 403);
    }

    $db->ai_lessons->updateOne(
        ['_id' => new MongoDB\BSON\ObjectId($lessonId)],
        ['$set' => ['visibility' => $targetVisibility]]
    );

    api_json(['status' => 'success', 'visibility' => $targetVisibility, 'lesson_id' => $lessonId], 200);
} catch (Exception $e) {
    api_json(['error' => 'Failed to update visibility: ' . errors_from_exception($e, 'learnAI/visibility_toggle')['error']], 500);
}
