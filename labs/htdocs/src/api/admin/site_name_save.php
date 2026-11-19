<?php
require_once __DIR__ . '/../../load.php';
require_once __DIR__ . '/../../lib/core/SiteBrand.class.php';

$admin = AuthMiddleware::requireAdmin();
AuthMiddleware::requireCsrf();

$name = (string)($_POST['name'] ?? '');
$result = SiteBrand::save($name);

if (($result['status'] ?? '') === 'success') {
    AuditLog::log(
        'update',
        'settings',
        'site_name',
        ['name' => $result['name'] ?? ''],
        (string)($admin->getUserId() ?? 'unknown')
    );
} else {
    errors_report(['context' => 'admin/site_name_save', 'message' => 'site_name_save: ' . ($result['error'] ?? 'unknown')]);
}

api_json($result, 200);
