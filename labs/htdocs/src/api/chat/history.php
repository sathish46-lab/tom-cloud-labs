<?php
/**
 * Chat history — GET /api/chat/history.php
 * Authenticated users only. Returns the last 50 messages (oldest first).
 * Deleted messages are excluded entirely.
 */

require_once __DIR__ . '/_chat_common.php';

$session = chat_require_user();
$db = chat_db();

$limit = 50;

try {
    $cursor = $db->chat_messages->find(
        ['deleted' => ['$ne' => true]],
        [
            'sort'  => ['created_at' => -1],
            'limit' => $limit,
        ]
    );

    $messages = [];
    foreach ($cursor as $doc) {
        $messages[] = chat_public_message($doc);
    }
    $messages = array_reverse($messages); // oldest first

    // Distinct users who have ever chatted, for the members stat.
    $members = $db->chat_messages->distinct('user_id', ['deleted' => ['$ne' => true]]);

    chat_json([
        'success'  => true,
        'messages' => $messages,
        'members'  => count($members),
        'me'       => [
            'user_id'  => (int) $session->getUserId(),
            'username' => (string) $session->getUsername(),
            'role'     => chat_role_label($session),
        ],
    ]);
} catch (\Throwable $e) {
    error_log('Chat history failed: ' . $e->getMessage());
    chat_json(['success' => false, 'error' => 'Could not load chat history'], 500);
}
