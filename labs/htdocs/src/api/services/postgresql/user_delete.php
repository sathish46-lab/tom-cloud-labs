<?php
require_once "../../../load.php";
require_once "../../../lib/services/PostgreSqlManager.php";

header('Content-Type: application/json');

if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user = Session::getUser();
$db = DatabaseConnection::getClient()->selectDatabase('tom_labs_db');

$data = json_decode(file_get_contents('php://input'), true);
$mysqlUsername = $data['username'] ?? '';

if (empty($mysqlUsername)) {
    echo json_encode(['success' => false, 'error' => 'Username is required.']);
    exit;
}

try {
    // Verify ownership of the PostgreSQL user
    $userRecord = $db->postgresql_users->findOne([
        'user_id' => $user->getUserId(),
        'email' => $user->getEmail(),
        'postgresql_username' => $mysqlUsername
    ]);

    if (!$userRecord) {
        throw new Exception("PostgreSQL User not found or permission denied.");
    }

    $manager = new PostgreSqlManager();

    // 1. Find and Drop all databases owned by this user
    $databases = $db->postgresql_databases->find([
        'postgresql_user_id' => (string)$userRecord['_id'],
        'user_id' => $user->getUserId(),
        'email' => $user->getEmail()
    ]);
    foreach ($databases as $dbDoc) {
        $manager->deleteDatabase($dbDoc['db_name']);
        // Delete the MongoDB record
        $db->postgresql_databases->deleteOne(['_id' => $dbDoc['_id']]);
    }

    // 2. Delete PostgreSQL User
    if (!$manager->deleteUser($mysqlUsername)) {
        error_log("Failed to drop user {$mysqlUsername} in PostgreSQL, but continuing with MongoDB cleanup.");
    }

    // 3. Remove user from MongoDB
    $db->postgresql_users->deleteOne(['_id' => $userRecord['_id']]);

    echo json_encode([
        'success' => true,
        'message' => 'PostgreSQL user and all associated databases deleted successfully.'
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
