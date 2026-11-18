<?php
/**
 * Mark notifications read — POST /api/notifications/read.php
 * Body: { ids: [...] }  or  { all: true, category?: "..." }
 */

require_once __DIR__ . '/_notification_common.php';

$session = notif_require_user();
AuthMiddleware::requireCsrf();

$userId = (string)($session->getUserId() ?? '');
$input  = notif_input_array();

if (isset($input['all']) && $input['all'] === 'true' || isset($input['all']) && $input['all'] === true) {
    $category = isset($input['category']) && is_scalar($input['category']) ? (string) $input['category'] : null;
    if ($category !== null && !in_array($category, Notification::CATEGORIES, true)) {
        notif_json(['success' => false, 'error' => 'Invalid category'], 400);
    }
    $marked = Notification::markAllRead($userId, $category);
    notif_json(['success' => true, 'marked' => $marked]);
}

$ids = isset($input['ids']) && is_array($input['ids']) ? $input['ids'] : [];
if ($ids === []) {
    notif_json(['success' => false, 'error' => 'Provide ids[] or all=true'], 400);
}

$marked = 0;
foreach ($ids as $id) {
    if (!is_scalar($id)) continue;
    if (Notification::markRead((string) $id, $userId)) $marked++;
}

notif_json(['success' => true, 'marked' => $marked]);
