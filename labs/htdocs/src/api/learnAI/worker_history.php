<?php
/**
 * Learn AI Tool API - Worker History
 * Handles GET (fetch history) and POST (save history) for the stateless AI Worker
 */
require_once __DIR__ . '/../../load.php';

AuthMiddleware::requireInternalToken();

$method = $_SERVER['REQUEST_METHOD'];

try {
    $db = DatabaseConnection::getDefaultDatabase();
    
    if ($method === 'GET') {
        $userId = $_GET['user_id'] ?? null;
        $lessonId = $_GET['lesson_id'] ?? null;
        $chapterId = $_GET['chapter_id'] ?? null;
        
        if (!$userId) {
api_json(['error' => 'user_id required'], 400);
        }

        $filter = ['user_id' => (int)$userId];
        if ($lessonId) {
            $filter['lesson_id'] = $lessonId;
        } else if ($chapterId) {
            $filter['chapter_id'] = $chapterId;
        }
        
        $doc = $db->ai_chat_history->findOne($filter);
        $history = [];
        if ($doc && isset($doc['messages'])) {
            $messages = (array)$doc['messages'];
            $rag_summary = $doc['rag_summary'] ?? '';
            $last_summarized_index = $doc['last_summarized_index'] ?? 0;
            
            if ($rag_summary) {
                $history[] = ['role' => 'system_summary', 'content' => $rag_summary];
            }
            
            $unsummarized = array_slice($messages, $last_summarized_index);
            foreach ($unsummarized as $m) {
                if (($m['role'] ?? '') !== 'system_summary') {
                    $history[] = $m;
                }
            }
        }
        
api_json(['history' => $history], 200);
    }
    
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $userId = $input['user_id'] ?? null;
        $lessonId = $input['lesson_id'] ?? null;
        $chapterId = $input['chapter_id'] ?? null;
        $query = $input['query'] ?? '';
        $response = $input['response'] ?? '';
        $usage = $input['usage'] ?? [];
        $tools = $input['tools'] ?? [];
        $ts = time();
        
        if (!$userId || empty($query) || empty($response)) {
api_json(['error' => 'Missing required fields'], 400);
        }
        
        $filter = ['user_id' => (int)$userId];
        if ($lessonId) {
            $filter['lesson_id'] = $lessonId;
        } else if ($chapterId) {
            $filter['chapter_id'] = $chapterId;
        }

        $db->ai_chat_history->updateOne(
            $filter,
            ['$push' => [
                'messages' => [
                    '$each' => [
                        ['role' => 'user', 'content' => $query, 'timestamp' => $ts],
                        ['role' => 'model', 'content' => $response, 'timestamp' => $ts + 1, 'usage' => $usage, 'tools' => $tools]
                    ]
                ]
            ]],
            ['upsert' => true]
        );
        
api_json(['status' => 'success'], 200);
    }
} catch (Exception $e) {
    api_json(['error' => 'Database error: ' . errors_from_exception($e, 'learnAI/worker_history')['error']], 500);
}
