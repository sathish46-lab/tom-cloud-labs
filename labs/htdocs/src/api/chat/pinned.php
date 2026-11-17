<?php
/**
 * Chat pinned — GET /api/chat/pinned.php
 * Returns currently pinned messages (oldest pinned first), limit 25.
 * Used by the chat "Pinned messages" panel. NOTE: the search endpoint
 * intentionally returns an empty list for an empty query, so the panel
 * cannot reuse it — this dedicated endpoint is the source of truth.
 *
 * Authenticated users or guests with X-Guest-Id header.
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

try {
    $cursor = $db->chat_messages->find(
        [
            'deleted' => ['$ne' => true],
            'pinned'  => true,
        ],
        [
            'sort'  => ['pinned_at' => -1],
            'limit' => 25,
        ]
    );

    $viewerUserId = $identity['type'] === 'user' ? (string)$identity['id'] : null;
    $messages = [];
    foreach ($cursor as $doc) {
        $messages[] = chat_public_message($doc, $viewerUserId);
    }

    chat_json([
        'success' => true,
        'total'   => count($messages),
        'messages'=> array_reverse($messages), // oldest pinned first, matches stream order
    ]);
} catch (\Throwable $e) {
    error_log('Chat pinned list failed: ' . $e->getMessage());
    chat_json(['success' => false, 'error' => 'Could not load pinned messages'], 500);
}
