<?php
/**
 * Roadmaps - Progress Tracking API
 * Security: Auth required, user-scoped, input validated
 */
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/utils/config.php';
require_once __DIR__ . '/../../../src/lib/core/DatabaseConnection.class.php';

// ── AUTH CHECK ──
$user = AuthMiddleware::requireAuth();

$userId = (int)$user->getUserId();

// ── INPUT VALIDATION ──
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$roadmapId = trim($input['roadmap_id'] ?? '');
$topicId = trim($input['topic_id'] ?? '');
$itemId = trim($input['item_id'] ?? '');
$completed = (bool)($input['completed'] ?? false);

if (empty($roadmapId) || !preg_match('/^[a-f0-9]{24}$/i', $roadmapId)) {
api_json(['error' => 'Invalid roadmap_id'], 400);
}

if (empty($topicId) || !preg_match('/^[a-zA-Z0-9_]+$/', $topicId)) {
api_json(['error' => 'Invalid topic_id'], 400);
}

if (empty($itemId) || !preg_match('/^[a-zA-Z0-9_]+$/', $itemId)) {
api_json(['error' => 'Invalid item_id'], 400);
}

$db = DatabaseConnection::getDefaultDatabase();

// ── VERIFY OWNERSHIP ──
$roadmap = $db->ai_roadmaps->findOne([
    '_id' => new MongoDB\BSON\ObjectId($roadmapId),
    'user_id' => $userId,  // SECURITY: only owner can update progress
]);

if (!$roadmap) {
api_json(['error' => 'Roadmap not found or access denied'], 404);
}

// ── UPDATE PROGRESS ──
$roadmapObjId = new MongoDB\BSON\ObjectId($roadmapId);

if ($completed) {
    // Add item to completed list
    $db->ai_roadmap_progress->updateOne(
        [
            'user_id' => $userId,
            'roadmap_id' => $roadmapObjId,
            'topic_id' => $topicId,
        ],
        [
            '$addToSet' => ['completed_items' => $itemId],
            '$set' => ['updated_at' => new MongoDB\BSON\UTCDateTime()],
            '$setOnInsert' => ['created_at' => new MongoDB\BSON\UTCDateTime()],
        ],
        ['upsert' => true]
    );
} else {
    // Remove item from completed list
    $db->ai_roadmap_progress->updateOne(
        [
            'user_id' => $userId,
            'roadmap_id' => $roadmapObjId,
            'topic_id' => $topicId,
        ],
        [
            '$pull' => ['completed_items' => $itemId],
            '$set' => ['updated_at' => new MongoDB\BSON\UTCDateTime()],
        ]
    );
}

// ── RECALCULATE OVERALL PROGRESS ──
$allProgress = $db->ai_roadmap_progress->find([
    'user_id' => $userId,
    'roadmap_id' => $roadmapObjId,
]);

// Count total items from actual roadmap sections
$totalItems = 0;
foreach ($roadmap['sections'] ?? [] as $section) {
    foreach ($section['topics'] ?? [] as $topic) {
        $totalItems += count($topic['items'] ?? []);
    }
}

$completedItems = 0;
foreach ($allProgress as $p) {
    $completedItems += count($p['completed_items'] ?? []);
}

$progressPercent = min(100, (int)round(($completedItems / max(1, $totalItems)) * 100));

// Update roadmap progress
$db->ai_roadmaps->updateOne(
    ['_id' => $roadmapObjId, 'user_id' => $userId],
    ['$set' => [
        'checkpoints_total' => $totalItems,
        'checkpoints_completed' => $completedItems,
        'progress' => $progressPercent,
        'updated_at' => new MongoDB\BSON\UTCDateTime(),
    ]]
);

api_json([
    'status' => 'updated',
    'progress' => $progressPercent,
    'checkpoints_completed' => $completedItems,
    'checkpoints_total' => $totalItems,
], 200);
