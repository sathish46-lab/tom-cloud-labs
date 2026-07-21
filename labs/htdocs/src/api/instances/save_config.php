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

// 2. Update config.json in the file store
$instanceHash = $instance['instance_hash'] ?? $hash;
$templateFolder = InstanceFileStore::resolveTemplateFolder($instance);

if (!$templateFolder) {
    echo json_encode(['status' => 'success', 'warning' => 'No template folder']);
    exit;
}

// Step A: Try reading existing config.json from user layer (DB)
$userDoc = InstanceFileStore::collection()->findOne(['instance_id' => $instanceHash]);
$userFiles = $userDoc ? (array)($userDoc['files'] ?? []) : [];
$existing = $userFiles['config.json'] ?? null;

$json = null;
if ($existing && is_array($existing) && !empty($existing['content'])) {
    $json = json_decode($existing['content'], true);
    error_log("save_config: Using existing user-layer config.json");
}

// Step B: If not in DB or decode failed, fetch from MinIO
if (!is_array($json)) {
    error_log("save_config: Fetching base config.json from MinIO for template=$templateFolder");
    $base = InstanceFileStore::readBaseFile($templateFolder, 'config.json');
    if ($base && !empty($base['content'])) {
        $json = json_decode($base['content'], true);
        error_log("save_config: MinIO returned " . strlen($base['content']) . " bytes");
    } else {
        error_log("save_config: MinIO readBaseFile returned empty/null");
    }
}

// Step C: Modify and save
if (is_array($json)) {
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

    $newContent = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $modified = InstanceFileStore::saveFile($instanceHash, $templateFolder, 'config.json', $newContent, $username, $email);
    error_log("save_config: saveFile returned modified=$modified, content length=" . strlen($newContent));

    // Remove stale "config" file (no extension)
    InstanceFileStore::deleteNode($instanceHash, 'config');
} else {
    error_log("save_config: SKIP — json is not array after all attempts");
}

echo json_encode(['status' => 'success']);
