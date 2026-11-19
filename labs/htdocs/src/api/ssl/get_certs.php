<?php
/**
 * GET /api/ssl/get_certs
 * Returns all SSL certificates for the current user.
 */
require_once "../../load.php";
require_once "../../lib/core/SSLManager.class.php";

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$user = AuthMiddleware::requireAuth();

if (!$user) {
api_json(['success' => false, 'error' => 'User session invalid'], 400);
}

try {
    $ssl = new SSLManager();
    $certs = $ssl->getCertificates($user->getUserId());
    api_json([
        'success' => true,
        'certificates' => $certs,
        'auto_managed' => $ssl->getAutoManagedCount($certs),
        'cached_until' => date('H:i:s', time() + 900)
    ], 200);
} catch (Exception $e) {
    api_json(['success' => false, 'error' => errors_from_exception($e, 'ssl/get_certs')['error']], 500);
}
