<?php
/**
 * Chat typing — POST /api/chat/typing.php
 * Broadcasts a transient "typing" indicator to the chat topic.
 * The client throttles to at most one call per 2.5s while the user is
 * typing; the server additionally rate limits. Nothing is persisted.
 */

require_once __DIR__ . '/_chat_common.php';

$session = chat_require_user();
AuthMiddleware::requireCsrf();

rate_limit('chat:typing', 40, 60);

$username = (string) $session->getUsername();
$userId   = (int) $session->getUserId();

// Unlock the PHP session immediately so concurrent send.php / delete.php
// calls never block on a typing request's session lock.
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$stop = filter_input(INPUT_POST, 'stop', FILTER_VALIDATE_BOOLEAN)
    ?? (isset($_POST['stop']) ? filter_var($_POST['stop'], FILTER_VALIDATE_BOOLEAN) : false);
$raw = file_get_contents('php://input');
if ($raw !== false && $raw !== '') {
    $json = json_decode($raw, true);
    if (is_array($json) && isset($json['stop'])) {
        $stop = filter_var($json['stop'], FILTER_VALIDATE_BOOLEAN);
    }
}

if ($stop) {
    chat_publish([
        'type'     => 'typing_stop',
        'user_id'  => $userId,
        'username' => $username,
    ]);
} else {
    chat_publish([
        'type'     => 'typing',
        'user_id'  => $userId,
        'username' => $username,
    ]);
}

chat_json(['success' => true]);
