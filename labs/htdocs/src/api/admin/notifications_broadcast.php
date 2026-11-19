<?php
/**
 * Admin broadcast notification — POST /api/admin/notifications_broadcast.php
 * Body (form or JSON): { title, body, category, target: "all"|"role"|"emails", emails?, role? }
 * Creates one notification per target user so each recipient can mark read independently.
 */

require_once __DIR__ . '/../../load.php';

$admin = AuthMiddleware::requireAdmin();
AuthMiddleware::requireCsrf();

// Read input: JSON body or form fields
$raw = file_get_contents('php://input');
$json = [];
if ($raw !== false && $raw !== '') {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) $json = $decoded;
}

function bp(string $key): string {
    global $json;
    if (isset($json[$key]) && is_scalar($json[$key])) return (string) $json[$key];
    return isset($_POST[$key]) && is_scalar($_POST[$key]) ? (string) $_POST[$key] : '';
}

$title   = bp('title');
$body    = bp('body');
$category= bp('category');
$target  = bp('target');
$role    = bp('role');

if ($title === '' || $body === '') {
api_json(['status' => 'error', 'error' => 'title and body are required'], 400);
}

if ($category === '') $category = 'academy';
if (!in_array($category, Notification::CATEGORIES, true)) {
api_json(['status' => 'error', 'error' => 'Invalid category'], 400);
}

if ($target === '') $target = 'all';

// Resolve target user ids
$targetUserIds = [];
$db = DatabaseConnection::getDefaultDatabase();

if ($target === 'all') {
    $cursor = $db->users->find([], ['projection' => ['_id' => 1, 'user_id' => 1]]);
    foreach ($cursor as $u) {
        $targetUserIds[] = isset($u['user_id']) ? (string)$u['user_id'] : (string)$u['_id'];
    }
} elseif ($target === 'role') {
    if ($role === '') {
api_json(['status' => 'error', 'error' => 'role required for target=role'], 400);
    }
    $cursor = $db->users->find(['role' => $role], ['projection' => ['_id' => 1, 'user_id' => 1]]);
    foreach ($cursor as $u) {
        $targetUserIds[] = isset($u['user_id']) ? (string)$u['user_id'] : (string)$u['_id'];
    }
} elseif ($target === 'emails') {
    $emails = isset($json['emails']) && is_array($json['emails']) ? $json['emails'] : (preg_split('/\s*,\s*/', trim(bp('emails'))) ?: []);
    foreach ($emails as $e) {
        $e = trim((string)$e);
        if ($e === '') continue;
        $u = $db->users->findOne(['email' => $e], ['projection' => ['_id' => 1, 'user_id' => 1]]);
        if ($u) {
            $targetUserIds[] = isset($u['user_id']) ? (string)$u['user_id'] : (string)$u['_id'];
        }
    }
} else {
api_json(['status' => 'error', 'error' => 'Invalid target'], 400);
}

if ($targetUserIds === []) {
api_json(['status' => 'error', 'error' => 'No target users found'], 400);
}

$inserted = Notification::broadcast($title, $body, $category, $targetUserIds, (string)($admin->getEmail() ?? ''));

AuditLog::log('update', 'notifications', 'broadcast', [
    'action'  => 'broadcast_notification',
    'title'   => $title,
    'category'=> $category,
    'target'  => $target,
    'role'    => $role,
    'count'   => $inserted,
], (string)($admin->getUserId() ?? 'unknown'));

api_json([
    'status' => 'success',
    'sent'   => $inserted,
], 200);
