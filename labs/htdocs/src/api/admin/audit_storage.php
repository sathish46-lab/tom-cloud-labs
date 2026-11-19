<?php
require_once __DIR__ . '/../../load.php';
require_once __DIR__ . '/../../utils/storage.php';

$admin = AuthMiddleware::requireAdmin();
AuthMiddleware::requireCsrf();

try {
    $db = DatabaseConnection::getDefaultDatabase();
    $summary = storage_audit_run($db);

    if (isset($summary['error'])) {
api_json(['status' => 'error', 'error' => $summary['error']], 400);
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

    api_json(['status' => 'success'] + $summary, 200);
} catch (Throwable $e) {
    errors_report(['context' => 'admin/audit_storage', 'message' => 'audit_storage: ' . $e->getMessage()]);
    api_json(['status' => 'error', 'error' => 'Storage audit failed'], 400);
}
