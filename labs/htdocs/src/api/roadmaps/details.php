<?php
/**
 * Roadmaps - Get Roadmap Details (for prompt display)
 */
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/utils/config.php';
require_once __DIR__ . '/../../../src/lib/core/DatabaseConnection.class.php';

$user = AuthMiddleware::requireAuth();

$userId = (int)$user->getUserId();

$roadmapId = trim($_GET['roadmap_id'] ?? '');
if (empty($roadmapId) || !preg_match('/^[a-f0-9]{24}$/i', $roadmapId)) {
api_json(['error' => 'Invalid roadmap_id'], 400);
}

$db = DatabaseConnection::getDefaultDatabase();

$roadmap = $db->ai_roadmaps->findOne([
    '_id' => new MongoDB\BSON\ObjectId($roadmapId),
    'user_id' => $userId,
]);

if (!$roadmap) {
api_json(['error' => 'Roadmap not found'], 404);
}

$tags = ($roadmap['tags'] instanceof MongoDB\Model\BSONArray) ? iterator_to_array($roadmap['tags'], false) : (array)($roadmap['tags'] ?? []);

api_json([
    'prompt' => $roadmap['prompt'] ?? '',
    'title' => $roadmap['title'] ?? '',
    'tags' => $tags,
    'level' => $roadmap['level'] ?? '',
    'model' => $roadmap['ai_model'] ?? '',
    'markdown' => $roadmap['markdown'] ?? '',
], 200);
