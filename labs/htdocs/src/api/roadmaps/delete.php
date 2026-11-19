<?php
/**
 * Roadmaps - Delete Roadmap
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

$result = $db->ai_roadmaps->deleteOne([
    '_id' => new MongoDB\BSON\ObjectId($roadmapId),
    'user_id' => $userId,
]);

if ($result->getDeletedCount() === 0) {
api_json(['error' => 'Roadmap not found or access denied'], 404);
}

// Also delete related progress
$db->ai_roadmap_progress->deleteMany([
    'roadmap_id' => new MongoDB\BSON\ObjectId($roadmapId),
    'user_id' => $userId,
]);

api_json(['success' => true, 'message' => 'Roadmap deleted'], 400);
