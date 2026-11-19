<?php
/**
 * Learn AI Tool API - Read Student Progress
 * Called internally by ai_worker.py
 */
require_once __DIR__ . '/../../../load.php';

AuthMiddleware::requireInternalToken();

// Parse payload
$input = json_decode(file_get_contents('php://input'), true);
$userId = $input['user_id'] ?? null;
$lessonId = $input['lesson_id'] ?? null;

if (!$userId || !$lessonId) {
api_json(["error" => "user_id and lesson_id are required"], 400);
}

try {
    $db = DatabaseConnection::getDefaultDatabase();
    
    // Query MongoDB for student's chat interaction count
    $chatDoc = $db->ai_chat_history->findOne([
        "user_id" => (int)$userId,
        "lesson_id" => (string)$lessonId
    ]);

    $msgCount = 0;
    if ($chatDoc && isset($chatDoc['messages'])) {
        $msgCount = count((array)$chatDoc['messages']);
    }

    $ragSummaryExists = false;
    if ($chatDoc && !empty($chatDoc['rag_summary'])) {
        $ragSummaryExists = true;
    }

    $response = [
        "lesson_id" => $lessonId,
        "total_messages" => $msgCount,
        "has_history" => $msgCount > 0,
        "rag_summary_exists" => $ragSummaryExists
    ];

    api_json($response, 200);

} catch (Exception $e) {
    api_json(["error" => "Database error: " . errors_from_exception($e, 'learnAI/tools/read_progress')['error']], 500);
}
