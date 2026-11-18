<?php
/**
 * Unread count — GET /api/notifications/unread_count.php
 * Returns { success, total, by_category: {...} } for the bell badge.
 */

require_once __DIR__ . '/_notification_common.php';

$session = notif_require_user();

$userId = (string)($session->getUserId() ?? '');

$counts = Notification::unreadCount($userId);

notif_json([
    'success'      => true,
    'total'        => (int) $counts['total'],
    'by_category'  => (array) $counts['by_category'],
]);
