<?php
/**
 * Chat history — GET /api/chat/history.php
 * Authenticated users only. Returns the last 50 messages (oldest first).
 * Deleted messages are excluded entirely.
 */

require_once __DIR__ . '/_chat_common.php';

$session = chat_require_user();
$db = chat_db();
$userId = (int)$session->getUserId();

$limit = 50;

// ---- Unread count: messages newer than the user's last read position ----
$lastReadId = chat_get_last_read_msg_id($userId);
if ($lastReadId === null) {
    // First time: count everything as unread (user asked for old unread to show on login).
    $unreadCount = (int)$db->chat_messages->countDocuments(['deleted' => ['$ne' => true]]);
} else {
    try {
        $after = new \MongoDB\BSON\ObjectId($lastReadId);
        $unreadCount = (int)$db->chat_messages->countDocuments([
            'deleted' => ['$ne' => true],
            '_id'     => ['$gt' => $after],
        ]);
    } catch (\Throwable $e) {
        // Corrupt stored id: fall back to counting everything.
        $unreadCount = (int)$db->chat_messages->countDocuments(['deleted' => ['$ne' => true]]);
    }
}

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
        'success'      => true,
        'messages'     => $messages,
        'members'      => count($members),
        'unread_count' => $unreadCount,
        'me'           => [
            'user_id'  => (int) $session->getUserId(),
            'username' => (string) $session->getUsername(),
            'role'     => chat_role_label($session),
        ],
    ]);
} catch (\Throwable $e) {
    error_log('Chat history failed: ' . $e->getMessage());
    chat_json(['success' => false, 'error' => 'Could not load chat history'], 500);
}
