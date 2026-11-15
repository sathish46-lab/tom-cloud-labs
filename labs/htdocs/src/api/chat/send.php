<?php
/**
 * Chat send — POST /api/chat/send.php
 * Authenticated users only + CSRF + per-user rate limit.
 * Sanitizes input server-side (strip_tags, 500 char cap), persists to
 * MongoDB, then publishes to the chat topic so every connected user
 * receives the message instantly over STOMP.
 *
 * The client does NOT optimistically render — the message comes back
 * through the socket fan-out (sender included). If publishing failed,
 * the persisted message is returned so the sender can render it anyway.
 */

require_once __DIR__ . '/_chat_common.php';

$session = chat_require_user();
AuthMiddleware::requireCsrf();
$db = chat_db();

// Per-user rate limit: 15 messages / 60s window.
rate_limit('chat:send', 15, 60);

// ---- Input + sanitization -------------------------------------------------
$text = chat_input('text');
// Strip any HTML, trim whitespace, collapse newlines, hard-cap length.
$text = trim(strip_tags($text));
$text = preg_replace("/\r\n|\r/", "\n", $text) ?? '';
$text = preg_replace("/\n{3,}/", "\n\n", $text) ?? '';
if (mb_strlen($text) > 500) {
    $text = mb_substr($text, 0, 500);
}

if ($text === '') {
    chat_json(['success' => false, 'error' => 'Message is empty'], 422);
}

// ---- Identity (server-derived, never client-supplied) ---------------------
$username = (string) $session->getUsername();
if ($username === '') {
    chat_json(['success' => false, 'error' => 'Account has no username'], 403);
}
$avatar = $session->getUser()->getAvatarUrl();
if (!is_string($avatar) || $avatar === '') {
    $avatar = null;
}
$role = chat_role_label($session);

// ---- Persist ---------------------------------------------------------------
$now = new MongoDB\BSON\UTCDateTime((int) (microtime(true) * 1000));
$doc = [
    'user_id'    => (int) $session->getUserId(),
    'username'   => $username,
    'avatar'     => $avatar,
    'role'       => $role,
    'text'       => $text,
    'created_at' => $now,
    'deleted'    => false,
];

try {
    $insert = $db->chat_messages->insertOne($doc);
    $id = (string) $insert->getInsertedId();
} catch (\Throwable $e) {
    error_log('Chat save failed: ' . $e->getMessage());
    chat_json(['success' => false, 'error' => 'Could not send message'], 500);
}

// ---- Fan out over RabbitMQ -------------------------------------------------
$payload = [
    'type'     => 'message',
    'id'       => $id,
    'user_id'  => (int) $doc['user_id'],
    'username' => $username,
    'avatar'   => $avatar,
    'role'     => $role,
    'text'     => $text,
    'ts'       => (int) (string) $now,
];
$published = chat_publish($payload);

chat_json([
    'success'   => true,
    'id'        => $id,
    'published' => $published,
    'message'   => $payload, // used by sender only if publish failed
]);
