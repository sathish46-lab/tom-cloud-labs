<?php
require_once "../../../load.php";

header('Content-Type: application/json');

if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user = Session::getUser();
$db = DatabaseConnection::getClient()->selectDatabase('tom_labs_db');

$username = $_GET['user'] ?? '';
if (empty($username)) {
    echo json_encode(['success' => false, 'error' => 'Username is required.']);
    exit;
}

// Ensure the user owns this postgresql_user
$mysqlUser = $db->postgresql_users->findOne([
    'user_id' => $user->getUserId(),
    'email' => $user->getEmail(),
    'postgresql_username' => $username
]);

if (!$mysqlUser) {
    echo json_encode(['success' => false, 'error' => 'PostgreSQL user not found.']);
    exit;
}

$databases = $db->postgresql_databases->find([
    'postgresql_user_id' => (string)$mysqlUser['_id'],
    'user_id' => $user->getUserId(),
    'email' => $user->getEmail()
])->toArray();

$results = [];
foreach ($databases as $dbObj) {
    $results[] = [
        'db_name' => $dbObj['db_name'],
        'character_set' => 'utf8mb4',
        'collation' => $dbObj['collation'] ?? 'UTF8',
        'size' => 0
    ];
}

echo json_encode([
    'result' => $results
]);
