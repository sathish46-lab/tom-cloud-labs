<?php
require_once __DIR__ . '/../../../src/load.php';

$user = AuthMiddleware::requireAuth();

$db = DatabaseConnection::getDefaultDatabase();

$payload = json_decode(file_get_contents('php://input'), true);

if (!$payload) {
    api_json(['status' => 'error', 'error' => 'Invalid payload'], 400);
}

$mode = $payload['mode'] ?? null;
$theme = $payload['theme'] ?? null;
$plainColor = $payload['plainColor'] ?? null;
$accentColor = $payload['accentColor'] ?? null;
$customSlots = $payload['customSlots'] ?? null;
$customThemes = $payload['customThemes'] ?? null;

$updateData = [];
if ($mode !== null) $updateData['theme_preferences.mode'] = $mode;
if ($theme !== null && in_array($theme, ['light', 'dark', 'auto'], true)) $updateData['theme_preferences.theme'] = $theme;
if ($plainColor !== null) $updateData['theme_preferences.plain_color'] = $plainColor;
if ($accentColor !== null) $updateData['theme_preferences.accent_color'] = $accentColor;
if ($customSlots !== null) $updateData['theme_preferences.custom_slots'] = $customSlots;
if ($customThemes !== null) $updateData['theme_preferences.custom_themes'] = $customThemes;

if (empty($updateData)) {
    api_json(['status' => 'success'], 200);
}

try {
    $db->users->updateOne(
        ['email' => $user->getEmail()],
        ['$set' => $updateData]
    );
    api_json(['status' => 'success'], 200);
} catch (Exception $e) {
    api_json(['status' => 'error', 'error' => 'Database error.'], 400);
}
