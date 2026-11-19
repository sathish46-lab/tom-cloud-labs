<?php
/**
 * Learn AI Tool API - Read Chapter Content
 * Called internally by ai_worker.py
 */
require_once __DIR__ . '/../../../load.php';

AuthMiddleware::requireInternalToken();

// Parse payload
$input = json_decode(file_get_contents('php://input'), true);
$lessonId = $input['lesson_id'] ?? null;
$chapterIds = $input['chapter_ids'] ?? [];
$currentChapterId = $input['chapter_id'] ?? null; // Fallback context

if (!$lessonId) {
api_json(["error" => "lesson_id is required"], 400);
}

if (empty($chapterIds) && $currentChapterId) {
    $chapterIds = [$currentChapterId];
}

if (empty($chapterIds)) {
api_json(["error" => "No chapter IDs provided to read"], 400);
}

try {
    $db = DatabaseConnection::getDefaultDatabase();
    
    $lidMongo = null;
    try { 
        $lidMongo = new \MongoDB\BSON\ObjectId($lessonId); 
    } catch (\Exception $e) { 
api_json(['error' => 'Invalid lesson_id format'], 400);
    }

    $lesson = $db->ai_lessons->findOne(['_id' => $lidMongo]);
    if (!$lesson) {
api_json(['error' => 'Lesson not found'], 400);
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

    api_json($response, 200);

} catch (Exception $e) {
    api_json(["error" => "Database error: " . errors_from_exception($e, 'learnAI/tools/read_chapters')['error']], 500);
}
