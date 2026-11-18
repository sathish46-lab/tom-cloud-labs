<?php
/**
 * List notifications — GET /api/notifications/list.php
 * Query: ?category=<cat>&limit=20&offset=0&unread_only=1
 */

require_once __DIR__ . '/_notification_common.php';

$session = notif_require_user();
AuthMiddleware::requireCsrf();

$userId = (string)($session->getUserId() ?? '');
$email  = (string)($session->getEmail() ?? '');

$category = trim($_GET['category'] ?? '');
$limit    = (int)($_GET['limit'] ?? 20);
$offset   = (int)($_GET['offset'] ?? 0);
$unread   = filter_var($_GET['unread_only'] ?? '0', FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?: false;

if ($limit < 1)  $limit = 1;
if ($limit > 100) $limit = 100;
if ($offset < 0) $offset = 0;

$cat = $category === '' ? null : $category;

$items = Notification::listForUser($userId, $cat, $limit, $offset, $unread);

// Enrich with per-user category enablement so the UI can grey disabled tabs.
$enabledCats = [];
foreach (Notification::CATEGORIES as $c) {
    $enabledCats[$c] = UserNotificationSettings::categoryEnabled($email, $c);
}

notif_json([
    'success'      => true,
    'items'        => $items,
    'count'        => count($items),
    'categories'   => $enabledCats,
]);
