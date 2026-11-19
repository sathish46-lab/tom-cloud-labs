<?php
/**
 * News automation settings API — POST /api/admin/news_settings
 * Admin-only: save news auto-fetch settings (enabled + interval) to global_settings.
 * Returns {status:'success'} or {status:'error', error}.
 */

require_once __DIR__ . '/../../../src/load.php';

$user = AuthMiddleware::requireAdmin();

$enabled = isset($_POST['enabled']) && $_POST['enabled'] === '1';
$interval = (int)($_POST['interval'] ?? 60);

// Validate interval
$allowedIntervals = [1, 30, 60, 180, 360, 720, 1440];
if (!in_array($interval, $allowedIntervals, true)) {
    $interval = 60;
}

try {
    $db = DatabaseConnection::getDefaultDatabase();

    $db->global_settings->updateOne(
        ['_id' => 'news_settings'],
        [        '$set' => [
            'enabled' => $enabled,
            'interval' => $interval,
            'updated_at' => \TimeHelper::now(),
            'updated_by' => $user->getUsername() ?? 'admin',
        ]],
        ['upsert' => true]
    );

    api_json(['status' => 'success'], 200);
} catch (Exception $e) {
    api_json(['status' => 'error', 'error' => errors_from_exception($e, 'admin/news_settings')['error']], 500);
}
