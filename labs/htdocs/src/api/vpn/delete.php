<?php
require_once __DIR__ . '/../../../src/load.php';
require_once __DIR__ . '/../../../src/lib/core/VPN.class.php';
require_once __DIR__ . '/../../../src/lib/core/AuditLog.class.php';

$user = AuthMiddleware::requireAuth();
AuthMiddleware::requireCsrf();

$data = json_decode(file_get_contents('php://input'), true);
$dbId = $data['id'] ?? null;
$pubKey = $data['public_key'] ?? null;

if (!$dbId || !$pubKey) {
    api_json(['status' => 'error', 'error' => 'Missing ID or Public Key'], 400);
}

$db = DatabaseConnection::getDefaultDatabase();
$user = Session::getUser();

try {
    // 1. Find device to get its IP
    $device = $db->devices->findOne([
        '_id' => new MongoDB\BSON\ObjectId($dbId),
        'user_id' => $user->getUserId()
    ]);
    
    if (!$device) {
        throw new Exception("Device not found.");
    }
    
    $assignedIp = $device['assigned_ip'] ?? null;

    // 2. Remove from WireGuard Kernel
    $response = VPN::request('wg', 'remove_peer', [
        'peer'     => $pubKey, 
        'reserved' => 'true',
        'device'   => 'wg0'
    ]);

    // 3. Soft-delete from devices collection (keep record for audit trail)
    $db->devices->updateOne([
        '_id' => new MongoDB\BSON\ObjectId($dbId),
        'user_id' => $user->getUserId()
    ], [
        '$set' => [
            'status' => 'deleted',
            'deleted_at' => new MongoDB\BSON\UTCDateTime(),
            'deleted_by' => $user->getEmail(),
        ]
    ]);

    AuditLog::log('delete', 'vpn_device', $assignedIp, [
        'device_name' => $device['device_name'] ?? '',
    ]);

    api_json(['status' => 'success'], 200);
} catch (Exception $e) {
    api_json(['status' => 'error', 'error' => errors_from_exception($e, 'vpn/delete')['error']], 500);
}
