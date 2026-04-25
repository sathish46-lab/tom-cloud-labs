<?php
// /app/labs/dashboard.php
require_once __DIR__ . '/../../src/load.php';

if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    header("Location: /auth/signin.php"); exit;
}

$user = Session::getUser();

// 1. Get Hash from URL
$uriParts = explode('/', $_SERVER['REQUEST_URI']);
$instanceHash = end($uriParts); 

if (empty($instanceHash)) { 
    header("Location: /labs"); 
    exit; 
}

$db = DatabaseConnection::getDefaultDatabase();
$labData = $db->deployed_labs->findOne(['instance_hash' => $instanceHash]);

// 2. If Lab is new, identify type by hash comparison
if (!$labData) {
    if ($instanceHash === $user->getLabHash('minio')) {
        $labType = 'minio';
    } elseif ($instanceHash === $user->getLabHash('docker')) {
        $labType = 'docker';
    } else {
        $labType = 'essentials';
    }
    
    // Create a "Virtual" lab object with the ACTUAL hash
    $labData = [
        'instance_hash' => $instanceHash,
        'lab_type' => $labType,
        'status' => 'not_deployed',
        'internal_ip' => '0.0.0.0'
    ];
} else {
    $labType = $labData['lab_type'] ?? 'essentials';
    $instanceHash = $labData['instance_hash'];
}

// 3. Declare the exchange immediately
new RabbitClient("logs_" . $instanceHash);

// 4. Set session variables
Session::set('full_instance_hash', $instanceHash);
Session::set('current_lab_status', $labData['status'] ?? 'not_deployed');

Session::$pageTitle = "Labs / Preferences / " . ucfirst($labType); 
// error_log("DEBUG: instanceHash=$instanceHash, labType=$labType, status=" . ($labData['status'] ?? 'none'));

Session::loadMaster();