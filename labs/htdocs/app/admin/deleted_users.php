<?php
require_once __DIR__ . '/../../src/load.php';
require_once __DIR__ . '/../../src/utils/user_delete.php';

if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    header("Location: /signin"); exit;
}

if (!AuthMiddleware::isAdmin()) {
    header("Location: /home"); exit;
}

$db = DatabaseConnection::getDefaultDatabase();

$id = (string)($_GET['id'] ?? '');
$record = null;
$records = [];

if ($id !== '') {
    if (preg_match('/^[a-f0-9]{24}$/', $id)) {
        $record = $db->deleted_users->findOne(['_id' => new MongoDB\BSON\ObjectId($id)]);
    }
    if (!$record) {
        header("Location: /admin/deleted-users"); exit;
    }
    $displayName = (string)($record['username'] ?? '') ?: (string)($record['email'] ?? '');
    Session::$pageTitle = "Admin / Deleted Users / " . $displayName;
} else {
    $records = iterator_to_array($db->deleted_users->find([], [
        'sort'       => ['deleted_at' => -1],
        'limit'      => 200,
        'projection' => [
            '_id' => 1, 'email' => 1, 'username' => 1, 'status' => 1,
            'deleted_at' => 1, 'deleted_by' => 1,
            'restored_at' => 1, 'restored_by' => 1,
            'snapshot.summary' => 1,
        ],
    ]), false);
    Session::$pageTitle = "Admin / Deleted Users";
}

Session::loadMaster();
