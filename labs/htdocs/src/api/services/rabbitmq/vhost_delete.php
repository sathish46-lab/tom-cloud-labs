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
$dbName = $data['vhost_name'] ?? '';

if (empty($dbName)) {
    echo json_encode(['success' => false, 'error' => 'Vhost name is required.']);
    exit;
}

try {
    // 1. Find the DB record
    $dbRecord = $db->rabbitmq_vhosts->findOne([
        'user_id' => $user->getUserId(),
        'email' => $user->getEmail(),
        'vhost_name' => $dbName
    ]);

    if (!$dbRecord) {
        throw new Exception("Vhost not found or permission denied.");
    }

    $manager = new RabbitMqManager();

    // 2. Delete RabbitMQ Vhost
    if (!$manager->deleteVhost($dbName)) {
        throw new Exception("Failed to drop vhost.");
    }

    // 3. Remove from MongoDB
    $db->rabbitmq_vhosts->deleteOne(['_id' => $dbRecord['_id']]);

    echo json_encode([
        'success' => true,
        'message' => 'Vhost deleted successfully.'
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
