<?php
require_once "../../../load.php";
require_once "../../../lib/services/RedisManager.php";

header('Content-Type: application/json');

if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user = Session::getUser();
$db = DatabaseConnection::getClient()->selectDatabase('tom_labs_db');

$data = json_decode(file_get_contents('php://input'), true);
$rawUsername = trim($data['username'] ?? '');
$password = $data['password'] ?? '';

if (empty($rawUsername) || empty($password)) {
    echo json_encode(['success' => false, 'error' => 'Username and password are required.']);
    exit;
}

// Clean username (alphanumeric and underscores only)
$dbUser = preg_replace('/[^a-zA-Z0-9_]/', '', strtolower($rawUsername));
if ($dbUser !== $rawUsername) {
    echo json_encode(['success' => false, 'error' => 'Username can only contain lowercase letters, numbers, and underscores.']);
    exit;
}

try {
    // Limit to 5 Redis users per account
    $userCount = $db->redis_users->countDocuments([
        'user_id' => $user->getUserId(),
        'email' => $user->getEmail()
    ]);
    if ($userCount >= 5) {
        echo json_encode(['success' => false, 'error' => 'You have reached the maximum limit of 5 Redis users.']);
        exit;
    }

    $manager = new RedisManager();

    // 1. Create Redis User
    if (!$manager->createUser($dbUser, $password)) {
        throw new Exception("Failed to create Redis user. The username '{$dbUser}' might already be taken globally.");
    }

    // 2. Save to MongoDB
    $dbRecord = [
        'user_id' => $user->getUserId(),
        'email' => $user->getEmail(),
        'redis_username' => $dbUser,
        'redis_password' => base64_encode($password),
        'created_at' => new MongoDB\BSON\UTCDateTime()
    ];
    
    $db->redis_users->insertOne($dbRecord);

    echo json_encode([
        'success' => true,
        'message' => 'Redis user created successfully.'
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
