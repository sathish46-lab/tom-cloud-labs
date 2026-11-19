<?php
/**
 * Learn AI - Content Generate API
 * Triggers AI chapter content generation via RabbitMQ queue 'ai_content_jobs'
 */
require_once __DIR__ . '/../../load.php';
require_once __DIR__ . '/../../lib/core/RabbitClient.class.php';

// 1. Validate Session
$user = AuthMiddleware::requireAuth();

$userId = (int)$user->getUserId();

// 2. Get Input
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$chapterId = $input['chapter_id'] ?? '';
$customPrompt = trim($input['custom_prompt'] ?? '');
$sessionId = $input['session_id'] ?? uniqid('sess_');
$messageId = $input['message_id'] ?? uniqid('msg_');

if (empty($chapterId)) {
api_json(['error' => 'chapter_id is required'], 400);
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

    api_json([
        'status' => 'success',
        'message' => 'Content generation job queued',
        'session_id' => $sessionId,
        'message_id' => $messageId
    ], 200);
} catch (Exception $e) {
    api_json(['error' => 'Failed to queue content job: ' . errors_from_exception($e, 'learnAI/content_generate')['error']], 500);
}
