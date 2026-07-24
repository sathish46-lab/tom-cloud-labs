<?php
require_once __DIR__ . '/../../../src/load.php';

header('Content-Type: application/json');

if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    echo json_encode(['status' => 'error', 'error' => 'Unauthorized']); exit;
}

$user = Session::getUser();
$hash = $_POST['hash'] ?? '';
$shareWith = trim($_POST['username'] ?? '');
$role = $_POST['role'] ?? 'viewer';
$canReshare = isset($_POST['can_reshare']) && $_POST['can_reshare'] === '1';

if (empty($hash) || empty($shareWith)) {
    echo json_encode(['status' => 'error', 'error' => 'Missing hash or username']); exit;
}

if (!in_array($role, ['viewer', 'manager', 'operator', 'owner'])) {
    echo json_encode(['status' => 'error', 'error' => 'Invalid role']); exit;
}

try {
    $instDb = DatabaseConnection::getClient()->selectDatabase('tom_labs_instances_db');
    $instance = $instDb->instances->findOne([
        'instance_hash' => $hash,
        'user_id' => $user->getUserId()
    ]);

    if (!$instance) {
        echo json_encode(['status' => 'error', 'error' => 'Instance not found or not owner']); exit;
    }

    $targetUser = $instDb->users->findOne(['username' => $shareWith]);
    if (!$targetUser) {
        $labUsers = DatabaseConnection::getClient()->selectDatabase('tom_labs_db');
        $targetUser = $labUsers->users->findOne(['username' => $shareWith]);
    }
    if (!$targetUser) {
        echo json_encode(['status' => 'error', 'error' => 'User not found']); exit;
    }

    $isOwner = ($instance['user_id'] == $user->getUserId());
    if (!$isOwner) {
        $existingShare = $instDb->instance_shares->findOne([
            'instance_hash' => $hash,
            'shared_with' => $user->getUsername()
        ]);
        $myRole = $existingShare['role'] ?? '';
        $canReshareExisting = $existingShare['can_reshare'] ?? false;

        if (!$canReshareExisting || !in_array($myRole, ['owner', 'operator'])) {
            echo json_encode(['status' => 'error', 'error' => 'Insufficient permissions']); exit;
        }

        $roleHierarchy = ['viewer' => 0, 'manager' => 1, 'operator' => 2, 'owner' => 3];
        if (($roleHierarchy[$role] ?? 0) >= ($roleHierarchy[$myRole] ?? 0)) {
            echo json_encode(['status' => 'error', 'error' => 'Cannot share at or above your role level']); exit;
        }
    }

    $instDb->instance_shares->updateOne(
        ['instance_hash' => $hash, 'shared_with' => $shareWith],
        ['$set' => [
            'instance_hash' => $hash,
            'shared_with'   => $shareWith,
            'shared_by'     => $user->getUsername(),
            'role'          => $role,
            'can_reshare'   => $canReshare,
            'created_at'    => time()
        ]],
        ['upsert' => true]
    );

    echo json_encode(['status' => 'success', 'message' => "Shared with {$shareWith} as {$role}"]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => $e->getMessage()]);
}
