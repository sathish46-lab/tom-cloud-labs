<?php
/**
 * Instance Template Like API
 * Toggles like/unlike status for an instance template and stores
 * username + timestamp. Mirrors the Learn AI lesson_like pattern.
 */
require_once __DIR__ . '/../../../src/load.php';

header('Content-Type: application/json');

if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user = Session::getUser();
$currentUsername = $user ? $user->getUsername() : '';
$currentUserId = $user ? (int) $user->getUserId() : 0;

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$slug = trim($input['slug'] ?? $_POST['slug'] ?? '');

if (empty($slug)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid slug']);
    exit;
}

try {
    $db = DatabaseConnection::getClient()->selectDatabase('tom_labs_db');
    $instance = $db->instances->findOne(['slug' => $slug]);

    if (!$instance) {
        http_response_code(404);
        echo json_encode(['error' => 'Instance not found']);
        exit;
    }

    $instanceId = $instance['_id'];
    $likesArray = $instance['likes'] ?? [];
    if (!is_array($likesArray)) {
        $likesArray = is_object($likesArray)
            ? (method_exists($likesArray, 'getArrayCopy') ? $likesArray->getArrayCopy() : (array) $likesArray)
            : [];
    }

    $existingLike = $db->instance_likes->findOne([
        'instance_id' => $instanceId,
        'username' => $currentUsername,
    ]);

    $isCurrentlyLiked = ($existingLike || in_array($currentUsername, $likesArray));

    if ($isCurrentlyLiked) {
        $db->instance_likes->deleteOne([
            'instance_id' => $instanceId,
            'username' => $currentUsername,
        ]);
        $db->instances->updateOne(
            ['_id' => $instanceId],
            ['$pull' => ['likes' => $currentUsername]]
        );

        $likesCount = $db->instance_likes->countDocuments(['instance_id' => $instanceId]);
        $db->instances->updateOne(
            ['_id' => $instanceId],
            ['$set' => ['likes_count' => $likesCount]]
        );

        echo json_encode([
            'result' => 'success',
            'liked' => false,
            'action' => 'unliked',
            'like_count' => $likesCount,
            'message' => 'Template unliked!',
        ]);
    } else {
        $db->instance_likes->insertOne([
            'instance_id' => $instanceId,
            'username' => $currentUsername,
            'user_id' => $currentUserId,
            'liked_at' => date('c'),
        ]);
        $db->instances->updateOne(
            ['_id' => $instanceId],
            ['$addToSet' => ['likes' => $currentUsername]]
        );

        $likesCount = $db->instance_likes->countDocuments(['instance_id' => $instanceId]);
        $db->instances->updateOne(
            ['_id' => $instanceId],
            ['$set' => ['likes_count' => $likesCount]]
        );

        echo json_encode([
            'result' => 'success',
            'liked' => true,
            'action' => 'liked',
            'like_count' => $likesCount,
            'message' => 'Template liked!',
        ]);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to process like: ' . $e->getMessage()]);
}
