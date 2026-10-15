<?php
require_once __DIR__ . '/../../load.php';
require_once __DIR__ . '/../../lib/core/SiteSeo.class.php';

header('Content-Type: application/json');

$admin = AuthMiddleware::requireAdmin();
AuthMiddleware::requireCsrf();

$result = SiteSeo::save([
    'title'       => (string)($_POST['title'] ?? ''),
    'description' => (string)($_POST['description'] ?? ''),
    'keywords'    => (string)($_POST['keywords'] ?? ''),
    'author'      => (string)($_POST['author'] ?? ''),
]);

if (($result['status'] ?? '') === 'success') {
    AuditLog::log(
        'update',
        'settings',
        'site_seo',
        [
            'title'       => $result['title'] ?? '',
            'description' => mb_substr((string)($result['description'] ?? ''), 0, 80),
            'keywords'    => $result['keywords'] ?? '',
            'author'      => $result['author'] ?? '',
        ],
        (string)($admin->getUserId() ?? 'unknown')
    );
} else {
    errors_report(['context' => 'admin/site_seo_save', 'message' => 'site_seo_save: ' . ($result['error'] ?? 'unknown')]);
}

echo json_encode($result);
exit;
