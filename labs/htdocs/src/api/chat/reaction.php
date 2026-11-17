<?php
/**
 * Chat reaction — POST /api/chat/reaction.php
 * Add or remove an emoji reaction on a message.
 * Any authenticated user can react; each user can react once per emoji per message.
 * Broadcasts the reaction event so all connected clients update live.
 */

require_once __DIR__ . '/_chat_common.php';

$session = chat_require_user();
AuthMiddleware::requireCsrf();
$db = chat_db();

$messageId = chat_input('message_id');
$emoji     = chat_input('emoji');

if (!preg_match('/^[a-f0-9]{24}$/i', $messageId)) {
    chat_json(['success' => false, 'error' => 'Invalid message id'], 422);
}
if ($emoji === '') {
    chat_json(['success' => false, 'error' => 'Emoji required'], 422);
}

$userId = (int) $session->getUserId();

try {
    $mongoId = new MongoDB\BSON\ObjectId($messageId);
} catch (\Throwable $e) {
    chat_json(['success' => false, 'error' => 'Invalid message id'], 422);
}

// Verify message exists and is not deleted.
$message = $db->chat_messages->findOne([
    '_id'   => $mongoId,
    'deleted' => ['$ne' => true],
]);
if ($message === null) {
    chat_json(['success' => false, 'error' => 'Message not found'], 404);
}

$reactions = $db->chat_reactions;

// Check if this user already reacted with this emoji on this message.
$existing = $reactions->findOne([
    'message_id' => (string)$messageId,
    'user_id'    => $userId,
    'emoji'      => $emoji,
]);

if ($existing !== null) {
    // Remove reaction (toggle off).
    $reactions->deleteOne(['_id' => $existing['_id']]);
    $added = false;
} else {
    // Add reaction.
    $reactions->insertOne([
        'message_id' => (string)$messageId,
        'user_id'    => $userId,
        'emoji'      => $emoji,
        'created_at' => new MongoDB\BSON\UTCDateTime((int)(microtime(true) * 1000)),
    ]);
    $added = true;
}

// Count total users who reacted with this emoji.
$count = (int)$reactions->countDocuments([
    'message_id' => (string)$messageId,
    'emoji'      => $emoji,
]);

// Broadcast reaction event.
chat_publish([
    'type'      => 'reaction',
    'message_id' => (string)$messageId,
    'emoji'      => $emoji,
    'user_id'    => $userId,
    'added'      => $added,
    'count'      => $count,
]);

chat_json([
    'success' => true,
    'added'   => $added,
    'count'   => $count,
    'emoji'   => $emoji,
]);
