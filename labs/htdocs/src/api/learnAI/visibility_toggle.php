<?php
/**
 * Learn AI - Toggle Lesson Visibility API
 * Toggles a lesson's visibility between 'Public' and 'Private' for the authorized author
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
$currentEmail = $user ? $user->getEmail() : '';
$currentUserId = $user ? (int)$user->getUserId() : 0;

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$lessonId = trim($input['lesson_id'] ?? $_POST['lesson_id'] ?? '');
$targetVisibility = trim($input['visibility'] ?? $_POST['visibility'] ?? '');

if (empty($lessonId) || !in_array($targetVisibility, ['Public', 'Private'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid lesson_id or visibility']);
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

    $isAuthor = false;
    if (!empty($lesson['author']) && strcasecmp($lesson['author'], $currentUsername) === 0) {
        $isAuthor = true;
    } elseif (!empty($lesson['author_email']) && strcasecmp($lesson['author_email'], $currentEmail) === 0) {
        $isAuthor = true;
    } elseif (!empty($lesson['user_id']) && (int)$lesson['user_id'] === $currentUserId && $currentUserId > 0) {
        $isAuthor = true;
    }

    if (!$isAuthor) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden: Only the author can change lesson visibility']);
        exit;
    }

    $db->ai_lessons->updateOne(
        ['_id' => new MongoDB\BSON\ObjectId($lessonId)],
        ['$set' => ['visibility' => $targetVisibility]]
    );

    echo json_encode(['status' => 'success', 'visibility' => $targetVisibility, 'lesson_id' => $lessonId]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update visibility: ' . $e->getMessage()]);
}
