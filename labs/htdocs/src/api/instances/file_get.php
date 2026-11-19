<?php
require_once __DIR__ . '/../../../src/load.php';

$user = AuthMiddleware::requireAuth();

try {
    
    $userId = (int) $user->getUserId();
    $slug = $_GET['slug'] ?? '';
    $path = $_GET['path'] ?? '';

    if (empty($slug) || empty($path)) {
api_json(['status' => 'error', 'error' => 'Missing parameters'], 400);
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

    $file = InstanceFileStore::getFile($instanceHash, $templateFolder, $path);
    if (!$file) {
api_json(['status' => 'error', 'error' => 'File not found: ' . $path], 400);
    }

    api_json([
        'status' => 'success',
        'path' => $file['base_path'],
        'name' => $file['name'],
        'content' => $file['content'] ?? '',
        'modified' => $file['modified'],
    ], 200);
} catch (Exception $e) {
    errors_report(['context' => 'instances/file_get', 'message' => 'file_get error: ' . $e->getMessage()]);
    api_json(['status' => 'error', 'error' => 'Server error: ' . errors_from_exception($e, 'instances/file_get')['error']], 500);
}
