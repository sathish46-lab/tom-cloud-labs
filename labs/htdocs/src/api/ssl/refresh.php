<?php
/**
 * GET /api/ssl/refresh
 * Flushes the SSL certificate cache and returns fresh data.
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

try {
    $ssl = new SSLManager();
    $certs = $ssl->refreshCertificates($user->getUserId());
    echo json_encode([
        'success' => true,
        'certificates' => $certs,
        'auto_managed' => $ssl->getAutoManagedCount($certs),
        'refreshed_at' => date('H:i:s'),
        'cached_until' => date('H:i:s', time() + 900)
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
