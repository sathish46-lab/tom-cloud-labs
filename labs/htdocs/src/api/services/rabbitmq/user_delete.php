<?php
require_once "../../../load.php";
require_once "../../../lib/services/RabbitMqManager.php";

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
    // Verify ownership of the RabbitMQ user
    $userRecord = $db->rabbitmq_users->findOne([
        'user_id' => $user->getUserId(),
        'email' => $user->getEmail(),
        'rabbitmq_username' => $mysqlUsername
    ]);

    if (!$userRecord) {
        throw new Exception("RabbitMQ User not found or permission denied.");
    }

    $manager = new RabbitMqManager();

    // 1. Find and Drop all vhosts owned by this user
    $vhosts = $db->rabbitmq_vhosts->find([
        'rabbitmq_user_id' => (string)$userRecord['_id'],
        'user_id' => $user->getUserId(),
        'email' => $user->getEmail()
    ]);
    foreach ($vhosts as $dbDoc) {
        $manager->deleteDatabase($dbDoc['vhost_name']);
        // Delete the MongoDB record
        $db->rabbitmq_vhosts->deleteOne(['_id' => $dbDoc['_id']]);
    }

    // 2. Delete RabbitMQ User
    if (!$manager->deleteUser($mysqlUsername)) {
        error_log("Failed to drop user {$mysqlUsername} in RabbitMQ, but continuing with MongoDB cleanup.");
    }

    // 3. Remove user from MongoDB
    $db->rabbitmq_users->deleteOne(['_id' => $userRecord['_id']]);

    echo json_encode([
        'success' => true,
        'message' => 'RabbitMQ user and all associated vhosts deleted successfully.'
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
