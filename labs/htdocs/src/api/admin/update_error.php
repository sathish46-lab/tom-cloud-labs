<?php
/**
 * POST /api/admin/update_error
 *
 * Move one audited error through the review queue:
 * open → acknowledged → resolved, or back to open to re-open it.
 */
require_once __DIR__ . '/../../load.php';
require_once __DIR__ . '/../../utils/errors.php';

header('Content-Type: application/json');

$admin = AuthMiddleware::requireAdmin();
AuthMiddleware::requireCsrf();

$ref    = trim((string)($_POST['ref'] ?? ''));
$status = trim((string)($_POST['status'] ?? ''));

if ($ref === '') {
    echo json_encode(['status' => 'error', 'error' => 'Missing reference.']);
    exit;
}

$ok = errors_set_status($ref, $status, (string)($admin->getEmail() ?? ''));

echo json_encode([
    'status'  => $ok ? 'success' : 'error',
    'error'   => $ok ? '' : 'No such error reference, or invalid status.',
    'ref'     => strtoupper($ref),
    'state'   => $status,
]);
