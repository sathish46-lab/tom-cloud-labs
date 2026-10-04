<?php
require_once __DIR__ . '/../../load.php';
require_once __DIR__ . '/../../utils/storage.php';

header('Content-Type: application/json');

$admin = AuthMiddleware::requireAdmin();
AuthMiddleware::requireCsrf();

try {
    $db = DatabaseConnection::getDefaultDatabase();
    $summary = storage_audit_run($db);

    if (isset($summary['error'])) {
        echo json_encode(['status' => 'error', 'error' => $summary['error']]);
        exit;
    }

    AuditLog::log(
        'update',
        'storage_pool',
        'default',
        [
            'action'     => 're_audit',
            'tenants'    => (int)$summary['tenants'],
            'present'    => (int)$summary['present'],
            'bytes'      => (int)$summary['bytes'],
            'over_quota' => (int)($summary['over_quota'] ?? 0),
        ],
        (string)($admin->getUserId() ?? 'unknown')
    );

    echo json_encode(['status' => 'success'] + $summary);
} catch (Throwable $e) {
    error_log('audit_storage: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'error' => 'Storage audit failed']);
}
