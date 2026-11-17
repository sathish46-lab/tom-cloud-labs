<?php
/**
 * Chat history — GET /api/chat/history.php
 * Authenticated users or guests with X-Guest-Id header.
 * Returns the last 50 messages (oldest first).
 * Deleted messages are excluded entirely.
 */

require_once __DIR__ . '/_chat_common.php';

$guestId = $_SERVER['HTTP_X_GUEST_ID'] ?? '';
$identity = chat_resolve_identity($guestId ?: null);
$db = chat_db();

if (!$identity) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$limit = 50;

// ---- Unread count: messages newer than the identity's last read position ----
$lastReadId = chat_get_last_read_msg_id($identity['type'], (string)$identity['id']);
if ($lastReadId === null) {
    // First time for this identity: count everything as unread.
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
    $viewerUserId = $identity['type'] === 'user' ? (string)$identity['id'] : null;
    foreach ($cursor as $doc) {
        $messages[] = chat_public_message($doc, $viewerUserId);
    }
    $messages = array_reverse($messages); // oldest first

    // Distinct users who have ever chatted, for the members stat.
    $members = $db->chat_messages->distinct('user_id', ['deleted' => ['$ne' => true]]);

    $payload = [
        'success'      => true,
        'messages'     => $messages,
        'members'      => count($members),
        'unread_count' => $unreadCount,
    ];

    if ($identity['type'] === 'user') {
        $session = AuthMiddleware::getUser();
        $payload['me'] = [
            'user_id'  => (int) $session->getUserId(),
            'username' => (string) $session->getUsername(),
            'role'     => chat_role_label($session),
        ];
    }

    chat_json($payload);
} catch (\Throwable $e) {
    error_log('Chat history failed: ' . $e->getMessage());
    chat_json(['success' => false, 'error' => 'Could not load chat history'], 500);
}
