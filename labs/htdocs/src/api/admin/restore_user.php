<?php
/**
 * POST /api/admin/restore_user
 *
 * "Return Back": rebuilds a deleted account from its snapshot — documents,
 * global-array references and the home folder — and marks the record restored.
 */
require_once __DIR__ . '/../../load.php';
require_once __DIR__ . '/../../utils/user_delete.php';

$admin = AuthMiddleware::requireAdmin();
AuthMiddleware::requireCsrf();

$snapshotId = trim($_POST['id'] ?? '');
$confirm    = trim($_POST['confirm'] ?? '');

if (!preg_match('/^[a-f0-9]{24}$/', $snapshotId)) {
api_json(['status' => 'error', 'error' => 'Invalid snapshot id'], 400);
}

$db = DatabaseConnection::getDefaultDatabase();
$snap = $db->deleted_users->findOne(['_id' => new MongoDB\BSON\ObjectId($snapshotId)]);
if (!$snap) {
api_json(['status' => 'error', 'error' => 'Snapshot not found'], 400);
}

if (!hash_equals('RESTORE ' . (string)$snap['email'], $confirm)) {
api_json(['status' => 'error', 'error' => 'Type exactly: RESTORE ' . $snap['email']], 400);
}

$result = user_delete_restore(
    $snapshotId,
    (string)($admin->getEmail() ?? 'unknown'),
    $admin->getUserId()
);
api_json($result, 200);
