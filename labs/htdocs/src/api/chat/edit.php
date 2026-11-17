<?php
/**
 * Chat edit — POST /api/chat/edit.php
 * Edit your OWN message text.
 * Sanitizes input server-side, updates MongoDB, broadcasts the edit event.
 * Admins can edit any message; regular users only their own.
 */

require_once __DIR__ . '/_chat_common.php';

$session = chat_require_user();
AuthMiddleware::requireCsrf();
$db = chat_db();

$messageId = chat_input('message_id');
$text      = chat_input('text');

if (!preg_match('/^[a-f0-9]{24}$/i', $messageId)) {
    chat_json(['success' => false, 'error' => 'Invalid message id'], 422);
}
$text = trim(strip_tags($text));
$text = preg_replace("/\r\n|\r/", "\n", $text) ?? '';
$text = preg_replace("/\n{3,}/", "\n\n", $text) ?? '';
if (mb_strlen($text) > 500) {
    $text = mb_substr($text, 0, 500);
}
if ($text === '') {
    chat_json(['success' => false, 'error' => 'Message is empty'], 422);
}

try {
    $mongoId = new MongoDB\BSON\ObjectId($messageId);
} catch (\Throwable $e) {
    chat_json(['success' => false, 'error' => 'Invalid message id'], 422);
}

$isAdmin = AuthMiddleware::isAdmin();
$userId  = (int) $session->getUserId();

$filter = ['_id' => $mongoId, 'deleted' => ['$ne' => true]];
if (!$isAdmin) {
    $filter['user_id'] = $userId;
}

$existing = $db->chat_messages->findOne($filter);
if ($existing === null) {
    chat_json(['success' => false, 'error' => 'Message not found'], 404);
}

try {
    $update = $db->chat_messages->updateOne(
        $filter,
        ['$set' => [
            'text'       => $text,
            'edited_at'  => new MongoDB\BSON\UTCDateTime((int)(microtime(true) * 1000)),
            'edited_by'  => $userId,
        ]]
    );

    if ($update->getModifiedCount() === 0) {
        chat_json(['success' => false, 'error' => 'No changes'], 422);
    }
} catch (\Throwable $e) {
    error_log('Chat edit failed: ' . $e->getMessage());
    chat_json(['success' => false, 'error' => 'Could not edit message'], 500);
}

// Broadcast edit event.
chat_publish([
    'type'      => 'edit',
    'id'        => (string)$messageId,
    'text'      => $text,
    'edited_at' => (int)(microtime(true) * 1000),
]);

chat_json([
    'success' => true,
    'id'      => (string)$messageId,
    'text'    => $text,
]);
