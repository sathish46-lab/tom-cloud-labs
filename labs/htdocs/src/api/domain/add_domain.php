<?php
ob_start();
require_once "../../load.php";
require_once "../../lib/core/DomainManager.class.php";

if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    http_response_code(401);
    echo 'Unauthorized';
    exit;
}

$user = Session::getUser();
$data = json_decode(file_get_contents('php://input'), true);

$domain = trim(strtolower($data['domain'] ?? ''));
$type = $data['type'] ?? 'custom';

if (empty($domain)) {
    http_response_code(400);
    echo 'Domain name is required.';
    exit;
}

try {
    $db = DatabaseConnection::getClient()->selectDatabase('tom_labs_db');
    
    // Enforce limits: 20 for Selfmade/Tom domains, Unlimited for Custom domains
    if (strtolower($type) !== 'custom') {
        $tomDomainCount = $db->domains->countDocuments([
            'user_id' => $user->getUserId(),
            'type' => ['$ne' => 'custom']
        ]);
        
        if ($tomDomainCount >= 20) {
            http_response_code(400);
            echo 'Domain limit reached. You can only create up to 20 Tom domains. Custom domains are unlimited.';
            exit;
        }
    }

    $dm = new DomainManager();
    
    // Use the DomainManager's addDomain method which handles everything
    $result = $dm->addDomain($user->getUserId(), $user->getEmail(), $domain, $type);
    // Get the verification status
    $db = DatabaseConnection::getClient()->selectDatabase('tom_labs_db');
    $inserted_domain = $db->domains->findOne(['domain' => $domain]);
    
    if ($inserted_domain) {
        ob_start();
        $d = $inserted_domain;
        // Make sure $d['_id'] is a string for JS compatibility
        if (isset($d['_id']) && is_object($d['_id'])) {
            $d['_id'] = (string)$d['_id'];
        }
        include __DIR__ . '/../../template/partials/_domain_card.php';
        $html = ob_get_clean();
    } else {
        $html = '';
    }

    // Ensure no PHP warnings/notices corrupt the HTML output
    if (ob_get_length()) ob_clean();

    // Since we are returning pure HTML, if it's verified we could optionally add an HX-Trigger header or similar.
    // For now, just return the HTML directly. If not verified, we can append a warning div or return a special header.
    if (!$inserted_domain['verified']) {
        // Send a custom header that the frontend can read if it wants to show the warning
        header('X-Domain-Verified: false');
    } else {
        header('X-Domain-Verified: true');
    }
    
    echo $html;
    
} catch (\Throwable $e) {
    // Clean buffer to prevent HTML pollution
    if (ob_get_length()) ob_clean();
    http_response_code(400);
    echo $e->getMessage();
}