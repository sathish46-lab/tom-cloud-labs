<?php
/**
 * Learn AI - Content Save API
 * Saves client-side rendered HTML or updated content back to MongoDB & Redis
 */
require_once __DIR__ . '/../../load.php';

$user = AuthMiddleware::requireAuth();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$chapterId = $input['chapter_id'] ?? '';
$contentHtml = $input['content_html'] ?? '';
$contentMd = $input['content_markdown'] ?? null;

if (empty($chapterId) || empty($contentHtml)) {
api_json(['error' => 'chapter_id and content_html are required'], 400);
}

try {
    $db = DatabaseConnection::getDefaultDatabase();
    $updateFields = [
        'content_html' => $contentHtml,
        'content_updated_at' => time()
    ];
    if ($contentMd !== null) {
        $updateFields['content'] = $contentMd;
    }

    $db->ai_chapters->updateOne(
        ['_id' => new MongoDB\BSON\ObjectId($chapterId)],
        ['$set' => $updateFields]
    );

    // Update Redis Cache
    try {
        $redis = new Redis();
        if (@$redis->connect('127.0.0.1', 6379, 1)) {
            $redis->setex("learn:content:{$chapterId}", 86400, $contentHtml);
        }
    } catch (Exception $e) {}

    api_json(['status' => 'success'], 200);
} catch (Exception $e) {
    api_json(['error' => 'Failed to save content: ' . errors_from_exception($e, 'learnAI/content_save')['error']], 500);
}
