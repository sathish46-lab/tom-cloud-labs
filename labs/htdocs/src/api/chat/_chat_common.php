<?php
/**
 * Chat common bootstrap — shared gate + helpers for all /api/chat/* endpoints.
 *
 * Security model:
 *  - Every endpoint requires an authenticated session (guests get 401).
 *  - All state-changing endpoints require a valid CSRF token.
 *  - Publishing to the chat topic happens ONLY here (never from browsers).
 *  - Message text is stripped of HTML server-side and rendered with
 *    textContent client-side, so stored payloads are XSS-inert.
 *  - Only username/avatar/role are exposed to other clients — never
 *    emails, internal ids beyond the public user_id, or session data.
 */

require_once __DIR__ . '/../../../src/load.php';

/**
 * Enforce authenticated session and return the UserSession.
 * Guests get a 401 JSON response and the script exits.
 */
function chat_require_user(): UserSession {
    $session = AuthMiddleware::requireAuth();
    if ($session->getUser() === null) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    return $session;
}

/**
 * Connect to the default Mongo database (tom_labs_db) or exit 503.
 */
function chat_db(): MongoDB\Database {
    try {
        return DatabaseConnection::getDefaultDatabase();
    } catch (\Throwable $e) {
        http_response_code(503);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Database unavailable']);
        exit;
    }
}

/**
 * Read JSON body (application/json) falling back to form fields.
 */
function chat_input(string $key): string {
    $raw = file_get_contents('php://input');
    if ($raw !== false && $raw !== '') {
        $json = json_decode($raw, true);
        if (is_array($json) && array_key_exists($key, $json)) {
            return is_scalar($json[$key]) ? (string) $json[$key] : '';
        }
    }
    return isset($_POST[$key]) && is_scalar($_POST[$key]) ? (string) $_POST[$key] : '';
}

/**
 * Emit a JSON response and exit.
 */
function chat_json(array $payload, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

/**
 * Build the public payload for a stored chat message document.
 */
function chat_public_message(MongoDB\Model\BSONDocument|array $doc): array {
    return [
        'type'     => 'message',
        'id'       => (string) $doc['_id'],
        'user_id'  => (int) ($doc['user_id'] ?? 0),
        'username' => (string) ($doc['username'] ?? ''),
        'avatar'   => $doc['avatar'] ?? null,
        'role'     => (string) ($doc['role'] ?? 'member'),
        'text'     => (string) ($doc['text'] ?? ''),
        'ts'       => isset($doc['created_at']) ? (int) (string) $doc['created_at'] : 0,
    ];
}

/**
 * Publish an event to the global chat topic on amq.topic.
 * STOMP subscribers listen on /topic/global-chat-messages.
 * Failure is non-fatal: the message is already persisted and will
 * appear on the next history fetch.
 */
function chat_publish(array $event): bool {
    try {
        $rabbit = new RabbitClient(); // amq.topic
        $rabbit->sendMessage($event, 'global-chat-messages');
        return true;
    } catch (\Throwable $e) {
        error_log('Chat publish failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Role label used for the badge in the UI. Only 'admin' gets a badge.
 */
function chat_role_label(UserSession $session): string {
    return AuthMiddleware::isAdmin() ? 'admin' : 'member';
}
