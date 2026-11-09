<?php
require_once __DIR__ . '/../../../src/load.php';
require_once __DIR__ . '/../../lib/core/RabbitClient.class.php';
require_once __DIR__ . '/../../lib/labs/IPManager.class.php';

use TomLabs\Labs\IPManager;

header('Content-Type: application/json');

$user = AuthMiddleware::requireAuth();


$hash = $_POST['hash'] ?? '';

if (empty($hash)) {
    echo json_encode(['status' => 'error', 'error' => 'Missing hash']); exit;
}

try {
    $instDb = DatabaseConnection::getClient()->selectDatabase('tom_labs_instances_db');
    $collection = $instDb->instances;
    $instance = $collection->findOne([
        'instance_hash' => $hash,
        'user_id' => $user->getUserId()
    ]);

    if (!$instance) {
        echo json_encode(['status' => 'error', 'error' => 'Instance not found']); exit;
    }

    $ipManager = new IPManager();

    $existingDeploy = $instance['deploy'] ?? [];
    $existingIp = $existingDeploy['internal_ip'] ?? null;

    $template = $instance['template'] ?? 'essentials';
    $templateLabels = [
        'essentials' => 'Essentials Lab', 'gui_essentials' => 'GUI Essentials Lab',
        'minio' => 'MinIO Service', 'n8n' => 'n8n Automation', 'docker_lab' => 'Docker Lab',
    ];
    $labLabel = $templateLabels[$template] ?? (ucfirst(str_replace('_', ' ', $template)) . ' Lab');

    $domains = $_POST['domains'] ?? [];
    $expose_web = filter_var($_POST['expose_web'] ?? false, FILTER_VALIDATE_BOOLEAN);
    if (!empty($domains)) $expose_web = true;
    $code_domain = $_POST['code_domain'] ?? ($hash . ".tomweb.shop");

    if (empty($existingIp)) {
        // First deploy — reserve the next free IP for this user and label it
        $internalIp = $ipManager->reserve($user->getEmail(), $user->getUserId());
        $ipManager->describe($internalIp, $labLabel, $template);

        $collection->updateOne(
            ['instance_hash' => $hash],
            ['$set' => [
                'deploy' => [
                    'internal_ip'   => $internalIp,
                    'storage_path'  => storage_base_path() . "/" . md5($user->getEmail()),
                    'lab_type'      => $instance['template'] ?? 'essentials',
                    'domains'       => $domains,
                    'code_domain'   => $code_domain,
                    'expose_web'    => $expose_web,
                    'status'        => 'deploying',
                    'created_at'    => time(),
                    'credentials'   => $existingDeploy['credentials'] ?? (object)[],
                    'init_script'   => $existingDeploy['init_script'] ?? '',
                    'http_proxies'  => $existingDeploy['http_proxies'] ?? [],
                    'always_on'     => $existingDeploy['always_on'] ?? false,
                    'image'         => $existingDeploy['image'] ?? null,
                ],
                'status' => 'deploying',
                'updated_at' => new MongoDB\BSON\UTCDateTime()
            ]]
        );
    } else {
        $internalIp = $existingIp;
        $ipManager->describe($internalIp, $labLabel, $template);

        $collection->updateOne(
            ['instance_hash' => $hash],
            ['$set' => [
                'deploy.domains'     => $domains,
                'deploy.expose_web'  => $expose_web,
                'deploy.code_domain' => $code_domain,
                'deploy.status'      => 'deploying',
                'status'             => 'deploying',
                'updated_at'         => new MongoDB\BSON\UTCDateTime()
            ]]
        );
    }

    $rabbit = new RabbitClient();
    $rabbit->sendToQueue('labs_jobs', [
        'action'     => 'deploy',
        'lab'        => 'instance',
        'hash'       => $hash,
        'user'       => $user->getUsername(),
        'vsc_domain' => $code_domain
    ]);

    // Stamp last_usage so admins can tell used IPs from assigned-but-never-deployed ones
    $ipManager->touchUsage($internalIp, 'Deployed ' . $labLabel, $template);

    echo json_encode([
        'status'  => 'success',
        'message' => 'Deployment queued',
        'hash'    => $hash,
        'ip'      => $internalIp,
        'queued'  => true
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => errors_from_exception($e, 'instances/deploy_instance')['error']]);
}
