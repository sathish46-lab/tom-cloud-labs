<?php
/**
 * Learn AI - Content Partial API (stream resume after reload)
 *
 * Returns any in-flight generated content for a chapter so a page that was
 * reloaded mid-generation can catch up. The worker writes content_partial*
 * fields while streaming (ai_worker.py _flush_content_partial) and unsets
 * them when the final content is saved.
 *
 * Auth model mirrors content_fetch.php (requireAuth only — content visibility
 * is enforced at the lesson page level).
 */
require_once __DIR__ . '/../../load.php';

header('Content-Type: application/json');

$user = AuthMiddleware::requireAuth();

$chapterId = trim($_GET['chapter_id'] ?? '');

if (empty($chapterId)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'chapter_id is required',
        'streaming' => false,
        'session_id' => null,
        'seq' => 0,
        'partial' => '',
    ]);
    exit;
}

try {
    $oid = new MongoDB\BSON\ObjectId($chapterId);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid chapter_id',
        'streaming' => false,
        'session_id' => null,
        'seq' => 0,
        'partial' => '',
    ]);
    exit;
}

try {
    $db = DatabaseConnection::getDefaultDatabase();
    $chapter = $db->ai_chapters->findOne(['_id' => $oid]);

    if (!$chapter) {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'Chapter not found',
            'streaming' => false,
            'session_id' => null,
            'seq' => 0,
            'partial' => '',
        ]);
        exit;
    }

    $partial = (string)($chapter['content_partial'] ?? '');

    if ($partial === '') {
        echo json_encode([
            'status' => 'none',
            'streaming' => false,
            'session_id' => null,
            'seq' => (int)($chapter['content_partial_seq'] ?? 0),
            'partial' => '',
            'updated_at' => (int)($chapter['content_partial_at'] ?? 0),
        ]);
        exit;
    }

    $workerStatus = $chapter['content_partial_status'] ?? 'streaming';
    $updatedAt = (int)($chapter['content_partial_at'] ?? 0);
    $isFresh = $updatedAt > 0 && (time() - $updatedAt) <= 120;

    // 'streaming' only when the worker is actually flushing right now;
    // anything older means the job died mid-stream (interrupted).
    if ($workerStatus === 'streaming' && !$isFresh) {
        $status = 'interrupted';
    } else {
        $status = $workerStatus;
    }

    echo json_encode([
        'status' => $status,
        'streaming' => $status === 'streaming',
        'session_id' => $chapter['content_partial_session'] ?? null,
        'seq' => (int)($chapter['content_partial_seq'] ?? 0),
        'partial' => $partial,
        'updated_at' => $updatedAt,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => errors_from_exception($e, 'learnAI/content_partial')['error'],
        'streaming' => false,
        'session_id' => null,
        'seq' => 0,
        'partial' => '',
    ]);
}
