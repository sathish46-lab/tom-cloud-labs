<?php
/**
 * Learn AI - Content Fetch API
 * Returns rendered chapter HTML from Redis cache or MongoDB
 */
require_once __DIR__ . '/../../load.php';
require_once __DIR__ . '/../../lib/core/Cache.class.php';

header('Content-Type: application/json');

if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$chapterId = $_GET['chapter_id'] ?? '';
if (empty($chapterId)) {
    http_response_code(400);
    echo json_encode(['error' => 'chapter_id is required']);
    exit;
}

try {
    // 1. Try Redis direct check
    try {
        $redis = new Redis();
        if (@$redis->connect('127.0.0.1', 6379, 1)) {
            $cached = $redis->get("learn:content:{$chapterId}");
            if ($cached && trim($cached) !== '') {
                echo json_encode([
                    'status' => 'success',
                    'html' => $cached,
                    'source' => 'redis'
                ]);
                exit;
            }
        }
    } catch (Exception $e) {}

    // 2. Fallback to MongoDB
    $db = DatabaseConnection::getDefaultDatabase();
    $chapter = $db->ai_chapters->findOne(['_id' => new MongoDB\BSON\ObjectId($chapterId)]);

    if (!$chapter) {
        http_response_code(404);
        echo json_encode(['error' => 'Chapter not found']);
        exit;
    }

    $contentHtml = $chapter['content_html'] ?? null;
    $contentMd = $chapter['content'] ?? null;

    if (!empty($contentHtml) && trim($contentHtml) !== '...' && trim($contentHtml) !== '') {
        // Populate Redis cache for next time
        try {
            if (isset($redis)) {
                @$redis->setex("learn:content:{$chapterId}", 86400, $contentHtml);
            }
        } catch (Exception $e) {}

        echo json_encode([
            'status' => 'success',
            'html' => $contentHtml,
            'source' => 'db_html'
        ]);
        exit;
    }

    if (!empty($contentMd) && trim($contentMd) !== '...' && trim($contentMd) !== '') {
        echo json_encode([
            'status' => 'success',
            'markdown' => $contentMd,
            'source' => 'db_markdown'
        ]);
        exit;
    }

    echo json_encode([
        'status' => 'empty',
        'message' => 'No content generated yet'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch content: ' . $e->getMessage()]);
}
