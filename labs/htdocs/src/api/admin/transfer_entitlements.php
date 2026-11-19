<?php
require_once __DIR__ . '/../../load.php';

$admin = AuthMiddleware::requireAdmin();
AuthMiddleware::requireCsrf();

$fromEmail = trim($_POST['from_email'] ?? '');
$toEmail   = trim($_POST['to_email'] ?? '');
$type      = trim($_POST['type'] ?? '');
$amount    = (int)($_POST['amount'] ?? 0);
$confirm   = trim($_POST['confirm'] ?? '');

if (!in_array($type, ['zeal', 'jolt'], true)) {
    api_json(['status' => 'error', 'error' => 'Type must be zeal or jolt'], 400);
}
if ($fromEmail === '' || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)
    || $toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
    api_json(['status' => 'error', 'error' => 'Both a valid from and to email are required'], 400);
}
if ($fromEmail === $toEmail) {
    api_json(['status' => 'error', 'error' => 'Cannot transfer to the same user'], 400);
}
if ($amount < 1) {
    api_json(['status' => 'error', 'error' => 'Amount must be at least 1'], 400);
}
// Typed-phrase confirmation — the operator must type the exact target email.
if ($confirm !== 'TRANSFER ' . $toEmail) {
    api_json(['status' => 'error', 'error' => 'Type exactly: TRANSFER ' . $toEmail], 400);
}

try {
    $db = DatabaseConnection::getDefaultDatabase();

    $fromUser = $db->users->findOne(['email' => $fromEmail]);
    $toUser   = $db->users->findOne(['email' => $toEmail]);
    if (!$fromUser || !$toUser) {
        api_json(['status' => 'error', 'error' => 'One or both users do not exist'], 400);
    }

    // Make sure both balance documents exist before the conditional decrement.
    foreach ([$fromEmail, $toEmail] as $em) {
        $db->user_stats->updateOne(
            ['email' => $em],
            ['$setOnInsert' => ['email' => $em, 'zeal' => 0, 'jolt' => 0]],
            ['upsert' => true]
        );
    }

    // Conditional update: never drive the donor negative even under concurrency.
    $debit = $db->user_stats->updateOne(
        ['email' => $fromEmail, $type => ['$gte' => $amount]],
        ['$inc' => [$type => -$amount]]
    );
    if ($debit->getMatchedCount() === 0) {
        $current = $db->user_stats->findOne(['email' => $fromEmail]);
        $balance = (int)($current[$type] ?? 0);
        api_json([
            'status'  => 'error',
            'error'   => 'Insufficient ' . $type . ' balance (' . $balance . ' available)',
            'balance' => $balance,
        ], 200);
        exit;
    }

    $db->user_stats->updateOne(['email' => $toEmail], ['$inc' => [$type => $amount]]);

    // Keep the denormalised copy on the user document in step.
    $db->users->updateOne(['email' => $fromEmail], ['$inc' => ["zeal_stats.$type" => -$amount]]);
    $db->users->updateOne(['email' => $toEmail],   ['$inc' => ["zeal_stats.$type" =>  $amount]]);

    $fromAfter = $db->user_stats->findOne(['email' => $fromEmail]);
    $toAfter   = $db->user_stats->findOne(['email' => $toEmail]);

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

    api_json([
        'status'  => 'success',
        'type'    => $type,
        'amount'  => $amount,
        'from'    => ['email' => $fromEmail, 'balance' => (int)($fromAfter[$type] ?? 0)],
        'to'      => ['email' => $toEmail,   'balance' => (int)($toAfter[$type] ?? 0)],
    ], 200);
} catch (Throwable $e) {
    errors_report(['context' => 'admin/transfer_entitlements', 'message' => 'transfer_entitlements: ' . $e->getMessage()]);
    api_json(['status' => 'error', 'error' => 'Transfer failed'], 400);
}
