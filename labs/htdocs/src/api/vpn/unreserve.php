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
        ['ip_addr' => $ip, 'status' => 'reserved'],
        ['$set' => ['status' => 'available'], '$unset' => [
            'email' => '', 'user_id' => '', 'reserved_at' => '', 'reserved_to' => '',
            'service_type' => '', 'label' => '', 'last_deploy' => '', 'allocated_to' => '',
            'resource_type' => '', 'resource_id' => '', 'device_name' => '', 'device_type' => '',
        ]]
    );
    api_json(['result' => true], 200);
} catch (Exception $e) {
    api_json(['result' => false], 200);
}
