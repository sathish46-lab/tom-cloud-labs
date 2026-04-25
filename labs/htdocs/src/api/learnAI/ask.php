<?php
/**
 * Learn AI - Ask AI API
 * Triggers AI content generation and streaming
 */
require_once __DIR__ . '/../../load.php';
require_once __DIR__ . '/../../lib/core/RabbitClient.class.php';

header('Content-Type: application/json');

// 1. Validate Session
if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user = Session::getUser();
$userId = (int)$user->getUserId(); // Always cast to int for consistent MongoDB typing

// 2. Get Input
$input = json_decode(file_get_contents('php://input'), true);
$query = $input['query'] ?? '';
$chapterId = $input['chapter_id'] ?? '';
// Normalize: empty/null chapter_id becomes '' for consistent matching
if (empty($chapterId)) $chapterId = '';
$aiModel   = $input['ai_model'] ?? 'gemini';
$sessionId = $input['session_id'] ?? uniqid('sess_');
$messageId = $input['message_id'] ?? uniqid('msg_');

if (empty($query)) {
    http_response_code(400);
    echo json_encode(['error' => 'Query is required']);
    exit;
}

try {
    // 3. Prepare RabbitMQ Job
    $rabbit = new RabbitClient();
    $job = [
        'session_id' => $sessionId,
        'message_id' => $messageId,
        'user_id' => $userId,
        'chapter_id' => $chapterId,
        'query' => $query,
        'ai_model' => $aiModel,
        'timestamp' => time()
    ];

    // Push to ai_jobs queue
    $rabbit->sendToQueue('ai_jobs', $job);

    echo json_encode([
        'status' => 'success',
        'message' => 'AI job queued',
        'session_id' => $sessionId,
        'message_id' => $messageId
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to queue AI job: ' . $e->getMessage()]);
}
