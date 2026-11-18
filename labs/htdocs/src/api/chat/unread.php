<?php
/**
 * Chat unread count — GET /api/chat/unread.php
 * Authenticated users or guests with X-Guest-Id header.
 *
 * Tiny endpoint: returns ONLY the unread badge value — no message rendering.
 * The chat widget calls this on every page load (panel closed) instead of
 * pulling the full history + pinned list just to learn one number. The panel
 * fetches history when it is actually opened.
 *
 * The unread logic mirrors history.php exactly: everything after the
 * identity's last read position counts as unread.
 */

require_once __DIR__ . '/_chat_common.php';

$guestId = $_SERVER['HTTP_X_GUEST_ID'] ?? '';
$identity = chat_resolve_identity($guestId ?: null);

if (!$identity) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Cheap endpoint called on every page load — keep abuse in check.
rate_limit('chat:unread', 10, 120);

$db = chat_db();

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

chat_json([
    'success'      => true,
    'unread_count' => $unreadCount,
    // Identity + member stat so the client can fully restore its pre-panel
    // state from this endpoint alone (typing beacon needs CHAT.me, the
    // header subtitle needs the member count) without a history load.
    'me'           => $identity['type'] === 'user' ? (function () use (&$db) {
        $session = AuthMiddleware::getUser();
        return [
            'user_id'  => (int) $session->getUserId(),
            'username' => (string) $session->getUsername(),
            'role'     => chat_role_label($session),
        ];
    })() : null,
    'members'      => count($db->chat_messages->distinct('user_id', ['deleted' => ['$ne' => true]])),
]);
