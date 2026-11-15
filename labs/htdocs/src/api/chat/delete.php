<?php
/**
 * Chat delete — POST /api/chat/delete.php
 * Users can delete their OWN messages; superusers can delete anyone's.
 * Soft-deletes in MongoDB and broadcasts the delete so every connected
 * client removes the bubble live.
 */

require_once __DIR__ . '/_chat_common.php';

$session = chat_require_user();
AuthMiddleware::requireCsrf();
$db = chat_db();

rate_limit('chat:delete', 30, 60);

$id = chat_input('id');
// Mongo ObjectId format check — reject anything that isn't 24 hex chars.
if (!preg_match('/^[a-f0-9]{24}$/i', $id)) {
    chat_json(['success' => false, 'error' => 'Invalid message id'], 422);
}

try {
    $mongoId = new MongoDB\BSON\ObjectId($id);
} catch (\Throwable $e) {
    chat_json(['success' => false, 'error' => 'Invalid message id'], 422);
}

$isAdmin = AuthMiddleware::isAdmin();
$userId  = (int) $session->getUserId();

// Owner scope: own messages only. Admin scope: any message.
$filter = ['_id' => $mongoId, 'deleted' => ['$ne' => true]];
if (!$isAdmin) {
    $filter['user_id'] = $userId;
}

try {
    // First verify the message exists and is in scope for the right error.
    $existing = $db->chat_messages->findOne($filter);
    if ($existing === null) {
        chat_json(['success' => false, 'error' => 'Message not found'], 404);
    }

    $update = $db->chat_messages->updateOne(
        $filter,
        ['$set' => ['deleted' => true, 'deleted_by' => $isAdmin && ($existing['user_id'] ?? 0) !== $userId ? 'admin' : 'self']]
    );

    if ($update->getModifiedCount() === 0) {
        chat_json(['success' => false, 'error' => 'Message not found'], 404);
    }
} catch (\Throwable $e) {
    error_log('Chat delete failed: ' . $e->getMessage());
    chat_json(['success' => false, 'error' => 'Could not delete message'], 500);
}

// Broadcast so every open panel drops the bubble immediately.
chat_publish([
    'type'      => 'delete',
    'id'        => $id,
    'by_admin'  => $isAdmin,
]);

chat_json(['success' => true, 'id' => $id]);
