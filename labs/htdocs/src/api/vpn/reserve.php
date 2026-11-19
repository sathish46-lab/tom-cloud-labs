<?php
require_once __DIR__ . '/../../../src/load.php';

$user = AuthMiddleware::requireAuth();

$data = json_decode(file_get_contents('php://input'), true);
$ip = $data['ip'] ?? null;

if (!$ip) {
    api_json(['result' => false], 200);
}

$db = DatabaseConnection::getDefaultDatabase();

try {
    $db->ip_registry->updateOne(
        ['ip_addr' => $ip, 'status' => 'available'],
        ['$set' => ['status' => 'reserved', 'email' => $user->getEmail(), 'user_id' => $user->getUserId(), 'reserved_at' => time()]]
    );
    api_json(['result' => true], 200);
} catch (Exception $e) {
    api_json(['result' => false], 200);
}
