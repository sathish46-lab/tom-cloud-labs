<?php
require_once "../../load.php";
require_once "../../lib/core/DomainManager.class.php";

header('Content-Type: application/json');

if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$domain_id = $data['domain_id'] ?? '';

if (empty($domain_id)) {
    echo json_encode(['success' => false, 'error' => 'Domain ID required']);
    exit;
}

try {
    $dm = new DomainManager();
    $is_verified = $dm->verifyDomain($domain_id);
    
    echo json_encode([
        'success' => true,
        'verified' => $is_verified,
        'message' => $is_verified ? 'Domain verified!' : 'DNS not pointing to server yet'
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}