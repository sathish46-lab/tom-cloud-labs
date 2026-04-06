<?php
require_once "../../src/load.php";
require_once "../../src/lib/labs/IPManager.class.php";

header('Content-Type: application/json');
if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']); exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$ipAddr = $data['ip'] ?? null;
$type = $data['type'] ?? 'vpn';
$user = Session::getUser();

if ($type === 'essential_lab') {
    $ipManager = new LabIPManager();
    $db = DatabaseConnection::getClient()->selectDatabase('tom_labs_db');

    // 1. Wipe the IP Inventory Record (Ownership Wipe)
    $ipManager->release($ipAddr, $user->getEmail());
    
    // 2. Remove the Lab Metadata entirely so it disappears from Dashboard
    $db->deployed_labs->deleteMany([
        'internal_ip' => $ipAddr, 
        'email' => $user->getEmail()
    ]);

    echo json_encode(['success' => true]);
} else {
    // Standard VPN release logic
    // 1. Try with email
    $response = VPN::request('ip', 'unreserve', ['ip' => $ipAddr, 'email' => $user->getEmail()]);
    
    // 2. If email failed, try with username (fallback for legacy records)
    if (!$response || empty($response['result'])) {
        error_log("VPN release failed with email for $ipAddr. Retrying with username: " . $user->getUsername());
        $response = VPN::request('ip', 'unreserve', ['ip' => $ipAddr, 'email' => $user->getUsername()]);
    }

    if ($response && $response['result']) {
        $db = DatabaseConnection::getDefaultDatabase();
        $db->devices->deleteMany(['assigned_ip' => $ipAddr, 'user_id' => $user->getUserId()]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to release VPN IP']);
    }
}