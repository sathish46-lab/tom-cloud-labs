<?php
require_once __DIR__ . '/../../load.php';

$admin = AuthMiddleware::requireAdmin();
AuthMiddleware::requireCsrf();

$plans  = ['free', 'default', 'pro'];
$email  = trim($_POST['email'] ?? '');
$plan   = trim($_POST['plan'] ?? '');

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    api_json(['status' => 'error', 'error' => 'Valid email required'], 400);
}
if (!in_array($plan, $plans, true)) {
    api_json(['status' => 'error', 'error' => 'Plan must be free, default or pro'], 400);
}

try {
    $db = DatabaseConnection::getDefaultDatabase();
    $target = $db->users->findOne(['email' => $email]);
    if (!$target) {
        api_json(['status' => 'error', 'error' => 'User not found'], 400);
    }

    $from = $target['plan'] ?? 'default';
    if ($from === $plan) {
        api_json(['status' => 'success', 'plan' => $plan, 'unchanged' => true], 200);
    }

    $db->users->updateOne(['email' => $email], ['$set' => ['plan' => $plan]]);

    AuditLog::log(
        'update',
        'user',
        (string)($target['user_id'] ?? $email),
        ['field' => 'plan', 'from' => $from, 'to' => $plan, 'target_email' => $email],
        (string)($admin->getUserId() ?? 'unknown')
    );

    api_json(['status' => 'success', 'plan' => $plan], 200);
} catch (Throwable $e) {
    errors_report(['context' => 'admin/set_plan', 'message' => 'set_plan: ' . $e->getMessage()]);
    api_json(['status' => 'error', 'error' => 'Failed to update plan'], 400);
}
