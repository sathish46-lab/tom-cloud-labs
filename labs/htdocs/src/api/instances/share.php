<?php
require_once __DIR__ . '/../../../src/load.php';

$user = AuthMiddleware::requireAuth();

$hash = $_POST['hash'] ?? '';
$shareWith = trim($_POST['username'] ?? '');
$role = $_POST['role'] ?? 'viewer';
$canReshare = isset($_POST['can_reshare']) && $_POST['can_reshare'] === '1';

if (empty($hash) || empty($shareWith)) {
    api_json(['status' => 'error', 'error' => 'Missing hash or username'], 400);
}

if (!in_array($role, ['viewer', 'manager', 'operator', 'owner'])) {
    api_json(['status' => 'error', 'error' => 'Invalid role'], 400);
}

try {
    $instDb = DatabaseConnection::getClient()->selectDatabase('tom_labs_instances_db');
    $instance = $instDb->instances->findOne([
        'instance_hash' => $hash,
        'user_id' => $user->getUserId()
    ]);

    if (!$instance) {
        api_json(['status' => 'error', 'error' => 'Instance not found or not owner'], 400);
    }

    $targetUser = $instDb->users->findOne(['username' => $shareWith]);
    if (!$targetUser) {
        $labUsers = DatabaseConnection::getClient()->selectDatabase('tom_labs_db');
        $targetUser = $labUsers->users->findOne(['username' => $shareWith]);
    }
    if (!$targetUser) {
        api_json(['status' => 'error', 'error' => 'User not found'], 400);
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
            api_json(['status' => 'error', 'error' => 'Insufficient permissions'], 400);
        }

        $roleHierarchy = ['viewer' => 0, 'manager' => 1, 'operator' => 2, 'owner' => 3];
        if (($roleHierarchy[$role] ?? 0) >= ($roleHierarchy[$myRole] ?? 0)) {
            api_json(['status' => 'error', 'error' => 'Cannot share at or above your role level'], 400);
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

    api_json(['status' => 'success', 'message' => "Shared with {$shareWith} as {$role}"], 200);
} catch (Exception $e) {
    api_json(['status' => 'error', 'error' => errors_from_exception($e, 'instances/share')['error']], 500);
}
