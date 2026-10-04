<?php
require_once __DIR__ . '/../../load.php';

header('Content-Type: application/json');

$admin = AuthMiddleware::requireAdmin();
AuthMiddleware::requireCsrf();

$fromEmail = trim($_POST['from_email'] ?? '');
$toEmail   = trim($_POST['to_email'] ?? '');
$type      = trim($_POST['type'] ?? '');
$amount    = (int)($_POST['amount'] ?? 0);
$confirm   = trim($_POST['confirm'] ?? '');

if (!in_array($type, ['zeal', 'jolt'], true)) {
    echo json_encode(['status' => 'error', 'error' => 'Type must be zeal or jolt']); exit;
}
if ($fromEmail === '' || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)
    || $toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'error' => 'Both a valid from and to email are required']); exit;
}
if ($fromEmail === $toEmail) {
    echo json_encode(['status' => 'error', 'error' => 'Cannot transfer to the same user']); exit;
}
if ($amount < 1) {
    echo json_encode(['status' => 'error', 'error' => 'Amount must be at least 1']); exit;
}
// Typed-phrase confirmation — the operator must type the exact target email.
if ($confirm !== 'TRANSFER ' . $toEmail) {
    echo json_encode(['status' => 'error', 'error' => 'Type exactly: TRANSFER ' . $toEmail]); exit;
}

try {
    $db = DatabaseConnection::getDefaultDatabase();

    $fromUser = $db->users->findOne(['email' => $fromEmail]);
    $toUser   = $db->users->findOne(['email' => $toEmail]);
    if (!$fromUser || !$toUser) {
        echo json_encode(['status' => 'error', 'error' => 'One or both users do not exist']); exit;
    }

    // Make sure both balance documents exist before the conditional decrement.
    foreach ([$fromEmail, $toEmail] as $em) {
        $db->user_stats->updateOne(
            ['user_email' => $em],
            ['$setOnInsert' => ['user_email' => $em, 'zeal' => 0, 'jolt' => 0]],
            ['upsert' => true]
        );
    }

    // Conditional update: never drive the donor negative even under concurrency.
    $debit = $db->user_stats->updateOne(
        ['user_email' => $fromEmail, $type => ['$gte' => $amount]],
        ['$inc' => [$type => -$amount]]
    );
    if ($debit->getMatchedCount() === 0) {
        $current = $db->user_stats->findOne(['user_email' => $fromEmail]);
        $balance = (int)($current[$type] ?? 0);
        echo json_encode([
            'status'  => 'error',
            'error'   => 'Insufficient ' . $type . ' balance (' . $balance . ' available)',
            'balance' => $balance,
        ]);
        exit;
    }

    $db->user_stats->updateOne(['user_email' => $toEmail], ['$inc' => [$type => $amount]]);

    // Keep the denormalised copy on the user document in step.
    $db->users->updateOne(['email' => $fromEmail], ['$inc' => ["zeal_stats.$type" => -$amount]]);
    $db->users->updateOne(['email' => $toEmail],   ['$inc' => ["zeal_stats.$type" =>  $amount]]);

    $fromAfter = $db->user_stats->findOne(['user_email' => $fromEmail]);
    $toAfter   = $db->user_stats->findOne(['user_email' => $toEmail]);

    AuditLog::log(
        'transfer',
        'user',
        (string)($fromUser['user_id'] ?? $fromEmail),
        [
            'type'       => $type,
            'amount'     => $amount,
            'from_email' => $fromEmail,
            'to_email'   => $toEmail,
            'from_after' => (int)($fromAfter[$type] ?? 0),
            'to_after'   => (int)($toAfter[$type] ?? 0),
        ],
        (string)($admin->getUserId() ?? 'unknown')
    );

    echo json_encode([
        'status'  => 'success',
        'type'    => $type,
        'amount'  => $amount,
        'from'    => ['email' => $fromEmail, 'balance' => (int)($fromAfter[$type] ?? 0)],
        'to'      => ['email' => $toEmail,   'balance' => (int)($toAfter[$type] ?? 0)],
    ]);
} catch (Throwable $e) {
    errors_report(['context' => 'admin/transfer_entitlements', 'message' => 'transfer_entitlements: ' . $e->getMessage()]);
    echo json_encode(['status' => 'error', 'error' => 'Transfer failed']);
}
