<?php
/**
 * Roadmaps - Like API
 * Toggles like/unlike status for a roadmap
 */
require_once __DIR__ . '/../../load.php';

$user = AuthMiddleware::requireAuth();

$currentUsername = $user ? $user->getUsername() : '';
$currentUserId = $user ? (int)$user->getUserId() : 0;

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$roadmapId = trim($input['roadmap_id'] ?? $_POST['roadmap_id'] ?? '');

if (empty($roadmapId)) {
api_json(['error' => 'Invalid roadmap_id'], 400);
}

try {
    $db = DatabaseConnection::getDefaultDatabase();
    $objectId = new MongoDB\BSON\ObjectId($roadmapId);
    $roadmap = $db->ai_roadmaps->findOne(['_id' => $objectId]);

    if (!$roadmap) {
api_json(['error' => 'Roadmap not found'], 404);
    }

    $likesArray = $roadmap['likes'] ?? [];
    if (!is_array($likesArray)) {
        if (is_object($likesArray)) {
            $likesArray = method_exists($likesArray, 'getArrayCopy') ? $likesArray->getArrayCopy() : (array)$likesArray;
        } else {
            $likesArray = [];
        }
    }

    $existingLike = $db->ai_roadmap_likes->findOne([
        'roadmap_id' => $roadmapId,
        'username' => $currentUsername
    ]);

    $isCurrentlyLiked = ($existingLike || in_array($currentUsername, $likesArray));

    if ($isCurrentlyLiked) {
        $db->ai_roadmap_likes->deleteOne([
            'roadmap_id' => $roadmapId,
            'username' => $currentUsername
        ]);
        $db->ai_roadmaps->updateOne(
            ['_id' => $objectId],
            ['$pull' => ['likes' => $currentUsername]]
        );

        $likesCount = $db->ai_roadmap_likes->countDocuments(['roadmap_id' => $roadmapId]);
        $db->ai_roadmaps->updateOne(
            ['_id' => $objectId],
            ['$set' => ['likes_count' => $likesCount]]
        );

        api_json([
            'result' => 'success', 'liked' => false, 'action' => 'unliked',
            'like_count' => $likesCount,
        ], 200);
    } else {
        $db->ai_roadmap_likes->insertOne([
            'roadmap_id' => $roadmapId, 'username' => $currentUsername,
            'user_id' => $currentUserId, 'liked_at' => date('c'),
        ]);
        $db->ai_roadmaps->updateOne(
            ['_id' => $objectId],
            ['$addToSet' => ['likes' => $currentUsername]]
        );

        $likesCount = $db->ai_roadmap_likes->countDocuments(['roadmap_id' => $roadmapId]);
        $db->ai_roadmaps->updateOne(
            ['_id' => $objectId],
            ['$set' => ['likes_count' => $likesCount]]
        );

        api_json([
            'result' => 'success', 'liked' => true, 'action' => 'liked',
            'like_count' => $likesCount,
        ], 200);
    }
} catch (Throwable $e) {
    api_json(['error' => 'Failed to process like: ' . errors_from_exception($e, 'roadmaps/roadmap_like')['error']], 500);
}
