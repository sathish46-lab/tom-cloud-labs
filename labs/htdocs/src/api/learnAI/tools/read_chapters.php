<?php
/**
 * Learn AI Tool API - Read Chapter Content
 * Called internally by ai_worker.py
 */
require_once __DIR__ . '/../../../load.php';

header('Content-Type: application/json');

// 1. Verify Internal Token
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';
$internalToken = null;

$envPath = __DIR__ . '/../../../../../../env.json';
if (file_exists($envPath)) {
    $env = json_decode(file_get_contents($envPath), true);
    $internalToken = $env['ai_internal_token'] ?? null;
}

if (!$internalToken || $authHeader !== "Bearer $internalToken") {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized access."]);
    exit;
}

// 2. Parse payload
$input = json_decode(file_get_contents('php://input'), true);
$lessonId = $input['lesson_id'] ?? null;
$chapterIds = $input['chapter_ids'] ?? [];
$currentChapterId = $input['chapter_id'] ?? null; // Fallback context

if (!$lessonId) {
    http_response_code(400);
    echo json_encode(["error" => "lesson_id is required"]);
    exit;
}

if (empty($chapterIds) && $currentChapterId) {
    $chapterIds = [$currentChapterId];
}

if (empty($chapterIds)) {
    http_response_code(400);
    echo json_encode(["error" => "No chapter IDs provided to read"]);
    exit;
}

try {
    $db = DatabaseConnection::getDefaultDatabase();
    
    $lidMongo = null;
    try { 
        $lidMongo = new \MongoDB\BSON\ObjectId($lessonId); 
    } catch (\Exception $e) { 
        http_response_code(400);
        echo json_encode(['error' => 'Invalid lesson_id format']);
        exit;
    }

    $lesson = $db->ai_lessons->findOne(['_id' => $lidMongo]);
    if (!$lesson) {
        echo json_encode(['error' => 'Lesson not found']);
        exit;
    }

    $response = [
        'lesson_id' => $lessonId,
        'lesson_title' => $lesson['title'] ?? '',
        'chapters' => []
    ];

    foreach ($chapterIds as $cId) {
        try {
            $cidMongo = new \MongoDB\BSON\ObjectId($cId);
            $chapter = $db->ai_chapters->findOne(['_id' => $cidMongo, 'lesson_id' => $lidMongo]);
            
            if ($chapter) {
                $response['chapters'][] = [
                    'chapter_id' => $cId,
                    'title' => $chapter['title'] ?? '',
                    'module_name' => $chapter['module_name'] ?? 'General',
                    'content' => $chapter['content'] ?? 'No content available for this chapter.'
                ];
            } else {
                $response['chapters'][] = [
                    'chapter_id' => $cId,
                    'error' => 'Chapter not found in this lesson'
                ];
            }
        } catch (\Exception $e) {
            $response['chapters'][] = [
                'chapter_id' => $cId,
                'error' => 'Invalid chapter ID format'
            ];
        }
    }

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}
