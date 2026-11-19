<?php
/**
 * Roadmaps - Toggle Visibility (public/private)
 */
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/utils/config.php';
require_once __DIR__ . '/../../../src/lib/core/DatabaseConnection.class.php';

$user = AuthMiddleware::requireAuth();

$userId = (int)$user->getUserId();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$roadmapId = trim($input['roadmap_id'] ?? '');

if (empty($roadmapId) || !preg_match('/^[a-f0-9]{24}$/i', $roadmapId)) {
api_json(['error' => 'Invalid roadmap_id'], 400);
}

$db = DatabaseConnection::getDefaultDatabase();

$roadmap = $db->ai_roadmaps->findOne([
    '_id' => new MongoDB\BSON\ObjectId($roadmapId),
    'user_id' => $userId,
]);

if (!$roadmap) {
api_json(['error' => 'Roadmap not found or access denied'], 404);
}

$currentVisibility = $roadmap['visibility'] ?? 'private';
$newVisibility = $currentVisibility === 'public' ? 'private' : 'public';

$db->ai_roadmaps->updateOne(
    [
        '_id' => new MongoDB\BSON\ObjectId($roadmapId),
        'user_id' => $userId,
    ],
    [
        '$set' => [
            'visibility' => $newVisibility,
            'updated_at' => new MongoDB\BSON\UTCDateTime(),
        ]
    ]
);

api_json([
    'success' => true,
    'visibility' => $newVisibility,
], 200);
