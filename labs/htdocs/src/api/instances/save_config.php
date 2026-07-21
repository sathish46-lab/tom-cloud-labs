<?php
require_once __DIR__ . '/../../load.php';

if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');
$user = Session::getUser();
$userId = (int)$user->getUserId();
$username = $user->getUsername() ?? '';
$email = $user->getEmail() ?? '';

$input = json_decode(file_get_contents('php://input'), true);
$hash = $input['slug'] ?? '';
$config = $input['config'] ?? [];

if (empty($hash)) {
    echo json_encode(['status' => 'error', 'error' => 'Instance hash is required']);
    exit;
}

$db = DatabaseConnection::getClient()->selectDatabase('tom_labs_instances_db');
$instance = $db->instances->findOne(['instance_hash' => $hash, 'user_id' => $userId]);

// Fallback: try by slug for old URLs
if (!$instance) {
    $instance = $db->instances->findOne(['slug' => $hash, 'user_id' => $userId]);
}

if (!$instance) {
    echo json_encode(['status' => 'error', 'error' => 'Instance not found']);
    exit;
}

// 1. Save config fields to the instance document in MongoDB
$updateFields = ['updated_at' => new MongoDB\BSON\UTCDateTime()];
$allowedFields = [
    'name', 'description', 'stability', 'cpu', 'memory', 'ports', 'network',
    'ssh_enabled', 'users', 'bind_mounts',
    'run_as_signed_in_user', 'home_mount_enabled', 'home_mount_path'
];
foreach ($allowedFields as $field) {
    if (array_key_exists($field, $config)) {
        $updateFields[$field] = $config[$field];
    }
}

$db->instances->updateOne(['_id' => $instance['_id']], ['$set' => $updateFields]);

// 2. Also update config.json in the file store so it stays in sync
$instanceId = $instance['_id'];
$templateFolder = InstanceFileStore::resolveTemplateFolder($instance);
if ($templateFolder) {
    $configFile = InstanceFileStore::getFile($instanceId, $templateFolder, 'config.json');
    if ($configFile && !empty($configFile['content'])) {
        $json = json_decode($configFile['content'], true);
        if (is_array($json)) {
            // Map UI fields → config.json structure
            if (isset($config['name'])) {
                $json['lab_name'] = $config['name'];
            }
            if (isset($config['stability'])) {
                $json['stability'] = $config['stability'];
            }
            if (isset($config['cpu'])) {
                $json['resources']['cpus'] = (string) $config['cpu'];
            }
            if (isset($config['memory'])) {
                $json['resources']['memory'] = $config['memory'];
            }
            if (isset($config['network'])) {
                $json['network']['mode'] = $config['network'];
            }
            // Update service ports from the ports field (space-separated string or array)
            if (isset($config['ports'])) {
                $ports = is_array($config['ports']) ? $config['ports'] : explode(' ', (string) $config['ports']);
                $ports = array_filter(array_map('trim', $ports));
                if (!empty($ports) && isset($json['services'])) {
                    $portIdx = 0;
                    foreach ($json['services'] as $svcName => &$svc) {
                        if (isset($svc['port']) && isset($ports[$portIdx])) {
                            $svc['port'] = (int) $ports[$portIdx];
                            $portIdx++;
                        }
                    }
                    unset($svc);
                }
            }

            $newContent = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            InstanceFileStore::saveFile($instanceId, $templateFolder, 'config.json', $newContent, $username, $email);
        }
    }
}

echo json_encode(['status' => 'success']);
