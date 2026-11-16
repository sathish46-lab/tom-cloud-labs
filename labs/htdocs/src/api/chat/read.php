<?php
/**
 * Chat mark-read — POST /api/chat/read.php
 * Authenticated users only + CSRF.
 * Advances the caller's last_read_msg_id to the newest stored message,
 * so the unread badge clears once the user opens the chat panel.
 */

require_once __DIR__ . '/_chat_common.php';

$session = chat_require_user();
AuthMiddleware::requireCsrf();
$db = chat_db();
$userId = (int)$session->getUserId();

// Mark read: advance last_read_msg_id to the newest message currently stored.
$latest = $db->chat_messages->findOne(
    ['deleted' => ['$ne' => true]],
    ['sort' => ['created_at' => -1], 'projection' => ['_id' => 1]]
);
$msgId = $latest ? (string)$latest['_id'] : null;
if ($msgId !== null) {
    chat_set_last_read_msg_id($userId, $msgId);
}

chat_json(['success' => true, 'last_read_msg_id' => $msgId]);
