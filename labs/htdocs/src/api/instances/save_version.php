<?php
require_once __DIR__ . '/../../../src/load.php';

$user = AuthMiddleware::requireAuth();

$hash = $_POST['hash'] ?? '';
$label = $_POST['label'] ?? 'untitled';

if (empty($hash)) {
    api_json(['status' => 'error', 'error' => 'Missing hash'], 400);
}

try {
    $instDb = DatabaseConnection::getClient()->selectDatabase('tom_labs_instances_db');
    $instance = $instDb->instances->findOne([
        'instance_hash' => $hash,
        'user_id' => $user->getUserId()
    ]);

    if (!$instance) {
        api_json(['status' => 'error', 'error' => 'Instance not found'], 400);
    }

    $filesDb = DatabaseConnection::getClient()->selectDatabase('tom_labs_instances_db');
    $fileDoc = $filesDb->instance_files->findOne(['instance_id' => $hash]);

    $instDb->instance_versions->insertOne([
        'instance_hash'  => $hash,
        'label'          => preg_replace('/[^a-zA-Z0-9._-]/', '', $label),
        'files_snapshot' => $fileDoc['files'] ?? new MongoDB\BSON\Document(),
        'config_snapshot' => [
            'name'        => $instance['name'] ?? '',
            'template'    => $instance['template'] ?? 'essentials',
            'image'       => $instance['image'] ?? 'ubuntu:24.04',
            'cpu'         => $instance['cpu'] ?? '2',
            'memory'      => $instance['memory'] ?? '512m',
            'ports'       => $instance['ports'] ?? [],
            'network'     => $instance['network'] ?? 'Default (wg0)',
            'ssh_enabled' => $instance['ssh_enabled'] ?? false,
            'users'       => $instance['users'] ?? [],
            'bind_mounts' => $instance['bind_mounts'] ?? [],
        ],
        'created_by'     => $user->getUsername(),
        'created_at'     => time()
    ]);

    api_json(['status' => 'success', 'message' => 'Version saved'], 400);
} catch (Exception $e) {
    api_json(['status' => 'error', 'error' => errors_from_exception($e, 'instances/save_version')['error']], 500);
}
