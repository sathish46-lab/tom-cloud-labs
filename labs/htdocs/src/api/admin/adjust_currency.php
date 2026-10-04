<?php
/**
 * POST /api/admin/adjust_currency
 *
 * Crediting or debiting one account's Zeal / Jolt balance. Every adjustment
 * requires a reason, is written to the `transactions` ledger (shown on the
 * Transaction Monitor) and to the audit log.
 */
require_once __DIR__ . '/../../load.php';
require_once __DIR__ . '/../../utils/currency.php';

header('Content-Type: application/json');

$admin = AuthMiddleware::requireAdmin();
AuthMiddleware::requireCsrf();

$result = currency_adjust(
    (string)($_POST['email'] ?? ''),
    (string)($_POST['currency'] ?? ''),
    (string)($_POST['direction'] ?? ''),
    $_POST['amount'] ?? null,
    (string)($_POST['reason'] ?? ''),
    (string)($admin->getEmail() ?? 'unknown'),
    (string)($_POST['type'] ?? 'Admin Adjustment')
);

echo json_encode($result);
