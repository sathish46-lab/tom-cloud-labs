<?php
/**
 * Home Page Labs API
 * Returns all labs for the current user with their status (for launchpad grid)
 */
require_once __DIR__ . '/../../load.php';

header('Content-Type: application/json');

if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user = Session::getUser();
$userId = (int)$user->getUserId();
$db = DatabaseConnection::getClient()->selectDatabase('tom_labs_db');

$labs = $db->machine_labs->find(
    ['user_id' => $userId],
    ['sort' => ['created_at' => -1]]
);

$list = [];
foreach ($labs as $lab) {
    $hash = $lab['instance_hash'] ?? '';
    $list[] = [
        'name'   => $lab['lab_name'] ?? ucfirst(str_replace('_', ' ', $lab['lab_type'] ?? 'Lab')),
        'type'   => $lab['lab_type'] ?? 'unknown',
        'status' => $lab['status'] ?? 'unknown',
        'hash'   => $hash,
        'icon'   => $lab['icon'] ?? null,
    ];
}

echo json_encode([
    'result' => 'success',
    'labs'   => $list,
]);
