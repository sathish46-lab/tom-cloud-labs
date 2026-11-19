<?php
require_once __DIR__ . '/../../load.php';

$admin = AuthMiddleware::requireAdmin();
AuthMiddleware::requireCsrf();

$email = trim($_POST['email'] ?? '');
$state = isset($_POST['state']) && $_POST['state'] === 'true';

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    api_json(['status' => 'error', 'error' => 'Valid email required'], 400);
}

try {
    $db = DatabaseConnection::getDefaultDatabase();
    $target = $db->users->findOne(['email' => $email]);
    if (!$target) {
        api_json(['status' => 'error', 'error' => 'User not found'], 400);
    }

    $from = !empty($target['moderator']);
    if ($from === $state) {
        api_json(['status' => 'success', 'moderator' => $state, 'unchanged' => true], 200);
    }

    $db->users->updateOne(['email' => $email], ['$set' => ['moderator' => $state]]);

    AuditLog::log(
        'update',
        'user',
        (string)($target['user_id'] ?? $email),
        ['field' => 'moderator', 'from' => $from, 'to' => $state, 'target_email' => $email],
        (string)($admin->getUserId() ?? 'unknown')
    );

    api_json(['status' => 'success', 'moderator' => $state], 200);
} catch (Throwable $e) {
    errors_report(['context' => 'admin/set_moderator', 'message' => 'set_moderator: ' . $e->getMessage()]);
    api_json(['status' => 'error', 'error' => 'Failed to update moderator flag'], 400);
}
