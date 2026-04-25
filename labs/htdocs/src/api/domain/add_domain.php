<?php
require_once "../../load.php";
require_once "../../lib/core/DomainManager.class.php";

header('Content-Type: application/json');

if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user = Session::getUser();
$data = json_decode(file_get_contents('php://input'), true);

$domain = trim(strtolower($data['domain'] ?? ''));
$type = $data['type'] ?? 'custom';

if (empty($domain)) {
    echo json_encode(['success' => false, 'error' => 'Domain name is required.']);
    exit;
}

try {
    $dm = new DomainManager();
    
    // Use the DomainManager's addDomain method which handles everything
    $result = $dm->addDomain($user->getUserId(), $user->getEmail(), $domain, $type);
    // Get the verification status
    $db = DatabaseConnection::getClient()->selectDatabase('tom_labs_db');
    $inserted_domain = $db->domains->findOne(['domain' => $domain]);
    
    if ($inserted_domain['verified']) {
        echo json_encode([
            'success' => true, 
            'verified' => true,
            'message' => 'Domain verified and added successfully!'
        ]);
    } else {
        echo json_encode([
            'success' => true, 
            'verified' => false,
            'message' => 'Domain added but NOT verified. Please point A record to ' . $dm->getServerIP()
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}