<?php
/**
 * Learn AI - Chat History API
 * Returns chat history for a given chapter (AJAX endpoint)
 */
require_once __DIR__ . '/../../load.php';

header('Content-Type: text/html');

// 1. Validate Session
if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    http_response_code(401);
    echo '<div class="text-center p-3 text-danger small">Unauthorized.</div>';
    exit;
}

$user = Session::getUser();
$userId = (int)$user->getUserId();

// 2. Get parameters from query string
$lessonId = $_GET['lesson_id'] ?? '';
$chapterId = $_GET['chapter_id'] ?? '';

// 3. Return empty if no chapter is selected (Overview Page)
if (empty($chapterId)) {
    echo '';
    exit;
}

if (!empty($lessonId)) {
    try {
        $db = DatabaseConnection::getDefaultDatabase();
        $lessonDoc = $db->ai_lessons->findOne(['_id' => new MongoDB\BSON\ObjectId($lessonId)]);
        if ($lessonDoc) {
            $isAuthor = false;
            $currentUsername = $user->getUsername();
            $currentEmail = $user->getEmail();
            if (!empty($lessonDoc['author']) && strcasecmp($lessonDoc['author'], $currentUsername) === 0) {
                $isAuthor = true;
            } elseif (!empty($lessonDoc['author_email']) && strcasecmp($lessonDoc['author_email'], $currentEmail) === 0) {
                $isAuthor = true;
            } elseif (!empty($lessonDoc['user_id']) && (int)$lessonDoc['user_id'] === $userId && $userId > 0) {
                $isAuthor = true;
            }

            if (!$isAuthor) {
                $unlockCheck = $db->ai_unlocked_lessons->findOne(['user_id' => $userId, 'lesson_id' => (string)$lessonId]);
                if (!$unlockCheck) {
                    echo '';
                    exit;
                }
            }
        }
    } catch (Exception $e) {}
}

include __DIR__ . '/../../template/partials/learnAI/chat_history.php';
