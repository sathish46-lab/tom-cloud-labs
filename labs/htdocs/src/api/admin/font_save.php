<?php
require_once __DIR__ . '/../../load.php';
require_once __DIR__ . '/../../lib/core/SiteFont.class.php';

$admin = AuthMiddleware::requireAdmin();
AuthMiddleware::requireCsrf();

$family = (string)($_POST['family'] ?? '');
$result = SiteFont::save($family);

if (($result['status'] ?? '') === 'success') {
    AuditLog::log(
        'update',
        'settings',
        'site_font',
        ['family' => $result['family'] ?? '', 'weights' => $result['weights'] ?? 'default'],
        (string)($admin->getUserId() ?? 'unknown')
    );
} else {
    errors_report(['context' => 'admin/font_save', 'message' => 'font_save: ' . ($result['error'] ?? 'unknown')]);
}

api_json($result, 200);
