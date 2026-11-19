<?php
require_once __DIR__ . '/../../../src/load.php';

$user = AuthMiddleware::requireAuth();

$userId = (int) $user->getUserId();
$username = $user->getUsername();
$email = $user->getEmail();

$slug = $_POST['slug'] ?? '';
$path = $_POST['path'] ?? '';

if (empty($slug) || empty($path)) {
api_json(['status' => 'error', 'error' => 'Missing parameters'], 400);
}

if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
api_json(['status' => 'error', 'error' => 'No file uploaded'], 400);
}

// S12: File type allowlist for instance assets (code-server context)
$allowedExtensions = [
    'js', 'ts', 'jsx', 'tsx', 'py', 'rb', 'go', 'rs', 'java', 'c', 'cpp', 'h', 'hpp',  // Code
    'html', 'htm', 'css', 'scss', 'less', 'sass',  // Web
    'json', 'yaml', 'yml', 'toml', 'ini', 'cfg', 'conf', 'env',  // Config
    'txt', 'md', 'csv', 'log', 'xml', 'sql',  // Text
    'sh', 'bash', 'zsh', 'fish', 'ps1',  // Scripts
    'php', 'rb', 'pl', 'lua',  // Other code
    'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico',  // Images
    'pdf', 'doc', 'docx', 'xls', 'xlsx',  // Documents
];
$ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allowedExtensions)) {
api_json(['status' => 'error', 'error' => 'File type not allowed for instance assets.'], 400);
}

// Block double-extension attacks
$parts = explode('.', $_FILES['file']['name']);
if (count($parts) > 2) {
api_json(['status' => 'error', 'error' => 'Files with multiple extensions are not allowed.'], 400);
}

$db = DatabaseConnection::getClient()->selectDatabase('tom_labs_instances_db');
$instance = $db->instances->findOne(['instance_hash' => $slug]);
if (!$instance) {
    $instance = $db->instances->findOne(['slug' => $slug]);
}
if (!$instance || (int) ($instance['user_id'] ?? 0) !== $userId) {
api_json(['status' => 'error', 'error' => 'Instance not found or forbidden'], 400);
}

$instanceHash = $instance['instance_hash'] ?? $slug;
$templateFolder = InstanceFileStore::resolveTemplateFolder($instance);
if (!$templateFolder) {
api_json(['status' => 'error', 'error' => 'No template for instance'], 400);
}

$tmpPath = $_FILES['file']['tmp_name'];
$s3Key = 'labassets/instances/' . $instanceHash . '/' . ltrim($path, '/');

$uploaded = Storage::upload($tmpPath, $s3Key);
if (!$uploaded) {
api_json(['status' => 'error', 'error' => 'MinIO upload failed'], 400);
}

// Store binary file in user layer via single-doc model
InstanceFileStore::deleteNode($instanceHash, $path);
InstanceFileStore::collection()->updateOne(
    ['instance_id' => $instanceHash],
    ['$set' => [
        'files.' . ltrim($path, '/') => [
            'content' => null,
            'size' => $_FILES['file']['size'],
            's3_key' => $s3Key,
        ],
        'username' => $username,
        'email' => $email,
        'updated_at' => new MongoDB\BSON\UTCDateTime(),
    ]]
);

api_json(['status' => 'success', 's3_key' => $s3Key], 200);
