<?php
/**
 * Learn AI - Content Fetch API
 * Returns rendered chapter HTML from Redis cache or MongoDB
 */
require_once __DIR__ . '/../../load.php';
require_once __DIR__ . '/../../lib/core/Cache.class.php';

header('Content-Type: text/html');

if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    http_response_code(401);
    echo '<div class="text-center p-4 text-danger small">Unauthorized</div>';
    exit;
}

$chapterId = $_GET['chapter_id'] ?? '';
if (empty($chapterId)) {
    http_response_code(400);
    echo '<div class="text-center p-4 text-danger small">chapter_id is required</div>';
    exit;
}

try {
    $db = DatabaseConnection::getDefaultDatabase();
    $chapter = $db->ai_chapters->findOne(['_id' => new MongoDB\BSON\ObjectId($chapterId)]);

    if (!$chapter) {
        http_response_code(404);
        echo '<div class="text-center p-4 text-danger small">Chapter not found</div>';
        exit;
    }

    $contentHtml = $chapter['content_html'] ?? null;
    $contentMd = $chapter['content'] ?? null;

    $isDummyFallback = (!empty($contentMd) && stripos($contentMd, 'Welcome to this chapter on') !== false) ||
                       (!empty($contentHtml) && stripos($contentHtml, 'Welcome to this chapter on') !== false);

    $hasRealHtml = !empty($contentHtml) && trim($contentHtml) !== '...' && trim($contentHtml) !== '' && stripos($contentHtml, 'Welcome to this chapter on') === false;
    $hasRealMd = !empty($contentMd) && trim($contentMd) !== '...' && trim($contentMd) !== '' && !$isDummyFallback;

    // 1. Try Redis direct check
    try {
        $redis = new Redis();
        if (@$redis->connect('127.0.0.1', 6379, 1)) {
            $cached = $redis->get("learn:content:{$chapterId}");
            if ($cached && trim($cached) !== '' && trim($cached) !== '...' && stripos($cached, 'Welcome to this chapter on') === false) {
                echo $cached;
                exit;
            } else if ($cached && (stripos($cached, 'Welcome to this chapter on') !== false || trim($cached) === '...')) {
                @$redis->del("learn:content:{$chapterId}");
            }
        }
    } catch (Exception $e) {}

    if ($hasRealHtml) {
        // Populate Redis cache for next time
        try {
            if (isset($redis)) {
                @$redis->setex("learn:content:{$chapterId}", 86400, $contentHtml);
            }
        } catch (Exception $e) {}

        echo $contentHtml;
        exit;
    }

    if ($hasRealMd) {
        echo '<div class="raw-markdown-fallback">' . htmlspecialchars($contentMd) . '</div>';
        exit;
    }

    ?>
    <div id="emptyContentPrompt" class="text-center py-5 my-4">
        <div class="mb-3">
            <i class="bx bx-book-open text-secondary opacity-50" style="font-size: 3.5rem;"></i>
        </div>
        <h5 class="fw-bold text-white mb-2">Ready to Learn?</h5>
        <p class="text-secondary small mb-4">Click below to generate practical, human-like tutorial content with live code blocks.</p>
        <button class="btn btn-primary rounded-pill px-4 btn-trigger-generate">
            <i class="bx bx-magic-wand me-1"></i> Generate Chapter Material
        </button>
    </div>
    <?php
} catch (Exception $e) {
    http_response_code(500);
    echo '<div class="text-center p-4 text-danger small">Failed to fetch content: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
