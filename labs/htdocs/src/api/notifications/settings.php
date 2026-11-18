<?php
/**
 * User notification settings — GET/POST /api/notifications/settings.php
 * GET:  returns current settings
 * POST: saves settings (body JSON: { email, security, mcp_live, categories: {...} })
 */

require_once __DIR__ . '/_notification_common.php';

$session = notif_require_user();
$email   = (string)($session->getEmail() ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    notif_json([
        'success' => true,
        'settings'=> UserNotificationSettings::getForUser($email),
    ]);
}

// POST — save
AuthMiddleware::requireCsrf();
$input = notif_input_array();

if (!isset($input['email']) && !isset($input['security']) && !isset($input['mcp_live']) && !isset($input['categories'])) {
    notif_json(['success' => false, 'error' => 'No settings provided'], 400);
}

$ok = UserNotificationSettings::saveForUser($email, $input);

$payload = [
    'success' => $ok,
    'settings'=> $ok ? UserNotificationSettings::getForUser($email) : null,
];
if (!$ok) $payload['error'] = 'Failed to save notification settings';

notif_json($payload);
