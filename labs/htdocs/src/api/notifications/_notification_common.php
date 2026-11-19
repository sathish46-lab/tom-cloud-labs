<?php
/**
 * Notification common bootstrap — shared gate + helpers for all /api/notifications/* endpoints.
 *
 * Security model:
 *  - Every endpoint requires an authenticated session (guests get 401).
 *  - All state-changing endpoints require a valid CSRF token.
 *  - Users can only touch their own notifications (user_id ownership check).
 */

require_once __DIR__ . '/../../../src/load.php';

/**
 * Enforce authenticated session and return the UserSession.
 * Guests get a 401 JSON response and the script exits.
 */
function notif_require_user(): UserSession {
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
function notif_db(): MongoDB\Database {
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
function notif_input(string $key): string {
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
 * Read JSON body as an array (falling back to form fields / $_REQUEST).
 */
function notif_input_array(): array {
    $raw = file_get_contents('php://input');
    if ($raw !== false && $raw !== '') {
        $json = json_decode($raw, true);
        if (is_array($json)) return $json;
    }
    return array_merge($_POST, $_REQUEST);
}

/**
 * Emit a JSON response and exit.
 */
function notif_json(array $payload, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
