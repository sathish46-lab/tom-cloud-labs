<?php
/**
 * Learn AI - Chat History API
 * Returns chat history for a given chapter (AJAX endpoint)
 */
require_once __DIR__ . '/../../load.php';

header('Content-Type: application/json');

// 1. Validate Session
if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user = Session::getUser();
$userId = (int)$user->getUserId();

// 2. Get chapter_id from query string
$chapterId = $_GET['chapter_id'] ?? '';
if (empty($chapterId)) $chapterId = '';

try {
    $db = DatabaseConnection::getDefaultDatabase();
    
    $chatDoc = $db->ai_chat_history->findOne([
        'user_id' => $userId,
        'chapter_id' => (string)$chapterId
    ]);
    
    $messages = [];
    $summary = null;
    
    if ($chatDoc && isset($chatDoc['messages']) && is_array($chatDoc['messages'])) {
        foreach ($chatDoc['messages'] as $m) {
            $msg = (array)$m;
            if (($msg['role'] ?? '') === 'system_summary') {
                $summary = [
                    'content' => $msg['content'] ?? '',
                    'summarized_count' => (int)($msg['summarized_count'] ?? 0),
                    'timestamp' => (int)($msg['timestamp'] ?? 0)
                ];
            } else {
                $messages[] = [
                    'role' => $msg['role'] ?? 'user',
                    'content' => $msg['content'] ?? '',
                    'timestamp' => (int)($msg['timestamp'] ?? 0)
                ];
            }
        }
    }
    
    // Sort messages chronologically
    usort($messages, function($a, $b) {
        return $a['timestamp'] - $b['timestamp'];
    });
    
    echo json_encode([
        'status' => 'success',
        'chapter_id' => $chapterId,
        'message_count' => count($messages),
        'has_summary' => $summary !== null,
        'summary' => $summary,
        'messages' => $messages
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch history: ' . $e->getMessage()]);
}
