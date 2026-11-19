<?php
/**
 * Admin notification mode — GET/POST /api/admin/notifications_mode.php
 * GET:  returns current mode from src/config/notification.json
 * POST: saves mode + broadcast_enabled (requireAdmin + CSRF + AuditLog)
 */

require_once __DIR__ . '/../../load.php';

$admin = AuthMiddleware::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    api_json([
        'status'          => 'success',
        'notification_mode' => NotificationSettings::mode(),
        'broadcast_enabled' => NotificationSettings::broadcastEnabled(),
    ], 200);
    exit;
}

// POST — save
AuthMiddleware::requireCsrf();

$raw = file_get_contents('php://input');
$json = [];
if ($raw !== false && $raw !== '') {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) $json = $decoded;
}

$mode = trim((string)($json['notification_mode'] ?? $_POST['notification_mode'] ?? ''));
$broadcastEnabled = isset($json['broadcast_enabled']) ? (bool)$json['broadcast_enabled']
    : (isset($_POST['broadcast_enabled']) ? filter_var($_POST['broadcast_enabled'], FILTER_VALIDATE_BOOLEAN) : true);

if ($mode === '') $mode = NotificationSettings::MODE_ADMIN;
if (!in_array($mode, NotificationSettings::MODES, true)) {
api_json(['status' => 'error', 'error' => 'Invalid notification mode'], 400);
}

$before = NotificationSettings::get();

if (!NotificationSettings::save($mode, $broadcastEnabled, (string)($admin->getEmail() ?? ''))) {
    errors_report(['context' => 'admin/notifications_mode', 'message' => 'Failed writing notification.json']);
api_json(['status' => 'error', 'error' => 'Failed to save notification settings (file not writable)'], 400);
}

AuditLog::log('update', 'settings', 'notification.json', [
    'action'              => 'set_notification_mode',
    'from_mode'           => $before['notification_mode'] ?? null,
    'to_mode'             => $mode,
    'from_broadcast'      => $before['broadcast_enabled'] ?? null,
    'to_broadcast'        => $broadcastEnabled,
], (string)($admin->getUserId() ?? 'unknown'));

api_json([
    'status'          => 'success',
    'notification_mode' => NotificationSettings::mode(),
    'broadcast_enabled' => NotificationSettings::broadcastEnabled(),
], 200);
