<?php
/**
 * Chat mark-read — POST /api/chat/read.php
 * Authenticated users or guests with X-Guest-Id header + CSRF.
 * Advances the caller's last_read_msg_id to the newest stored message,
 * so the unread badge clears once the user opens the chat panel.
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
AuthMiddleware::requireCsrf();
$db = chat_db();

// Mark read: advance last_read_msg_id to the newest message currently stored.
$latest = $db->chat_messages->findOne(
    ['deleted' => ['$ne' => true]],
    ['sort' => ['created_at' => -1], 'projection' => ['_id' => 1]]
);
$msgId = $latest ? (string)$latest['_id'] : null;
if ($msgId !== null) {
    chat_set_last_read_msg_id($identity['type'], (string)$identity['id'], $msgId);
}

chat_json(['success' => true, 'last_read_msg_id' => $msgId]);
