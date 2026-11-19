<?php
/**
 * POST /api/admin/update_error
 *
 * Move one audited error through the review queue:
 * open → acknowledged → resolved, or back to open to re-open it.
 */
require_once __DIR__ . '/../../load.php';
require_once __DIR__ . '/../../utils/errors.php';

$admin = AuthMiddleware::requireAdmin();
AuthMiddleware::requireCsrf();

$ref    = trim((string)($_POST['ref'] ?? ''));
$status = trim((string)($_POST['status'] ?? ''));

if ($ref === '') {
api_json(['status' => 'error', 'error' => 'Missing reference.'], 400);
}

$ok = errors_set_status($ref, $status, (string)($admin->getEmail() ?? ''));

api_json([
    'status'  => $ok ? 'success' : 'error',
    'error'   => $ok ? '' : 'No such error reference, or invalid status.',
    'ref'     => strtoupper($ref),
    'state'   => $status,
], 200);
