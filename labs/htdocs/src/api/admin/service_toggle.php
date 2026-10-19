<?php
require_once __DIR__ . '/../../../src/load.php';

header('Content-Type: application/json');

$user = AuthMiddleware::requireAdmin();

$service = $_POST['service'] ?? '';
$state = (isset($_POST['state']) && $_POST['state'] === 'true');

if ($service === '') {
    echo json_encode(['status' => 'error', 'error' => 'Service missing']); exit;
}

try {
    \TomLabs\Services\ServiceFlags::set($service, $state);
    echo json_encode(['status' => 'success', 'service' => $service, 'enabled' => $state]);
} catch (\InvalidArgumentException $e) {
    error_log('service_toggle rejected: ' . $e);
    echo json_encode(['status' => 'error', 'error' => 'Unknown service name']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'error' => errors_from_exception($e, 'admin/service_toggle')['error']]);
}
