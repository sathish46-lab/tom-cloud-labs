<?php
require_once "../../../load.php";
require_once "../../../lib/services/MySqlManager.php";

header('Content-Type: application/json');

if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user = Session::getUser();
$db = DatabaseConnection::getClient()->selectDatabase('tom_labs_db');

$data = json_decode(file_get_contents('php://input'), true);
$mysqlUsername = $data['mysql_username'] ?? '';
$dbNameRaw = trim($data['db_name'] ?? '');
$collation = trim($data['collation'] ?? 'utf8mb4_0900_ai_ci');

if (empty($mysqlUsername) || empty($dbNameRaw)) {
    echo json_encode(['success' => false, 'error' => 'MySQL username and database name are required.']);
    exit;
}

// Clean database name (alphanumeric and underscores only)
$dbName = preg_replace('/[^a-zA-Z0-9_]/', '', strtolower($dbNameRaw));
if ($dbName !== $dbNameRaw) {
    echo json_encode(['success' => false, 'error' => 'Database name can only contain lowercase letters, numbers, and underscores.']);
    exit;
}

// Validate collation
if (!preg_match('/^[a-zA-Z0-9_]+$/', $collation)) {
    $collation = 'utf8mb4_0900_ai_ci';
}

try {
    // 1. Verify ownership of the parent MySQL user
    $userRecord = $db->mysql_users->findOne([
        'user_id' => $user->getUserId(),
        'email' => $user->getEmail(),
        'mysql_username' => $mysqlUsername
    ]);

    if (!$userRecord) {
        throw new Exception("MySQL User not found or permission denied.");
    }

    // 2. Limit to 10 databases per MySQL user
    $dbCount = $db->mysql_databases->countDocuments([
        'mysql_user_id' => (string)$userRecord['_id'],
        'email' => $user->getEmail(),
        'user_id' => $user->getUserId()
    ]);
    if ($dbCount >= 10) {
        throw new Exception("You have reached the limit of 10 databases for this user.");
    }

    $manager = new MySqlManager();

    // 3. Create MySQL Database
    // We prefix the DB name with the username to prevent global collisions (like username_dbname)
    // Wait, the user might expect the exact DB name. Let's prepend to be safe, or just use exact.
    // If we use exact, there's a high chance of collisions globally. Let's prefix with their MySQL username.
    $finalDbName = $mysqlUsername . "_" . $dbName;

    if (!$manager->createDatabase($finalDbName, $mysqlUsername, $collation)) {
        throw new Exception("Failed to create database. The name '{$finalDbName}' might already exist.");
    }

    // 4. Save to MongoDB
    $dbRecord = [
        'user_id' => $user->getUserId(),
        'email' => $user->getEmail(),
        'mysql_user_id' => (string)$userRecord['_id'],
        'db_name' => $finalDbName,
        'collation' => $collation,
        'created_at' => new MongoDB\BSON\UTCDateTime()
    ];
    
    $db->mysql_databases->insertOne($dbRecord);

    echo json_encode([
        'success' => true,
        'message' => 'Database created successfully.',
        'data' => [
            'db_name' => $finalDbName
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
