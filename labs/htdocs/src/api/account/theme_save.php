<?php
require_once __DIR__ . '/../../../src/load.php';

header('Content-Type: application/json');
if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    echo json_encode(['status' => 'error', 'error' => 'Unauthorized']); exit;
}

$user = Session::getUser();
$db = DatabaseConnection::getDefaultDatabase();

$payload = json_decode(file_get_contents('php://input'), true);

if (!$payload) {
    echo json_encode(['status' => 'error', 'error' => 'Invalid payload']); exit;
}

$mode = $payload['mode'] ?? null;
$plainColor = $payload['plainColor'] ?? null;
$customSlots = $payload['customSlots'] ?? null;

$updateData = [];
if ($mode !== null) $updateData['theme_preferences.mode'] = $mode;
if ($plainColor !== null) $updateData['theme_preferences.plain_color'] = $plainColor;
if ($customSlots !== null) $updateData['theme_preferences.custom_slots'] = $customSlots;

if (empty($updateData)) {
    echo json_encode(['status' => 'success']); exit;
}

try {
    $db->users->updateOne(
        ['email' => $user->getEmail()],
        ['$set' => $updateData]
    );
    echo json_encode(['status' => 'success']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'error' => 'Database error.']);
}
