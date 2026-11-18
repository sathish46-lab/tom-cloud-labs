<?php
/**
 * Delete one notification — DELETE /api/notifications/delete.php?id=<id>
 * Ownership-checked: user can only delete their own notifications.
 */

require_once __DIR__ . '/_notification_common.php';

$session = notif_require_user();
AuthMiddleware::requireCsrf();

$userId = (string)($session->getUserId() ?? '');
$id     = trim($_GET['id'] ?? notif_input('id') ?? '');

if ($id === '') {
    notif_json(['success' => false, 'error' => 'Missing notification id'], 400);
}

if (!preg_match('/^[a-f0-9]{24}$/', $id)) {
    notif_json(['success' => false, 'error' => 'Invalid notification id'], 400);
}

$deleted = Notification::delete($id, $userId);

notif_json(['success' => $deleted, 'deleted' => $deleted ? 1 : 0]);
