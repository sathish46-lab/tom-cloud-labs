<?php
require_once __DIR__ . '/../../../src/load.php';

$user = AuthMiddleware::requireAuth();

$preference_id = $_POST['preference_id'] ?? '';
$value = $_POST['value'] ?? '';

if (is_string($value) && !empty($value) && ($value[0] === '{' || $value[0] === '[')) {
    $decoded = json_decode($value, true);
    if (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_object($decoded))) {
        $value = $decoded;
    }
}

if (empty($preference_id)) {
    api_json(['status' => 'error', 'message' => 'Missing preference_id'], 400);
}

try {
    $db = DatabaseConnection::getDefaultDatabase();
    
    // Set the specific preference inside the ui_preferences object
    $db->users->updateOne(
        ['email' => $user->getEmail()],
        ['$set' => ["ui_preferences.{$preference_id}" => $value]]
    );

    // Keep the active PHP session in sync so it applies on reload
    $user->setUiPreference($preference_id, $value);

    api_json([
        'status' => 'success',
        'message' => 'Preference saved successfully',
        'preference_id' => $preference_id,
        'value' => $value
    ], 200);
} catch (Exception $e) {
    api_json(['status' => 'error', 'message' => errors_from_exception($e, 'user/preference_save')['error']], 500);
}
