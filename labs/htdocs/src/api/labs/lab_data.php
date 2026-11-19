<?php
require_once __DIR__ . '/../../../src/load.php';

$user = AuthMiddleware::requireAuth();

$labHash = $_GET['hash'] ?? '';
$datatype = $_GET['type'] ?? 'all';

if (empty($labHash)) {
    api_json(['error' => 'Missing hash parameter'], 400);
}

// Verify ownership: check if this hash belongs to the user
$isOwner = false;
foreach (['essentials', 'minio', 'n8n', 'docker_lab', 'gui_essentials'] as $type) {
    if ($user->getLabHash($type) === $labHash) {
        $isOwner = true;
        break;
    }
}

if (!$isOwner) {
    api_json(['error' => 'Access denied'], 403);
}

try {
    $db = DatabaseConnection::getClient()->selectDatabase('tom_labs_db');
    $inst = $db->machine_labs->findOne(
        ['instance_hash' => $labHash],
        ['projection' => [
            'lab_type' => 1,
            'internal_ip' => 1,
            'credentials' => 1,
            'status' => 1,
            'code_domain' => 1,
            'gui_domain' => 1,
            'domains' => 1,
            'expose_web' => 1,
            'http_proxies' => 1
        ]]
    );

    if (!$inst) {
        api_json(['error' => 'Lab not found'], 404);
    }

    $labType = $inst['lab_type'] ?? 'essentials';
    $currentUsername = $user->getUsername();

    $response = [];

    if ($datatype === 'config' || $datatype === 'all') {
        $configData = [
            'ip' => $inst['internal_ip'] ?? null,
            'domains' => $inst['domains'] ?? [],
            'code_domain' => $inst['code_domain'] ?? null,
            'gui_domain' => $inst['gui_domain'] ?? null,
            'expose_web' => $inst['expose_web'] ?? [],
            'http_proxies' => $inst['http_proxies'] ?? [],
        ];
        $labConfig = \TomLabs\Labs\LabTemplateConfig::getTemplate($labType, $configData, $currentUsername);
        $response['config'] = $labConfig;
    }

    if ($datatype === 'domains' || $datatype === 'all') {
        $dm = new DomainManager();
        $domainUsageMap = $dm->getDomainUsageMap($user->getUserId());
        $response['domains'] = $domainUsageMap;
    }

    api_json($response, 200);

} catch (Exception $e) {
    api_json(['error' => 'Internal server error'], 500);
}
