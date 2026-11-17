<?php
/**
 * Chat pin — POST /api/chat/pin.php
 * Pin or unpin a message. Admin/superuser only.
 * Pinned messages appear at the top of the chat panel.
 * Broadcasts the pin event so all connected clients update live.
 */

require_once __DIR__ . '/_chat_common.php';

$session = chat_require_user();
AuthMiddleware::requireCsrf();
AuthMiddleware::requireAdmin(); // admins only
$db = chat_db();

$messageId = chat_input('message_id');
$pinned    = filter_var(chat_input('pinned'), FILTER_VALIDATE_BOOLEAN);

if (!preg_match('/^[a-f0-9]{24}$/i', $messageId)) {
    chat_json(['success' => false, 'error' => 'Invalid message id'], 422);
}

try {
    $mongoId = new MongoDB\BSON\ObjectId($messageId);
} catch (\Throwable $e) {
    chat_json(['success' => false, 'error' => 'Invalid message id'], 422);
}

$message = $db->chat_messages->findOne([
    '_id'   => $mongoId,
    'deleted' => ['$ne' => true],
]);
if ($message === null) {
    chat_json(['success' => false, 'error' => 'Message not found'], 404);
}

try {
    $db->chat_messages->updateOne(
        ['_id' => $mongoId],
        ['$set' => [
            'pinned'    => (bool)$pinned,
            'pinned_by' => (int)$session->getUserId(),
            'pinned_at' => $pinned ? new MongoDB\BSON\UTCDateTime((int)(microtime(true) * 1000)) : null,
        ]]
    );
} catch (\Throwable $e) {
    error_log('Chat pin failed: ' . $e->getMessage());
    chat_json(['success' => false, 'error' => 'Could not pin message'], 500);
}

// Broadcast pin event.
chat_publish([
    'type'      => 'pin',
    'id'        => (string)$messageId,
    'pinned'    => (bool)$pinned,
    'user_id'   => (int)$session->getUserId(),
]);

chat_json([
    'success' => true,
    'id'      => (string)$messageId,
    'pinned'  => (bool)$pinned,
]);
