<?php
/**
 * Learn AI Tool API - Read Student Progress
 * Called internally by ai_worker.py
 */
require_once __DIR__ . '/../../../load.php';

header('Content-Type: application/json');

// 1. Verify Internal Token
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';
$internalToken = null;

$envPath = __DIR__ . '/../../../../../../env.json';
if (file_exists($envPath)) {
    $env = json_decode(file_get_contents($envPath), true);
    $internalToken = $env['ai_internal_token'] ?? null;
}

if (!$internalToken || $authHeader !== "Bearer $internalToken") {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized access."]);
    exit;
}

// 2. Parse payload
$input = json_decode(file_get_contents('php://input'), true);
$userId = $input['user_id'] ?? null;
$lessonId = $input['lesson_id'] ?? null;

if (!$userId || !$lessonId) {
    http_response_code(400);
    echo json_encode(["error" => "user_id and lesson_id are required"]);
    exit;
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

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}
