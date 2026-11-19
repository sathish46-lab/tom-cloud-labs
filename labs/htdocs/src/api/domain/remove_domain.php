<?php
require_once "../../load.php";

$user = AuthMiddleware::requireAuth();

$data = json_decode(file_get_contents('php://input'), true);
$domainId = $data['domain_id'] ?? null;

if (!$domainId) {
    api_json(['success' => false, 'error' => 'Invalid request'], 400);
}

try {
    $db = DatabaseConnection::getClient()->selectDatabase('tom_labs_db');
    
    // Security check: Soft-delete only if it belongs to the logged-in user
    $result = $db->domains->updateOne([
        '_id' => new MongoDB\BSON\ObjectId($domainId),
        'user_id' => $user->getUserId()
    ], [
        '$set' => [
            'status' => 'deleted',
            'deleted_at' => new MongoDB\BSON\UTCDateTime(),
            'deleted_by' => $user->getEmail(),
        ]
    ]);

    if ($result->getModifiedCount() > 0) {
        api_json(['success' => true], 200);
    } else {
        throw new Exception("Domain not found or unauthorized access.");
    }
} catch (Exception $e) {
    api_json(['success' => false, 'error' => errors_from_exception($e, 'domain/remove_domain')['error']], 500);
}
