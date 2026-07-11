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

// 2. Get Input & Session Context (Layer 2)
$input = json_decode(file_get_contents('php://input'), true);
$query = $input['query'] ?? '';
$lessonId  = $input['lesson_id'] ?? '';
$chapterId = $input['chapter_id'] ?? '';
if (empty($lessonId))  $lessonId  = '';
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
    $rabbit = new RabbitClient();
    $orchestrator = new LearnAIOrchestrator($userId, $user, $lessonId, $chapterId, $sessionId);

    // 3. LAYER 3: INTENT ROUTER (100% Local, Zero LLM API Cost)
    $deterministicAnswer = $orchestrator->route($query);

    if ($deterministicAnswer !== null) {
        // LAYER 4a: DETERMINISTIC PATH -> Stream local answer immediately via MQ (0 API cost)
        // 1. Persist deterministic exchange to MongoDB ai_chat_history
        try {
            $db = DatabaseConnection::getDefaultDatabase();
            $ts = time();
            $db->ai_chat_history->updateOne(
                ['user_id' => $userId, 'lesson_id' => (string)$lessonId],
                ['$push' => [
                    'messages' => [
                        '$each' => [
                            ['role' => 'user', 'content' => $query, 'timestamp' => $ts],
                            ['role' => 'model', 'content' => $deterministicAnswer, 'timestamp' => $ts + 1]
                        ]
                    ]
                ]],
                ['upsert' => true]
            );
        } catch (Exception $e) {}

        // 2. Stream raw markdown so the client renders it with marked.parse & Prism code highlighting
        $rabbit->sendMessage([
            'type' => 'text_delta',
            'data' => $deterministicAnswer
        ], "ai_stream.{$sessionId}");

        $rabbit->sendMessage([
            'type' => 'stream_end'
        ], "ai_stream.{$sessionId}");

        echo json_encode([
            'status' => 'success',
            'routed' => 'deterministic',
            'message' => 'Answered deterministically via local tools',
            'session_id' => $sessionId,
            'message_id' => $messageId
        ]);
        exit;
    }

    // 4. LAYER 4b: LLM ORCHESTRATOR -> Pre-fetch authoritative Layer 5 context locally
    $enrichedContext = $orchestrator->prepareLLMContext();

    $job = [
        'session_id'  => $sessionId,
        'message_id'  => $messageId,
        'user_id'     => $userId,
        'lesson_id'   => $lessonId,
        'chapter_id'  => $chapterId,
        'query'       => $query,
        'ai_model'    => $aiModel,
        'context'     => $enrichedContext,
        'timestamp'   => time()
    ];

    // Push to ai_jobs queue
    $rabbit->sendToQueue('ai_jobs', $job);

    echo json_encode([
        'status' => 'success',
        'routed' => 'llm_orchestrator',
        'message' => 'AI job queued with pre-fetched Layer 5 context',
        'session_id' => $sessionId,
        'message_id' => $messageId
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to process AI request: ' . $e->getMessage()]);
}
