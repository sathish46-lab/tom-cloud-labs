<?php
require_once __DIR__ . '/../../../src/load.php';

use TomLabs\Services\ServiceConfig;

$user = AuthMiddleware::requireAdmin();

$service = $_POST['service'] ?? '';
$state = (isset($_POST['state']) && $_POST['state'] === 'true');

if ($service === '') {
    api_json(['status' => 'error', 'error' => 'Service missing'], 400);
}

// Validate service slug against known services
$allServices = ServiceConfig::all();
if (!isset($allServices[$service])) {
    api_json(['status' => 'error', 'error' => 'Unknown service name'], 400);
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
        api_json([
            'status' => 'error',
            'error' => 'Failed to ' . $labsctlAction . ' service: ' . $labsctlOutput,
        ], 200);
        exit;
    }

    // Persist the flag only after the container action succeeded
    \TomLabs\Services\ServiceFlags::set($service, $state);
    api_json(['status' => 'success', 'service' => $service, 'enabled' => $state], 200);
} catch (\InvalidArgumentException $e) {
    error_log('service_toggle rejected: ' . $e);
    api_json(['status' => 'error', 'error' => 'Unknown service name'], 400);
} catch (Exception $e) {
    api_json(['status' => 'error', 'error' => errors_from_exception($e, 'admin/service_toggle')['error']], 500);
}
