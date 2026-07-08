<?php
/**
 * Learn AI - Content Generate API
 * Triggers AI chapter content generation via RabbitMQ queue 'ai_content_jobs'
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
$userId = (int)$user->getUserId();

// 2. Get Input
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$chapterId = $input['chapter_id'] ?? '';
$customPrompt = trim($input['custom_prompt'] ?? '');
$sessionId = $input['session_id'] ?? uniqid('sess_');
$messageId = $input['message_id'] ?? uniqid('msg_');

if (empty($chapterId)) {
    http_response_code(400);
    echo json_encode(['error' => 'chapter_id is required']);
    exit;
}

try {
    $rabbit = new RabbitClient();
    $job = [
        'session_id' => $sessionId,
        'message_id' => $messageId,
        'user_id' => $userId,
        'chapter_id' => $chapterId,
        'custom_prompt' => $customPrompt,
        'timestamp' => time()
    ];

    $rabbit->sendToQueue('ai_content_jobs', $job);

    echo json_encode([
        'status' => 'success',
        'message' => 'Content generation job queued',
        'session_id' => $sessionId,
        'message_id' => $messageId
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to queue content job: ' . $e->getMessage()]);
}
