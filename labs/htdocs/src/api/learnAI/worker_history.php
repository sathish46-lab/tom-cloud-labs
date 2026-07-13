<?php
/**
 * Learn AI Tool API - Worker History
 * Handles GET (fetch history) and POST (save history) for the stateless AI Worker
 */
require_once __DIR__ . '/../../load.php';

header('Content-Type: application/json');

// Internal API auth
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? ($headers['authorization'] ?? '');
$envConfig = json_decode(file_get_contents(__DIR__ . '/../../../../../env.json'), true);
$internalToken = $envConfig['ai_internal_token'] ?? '';

if (empty($internalToken) || $authHeader !== "Bearer {$internalToken}") {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'error' => 'Forbidden']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    $db = DatabaseConnection::getDefaultDatabase();
    
    if ($method === 'GET') {
        $userId = $_GET['user_id'] ?? null;
        $lessonId = $_GET['lesson_id'] ?? null;
        $chapterId = $_GET['chapter_id'] ?? null;
        
        if (!$userId) {
            http_response_code(400);
            echo json_encode(['error' => 'user_id required']);
            exit;
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
        
        echo json_encode(['history' => $history]);
        exit;
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
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields']);
            exit;
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
        
        echo json_encode(['status' => 'success']);
        exit;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
