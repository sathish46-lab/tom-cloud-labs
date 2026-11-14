<?php
require_once __DIR__ . '/../../../src/load.php';

use TomLabs\Services\ServiceConfig;

header('Content-Type: application/json');

$user = AuthMiddleware::requireAdmin();

$service = $_POST['service'] ?? '';
$state = (isset($_POST['state']) && $_POST['state'] === 'true');

if ($service === '') {
    echo json_encode(['status' => 'error', 'error' => 'Service missing']); exit;
}

// Validate service slug against known services
$allServices = ServiceConfig::all();
if (!isset($allServices[$service])) {
    echo json_encode(['status' => 'error', 'error' => 'Unknown service name']); exit;
}

try {
    // Actually stop/start the container via labsctl first
    $labsctlAction = $state ? 'start' : 'stop';
    $command = sprintf(
        'labsctl service %s --email=%s %s',
        escapeshellarg($service),
        escapeshellarg($user->getEmail()),
        escapeshellarg($labsctlAction)
    );
    exec($command . ' 2>&1', $labsctlLines, $labsctlExitCode);
    $labsctlOutput = trim(implode("\n", $labsctlLines));
    if ($labsctlExitCode !== 0) {
        echo json_encode([
            'status' => 'error',
            'error' => 'Failed to ' . $labsctlAction . ' service: ' . $labsctlOutput,
        ]);
        exit;
    }

    // Persist the flag only after the container action succeeded
    \TomLabs\Services\ServiceFlags::set($service, $state);
    echo json_encode(['status' => 'success', 'service' => $service, 'enabled' => $state]);
} catch (\InvalidArgumentException $e) {
    error_log('service_toggle rejected: ' . $e);
    echo json_encode(['status' => 'error', 'error' => 'Unknown service name']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'error' => errors_from_exception($e, 'admin/service_toggle')['error']]);
}
