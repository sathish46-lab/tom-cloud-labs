<?php
/**
 * Learn AI - Content Save API
 * Saves client-side rendered HTML or updated content back to MongoDB & Redis
 */
require_once __DIR__ . '/../../load.php';

header('Content-Type: application/json');

if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$chapterId = $input['chapter_id'] ?? '';
$contentHtml = $input['content_html'] ?? '';
$contentMd = $input['content_markdown'] ?? null;

if (empty($chapterId) || empty($contentHtml)) {
    http_response_code(400);
    echo json_encode(['error' => 'chapter_id and content_html are required']);
    exit;
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

    echo json_encode(['status' => 'success']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save content: ' . $e->getMessage()]);
}
