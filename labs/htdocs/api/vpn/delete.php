<?php
require_once "../../src/load.php";
require_once "../../src/lib/core/VPN.class.php";

header('Content-Type: application/json');

if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    echo json_encode(['status' => 'error', 'error' => 'Unauthorized']); exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$dbId = $data['id'] ?? null;      // From tom_labs_db.devices
$pubKey = $data['public_key'] ?? null; // For WireGuard kernel removal

if (!$dbId || !$pubKey) {
    echo json_encode(['status' => 'error', 'error' => 'Missing ID or Public Key']); exit;
}

$db = DatabaseConnection::getDefaultDatabase();
$user = Session::getUser();

try {
    // 1. Remove from WireGuard Kernel via API
    $response = VPN::request('wg', 'remove_peer', [
        'peer'     => $pubKey, 
        'reserved' => 'true', // Keep IP reserved in tom_labs_vpn for reuse
        'device'   => 'wg0'
    ]);

    // 2. FIXED: Delete from the CORRECT collection
    // This removes the duplicate/deleted card from your UI
    $deleteResult = $db->devices->deleteOne([
        '_id' => new MongoDB\BSON\ObjectId($dbId),
        'user_id' => $user->getUserId()
    ]);

    if ($deleteResult->getDeletedCount() === 0) {
        throw new Exception("Record not found in metadata database.");
    }

    echo json_encode(['status' => 'success']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'error' => $e->getMessage()]);
}