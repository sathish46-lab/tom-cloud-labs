<?php
require_once "../../../load.php";
require_once "../../../lib/services/RabbitMqManager.php";

header('Content-Type: application/json');

if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user = Session::getUser();
$db = VhostConnection::getClient()->selectVhost('tom_labs_db');

$data = json_decode(file_get_contents('php://input'), true);
$mysqlUsername = $data['rabbitmq_username'] ?? '';
$dbNameRaw = trim($data['vhost_name'] ?? '');
$collation = trim($data['collation'] ?? 'utf8mb4_0900_ai_ci');

if (empty($mysqlUsername) || empty($dbNameRaw)) {
    echo json_encode(['success' => false, 'error' => 'RabbitMQ username and vhost name are required.']);
    exit;
}

// Clean vhost name (alphanumeric and underscores only)
$dbName = preg_replace('/[^a-zA-Z0-9_]/', '', strtolower($dbNameRaw));
if ($dbName !== $dbNameRaw) {
    echo json_encode(['success' => false, 'error' => 'Vhost name can only contain lowercase letters, numbers, and underscores.']);
    exit;
}

// Validate collation
if (!preg_match('/^[a-zA-Z0-9_]+$/', $collation)) {
    $collation = 'utf8mb4_0900_ai_ci';
}

try {
    // 1. Verify ownership of the parent RabbitMQ user
    $userRecord = $db->rabbitmq_users->findOne([
        'user_id' => $user->getUserId(),
        'email' => $user->getEmail(),
        'rabbitmq_username' => $mysqlUsername
    ]);

    if (!$userRecord) {
        throw new Exception("RabbitMQ User not found or permission denied.");
    }

    // 2. Limit to 10 vhosts per RabbitMQ user
    $dbCount = $db->rabbitmq_vhosts->countDocuments([
        'rabbitmq_user_id' => (string)$userRecord['_id'],
        'email' => $user->getEmail(),
        'user_id' => $user->getUserId()
    ]);
    if ($dbCount >= 10) {
        throw new Exception("You have reached the limit of 10 vhosts for this user.");
    }

    $manager = new RabbitMqManager();

    // 3. Create RabbitMQ Vhost
    // We prefix the DB name with the username to prevent global collisions (like username_dbname)
    // Wait, the user might expect the exact DB name. Let's prepend to be safe, or just use exact.
    // If we use exact, there's a high chance of collisions globally. Let's prefix with their RabbitMQ username.
    $finalDbName = $mysqlUsername . "_" . $dbName;

    if (!$manager->createVhost($finalDbName, $mysqlUsername, $collation)) {
        throw new Exception("Failed to create vhost. The name '{$finalDbName}' might already exist.");
    }

    // 4. Save to MongoDB
    $dbRecord = [
        'user_id' => $user->getUserId(),
        'email' => $user->getEmail(),
        'rabbitmq_user_id' => (string)$userRecord['_id'],
        'vhost_name' => $finalDbName,
        'collation' => $collation,
        'created_at' => new MongoDB\BSON\UTCDateTime()
    ];
    
    $db->rabbitmq_vhosts->insertOne($dbRecord);

    echo json_encode([
        'success' => true,
        'message' => 'Vhost created successfully.',
        'data' => [
            'vhost_name' => $finalDbName
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
