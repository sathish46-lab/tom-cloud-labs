<?php
/**
 * Chat merge guest — POST /api/chat/merge_guest.php
 * Authenticated users only + CSRF.
 * Merges a guest's unread tracking into the logged-in user's account.
 * When a guest browses chat before logging in, their last_read_msg_id is
 * stored under key_type='guest'. On login, this advances the user's
 * last_read_msg_id to the same point so only truly unread messages show.
 */

require_once __DIR__ . '/_chat_common.php';

$session = chat_require_user();
AuthMiddleware::requireCsrf();
$db = chat_db();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$guestId = $input['guest_id'] ?? '';
if (!$guestId) {
    chat_json(['success' => false, 'error' => 'guest_id required'], 400);
}

$userId = (int)$session->getUserId();

// Look up the guest's last read message id.
$guestLastRead = chat_get_last_read_msg_id('guest', $guestId);

if ($guestLastRead !== null) {
    // Advance the user's read position to the guest's last read,
    // so messages the guest already saw don't count as unread.
    chat_set_last_read_msg_id('user', (string)$userId, $guestLastRead);
}

// Clean up the guest record (optional — keeps collection small).
try {
    chat_read_positions()->deleteOne(['key_type' => 'guest', 'key_id' => $guestId]);
} catch (\Throwable $e) {
    // Non-fatal.
}

chat_json([
    'success' => true,
    'merged_guest_last_read' => $guestLastRead,
    'user_id' => $userId,
]);
