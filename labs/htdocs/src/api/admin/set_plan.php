<?php
require_once __DIR__ . '/../../load.php';

header('Content-Type: application/json');

$admin = AuthMiddleware::requireAdmin();
AuthMiddleware::requireCsrf();

$plans  = ['free', 'default', 'pro'];
$email  = trim($_POST['email'] ?? '');
$plan   = trim($_POST['plan'] ?? '');

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'error' => 'Valid email required']); exit;
}
if (!in_array($plan, $plans, true)) {
    echo json_encode(['status' => 'error', 'error' => 'Plan must be free, default or pro']); exit;
}

try {
    $db = DatabaseConnection::getDefaultDatabase();
    $target = $db->users->findOne(['email' => $email]);
    if (!$target) {
        echo json_encode(['status' => 'error', 'error' => 'User not found']); exit;
    }

    $from = $target['plan'] ?? 'default';
    if ($from === $plan) {
        echo json_encode(['status' => 'success', 'plan' => $plan, 'unchanged' => true]); exit;
    }

    $db->users->updateOne(['email' => $email], ['$set' => ['plan' => $plan]]);

    AuditLog::log(
        'update',
        'user',
        (string)($target['user_id'] ?? $email),
        ['field' => 'plan', 'from' => $from, 'to' => $plan, 'target_email' => $email],
        (string)($admin->getUserId() ?? 'unknown')
    );

    echo json_encode(['status' => 'success', 'plan' => $plan]);
} catch (Throwable $e) {
    errors_report(['context' => 'admin/set_plan', 'message' => 'set_plan: ' . $e->getMessage()]);
    echo json_encode(['status' => 'error', 'error' => 'Failed to update plan']);
}
