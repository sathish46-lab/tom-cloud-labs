<?php
require_once "../../../load.php";
require_once "../../../lib/services/MariaDbManager.php";

header('Content-Type: application/json');

if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user = Session::getUser();
$db = DatabaseConnection::getClient()->selectDatabase('tom_labs_db');

$data = json_decode(file_get_contents('php://input'), true);
$dbName = $data['db_name'] ?? '';

if (empty($dbName)) {
    echo json_encode(['success' => false, 'error' => 'Database name is required.']);
    exit;
}

try {
    // 1. Find the DB record
    $dbRecord = $db->mariadb_databases->findOne([
        'user_id' => $user->getUserId(),
        'email' => $user->getEmail(),
        'db_name' => $dbName
    ]);

    if (!$dbRecord) {
        throw new Exception("Database not found or permission denied.");
    }

    $manager = new MariaDbManager();

    // 2. Delete MariaDB Database
    if (!$manager->deleteDatabase($dbName)) {
        throw new Exception("Failed to drop database.");
    }

    // 3. Remove from MongoDB
    $db->mariadb_databases->deleteOne(['_id' => $dbRecord['_id']]);

    echo json_encode([
        'success' => true,
        'message' => 'Database deleted successfully.'
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
