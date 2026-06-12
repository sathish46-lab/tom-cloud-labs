<?php
/**
 * GET /api/ssl/troubleshoot?domain=<main_domain>
 * Verifies DNS for all SANs in a specific certificate.
 */
require_once "../../load.php";
require_once "../../lib/core/SSLManager.class.php";

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user = Session::getUser();
if (!$user) {
    echo json_encode(['success' => false, 'error' => 'User session invalid']);
    exit;
}

$domain = $_GET['domain'] ?? '';

// Validate domain format
if (empty($domain) || !preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9.-]*[a-zA-Z0-9])?$/', $domain)) {
    echo json_encode(['success' => false, 'error' => 'Invalid or missing domain parameter']);
    exit;
}

try {
    $ssl = new SSLManager();
    $result = $ssl->troubleshootCertificate($domain, $user->getUserId());
    echo json_encode([
        'success' => true,
        'result' => $result
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
