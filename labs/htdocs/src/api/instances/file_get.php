<?php
require_once __DIR__ . '/../../../src/load.php';

header('Content-Type: application/json');

if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    echo json_encode(['status' => 'error', 'error' => 'Unauthorized']);
    exit;
}

try {
    $user = Session::getUser();
    $userId = (int) $user->getUserId();
    $slug = $_GET['slug'] ?? '';
    $path = $_GET['path'] ?? '';

    if (empty($slug) || empty($path)) {
        echo json_encode(['status' => 'error', 'error' => 'Missing parameters']);
        exit;
    }

    $db = DatabaseConnection::getClient()->selectDatabase('tom_labs_instances_db');
    $instance = $db->instances->findOne(['instance_hash' => $slug]);
    if (!$instance) {
        $instance = $db->instances->findOne(['slug' => $slug]);
    }
    if (!$instance || (int) ($instance['user_id'] ?? 0) !== $userId) {
        echo json_encode(['status' => 'error', 'error' => 'Instance not found or forbidden']);
        exit;
    }

    $instanceHash = $instance['instance_hash'] ?? $slug;
    $templateFolder = InstanceFileStore::resolveTemplateFolder($instance);
    if (!$templateFolder) {
        echo json_encode(['status' => 'error', 'error' => 'No template for instance']);
        exit;
    }

    $file = InstanceFileStore::getFile($instanceHash, $templateFolder, $path);
    if (!$file) {
        echo json_encode(['status' => 'error', 'error' => 'File not found: ' . $path]);
        exit;
    }

    echo json_encode([
        'status' => 'success',
        'path' => $file['base_path'],
        'name' => $file['name'],
        'content' => $file['content'] ?? '',
        'modified' => $file['modified'],
    ]);
} catch (Exception $e) {
    error_log('file_get error: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'error' => 'Server error: ' . $e->getMessage()]);
}
