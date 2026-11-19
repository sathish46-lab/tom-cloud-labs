<?php
/**
 * Chat search — GET /api/chat/search.php
 * Search messages by text query.
 * Returns matching messages (newest first), limited to 25.
 * Authenticated users or guests with X-Guest-Id header.
 */

require_once __DIR__ . '/_chat_common.php';

$guestId = $_SERVER['HTTP_X_GUEST_ID'] ?? '';
$identity = chat_resolve_identity($guestId ?: null);
$db = chat_db();

if (!$identity) {
    chat_json(['success' => false, 'error' => 'Unauthorized'], 401);
}

$query = trim($_GET['q'] ?? '');
if ($query === '') {
    chat_json(['success' => true, 'messages' => [], 'total' => 0]);
}

try {
    $cursor = $db->chat_messages->find(
        [
            'deleted' => ['$ne' => true],
            'text'    => ['$regex' => new MongoDB\BSON\Regex(preg_quote($query, '/'), 'i')],
        ],
        [
            'sort'  => ['created_at' => -1],
            'limit' => 25,
        ]
    );

    $messages = [];
    $viewerUserId = $identity['type'] === 'user' ? (string)$identity['id'] : null;
    foreach ($cursor as $doc) {
        $messages[] = chat_public_message($doc, $viewerUserId);
    }

    chat_json([
        'success' => true,
        'query'   => $query,
        'total'   => count($messages),
        'messages'=> array_reverse($messages), // oldest first
    ]);
} catch (\Throwable $e) {
    error_log('Chat search failed: ' . $e->getMessage());
    chat_json(['success' => false, 'error' => 'Search failed'], 500);
}
