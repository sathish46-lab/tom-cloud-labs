<?php
require_once "../../load.php";
require_once "../../lib/core/DomainManager.class.php";

$user = AuthMiddleware::requireAuth();

$data = json_decode(file_get_contents('php://input'), true);
$domain_id = $data['domain_id'] ?? '';

if (empty($domain_id)) {
api_json(['success' => false, 'error' => 'Domain ID required'], 400);
}

try {
    $dm = new DomainManager();
    $is_verified = $dm->verifyDomain($domain_id);
    
    api_json([
        'success' => true,
        'verified' => $is_verified,
        'message' => $is_verified ? 'Domain verified!' : 'DNS not pointing to server yet'
    ], 200);
} catch (Exception $e) {
    api_json(['success' => false, 'error' => errors_from_exception($e, 'domain/verify_domain')['error']], 500);
}
