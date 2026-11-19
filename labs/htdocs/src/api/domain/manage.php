<?php
/**
 * GET /api/domain/manage?domain_id=<id>
 * Returns full domain details + SSL info for the Manage modal.
 */
require_once "../../load.php";
require_once "../../lib/core/DomainManager.class.php";
require_once "../../lib/core/SSLManager.class.php";

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$user = AuthMiddleware::requireAuth();

if (!$user) {
api_json(['success' => false, 'error' => 'User session invalid'], 400);
}

$domainId = $_GET['domain_id'] ?? '';
if (empty($domainId) || !preg_match('/^[0-9a-f]{24}$/i', $domainId)) {
api_json(['success' => false, 'error' => 'Invalid domain_id format'], 400);
}

try {
    $db = DatabaseConnection::getClient()->selectDatabase('tom_labs_db');
    $userId = $user->getUserId();

    // Fetch domain document
    $domain = $db->domains->findOne([
        '_id' => new MongoDB\BSON\ObjectId($domainId),
        'user_id' => ['$in' => [(string)$userId, (int)$userId]]
    ]);

    if (!$domain) {
api_json(['success' => false, 'error' => 'Domain not found'], 400);
    }

    $dm = new DomainManager();
    $serverIP = $dm->getServerIP();

    // Get usage info
    $usageInfo = $dm->getDomainUsage($userId, $domain['domain']);

    // Get SSL info
    $ssl = new SSLManager();
    $certs = $ssl->getCertificates($userId);
    $sslInfo = null;

    foreach ($certs as $cert) {
        if (in_array($domain['domain'], $cert['sans'] ?? [])) {
            $sslInfo = [
                'main_domain' => $cert['main_domain'],
                'resolver' => $cert['resolver'],
                'issued' => $cert['issued'],
                'expires' => $cert['expires'],
                'expires_timestamp' => $cert['expires_timestamp'],
                'days_left' => $cert['days_left'],
                'is_valid' => $cert['is_valid'],
                'sans' => $cert['sans'],
                'used_by' => $cert['used_by']
            ];
            break;
        }
    }

    api_json([
        'success' => true,
        'domain' => [
            'id' => (string)$domain['_id'],
            'name' => $domain['domain'],
            'type' => $domain['type'],
            'verified' => !empty($domain['verified']),
            'created_at' => $domain['created_at'] ?? null,
            'last_checked' => $domain['last_checked'] ?? null,
            'last_ip' => $domain['last_ip'] ?? null,
            'server_ip' => $serverIP
        ],
        'usage' => $usageInfo ? [
            'status' => $usageInfo['status'] ?? 'unknown',
            'service' => $usageInfo['usage'] ?? null,
            'lab_type' => $usageInfo['lab_type'] ?? null,
            'instance_hash' => $usageInfo['instance_hash'] ?? null
        ] : null,
        'ssl' => $sslInfo
    ], 200);
} catch (Exception $e) {
    api_json(['success' => false, 'error' => errors_from_exception($e, 'domain/manage')['error']], 500);
}
