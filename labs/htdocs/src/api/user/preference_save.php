<?php
require_once __DIR__ . '/../../../src/load.php';

header('Content-Type: application/json');

if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    echo json_encode(['status' => 'error', 'error' => 'Unauthorized']); 
    exit;
}

$user = Session::getUser();
$preference_id = $_POST['preference_id'] ?? '';
$value = $_POST['value'] ?? '';

if (empty($preference_id)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing preference_id']); 
    exit;
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

    echo json_encode([
        'status' => 'success',
        'message' => 'Preference saved successfully',
        'preference_id' => $preference_id,
        'value' => $value
    ]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
