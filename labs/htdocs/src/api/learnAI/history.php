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

include __DIR__ . '/../../template/partials/learnAI/chat_history.php';
