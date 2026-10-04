<?php
require_once __DIR__ . '/../../load.php';

header('Content-Type: application/json');

$admin = AuthMiddleware::requireAdmin();
AuthMiddleware::requireCsrf();

$email = trim($_POST['email'] ?? '');
$state = isset($_POST['state']) && $_POST['state'] === 'true';

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'error' => 'Valid email required']); exit;
}

try {
    $db = DatabaseConnection::getDefaultDatabase();
    $target = $db->users->findOne(['email' => $email]);
    if (!$target) {
        echo json_encode(['status' => 'error', 'error' => 'User not found']); exit;
    }

    $from = !empty($target['moderator']);
    if ($from === $state) {
        echo json_encode(['status' => 'success', 'moderator' => $state, 'unchanged' => true]); exit;
    }

    $db->users->updateOne(['email' => $email], ['$set' => ['moderator' => $state]]);

    AuditLog::log(
        'update',
        'user',
        (string)($target['user_id'] ?? $email),
        ['field' => 'moderator', 'from' => $from, 'to' => $state, 'target_email' => $email],
        (string)($admin->getUserId() ?? 'unknown')
    );

    echo json_encode(['status' => 'success', 'moderator' => $state]);
} catch (Throwable $e) {
    error_log('set_moderator: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'error' => 'Failed to update moderator flag']);
}
