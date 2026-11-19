<?php
/**
 * Learn AI Tool API - Get Lesson Outline
 * Called internally by ai_worker.py
 */
require_once __DIR__ . '/../../../load.php';

AuthMiddleware::requireInternalToken();

// Parse payload
$input = json_decode(file_get_contents('php://input'), true);
$lessonId = $input['lesson_id'] ?? null;

if (!$lessonId) {
api_json(["error" => "lesson_id is required"], 400);
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

    $chapters = $db->ai_chapters->find(
        ['lesson_id' => $lidMongo],
        ['sort' => ['order' => 1]]
    )->toArray();

    $outline = [
        'lesson_id'    => $lessonId,
        'title'        => $lesson['title'] ?? '',
        'description'  => $lesson['description'] ?? '',
        'level'        => $lesson['level'] ?? 'beginner',
        'modules'      => []
    ];

    $moduleMap = [];
    foreach ($chapters as $ch) {
        $moduleName = $ch['module_name'] ?? 'General';
        if (!isset($moduleMap[$moduleName])) {
            $moduleMap[$moduleName] = [];
        }
        $moduleMap[$moduleName][] = [
            'chapter_id'   => (string)$ch['_id'],
            'title'        => $ch['title'] ?? '',
            'has_content'  => !empty($ch['content'])
        ];
    }

    foreach ($moduleMap as $mName => $mChaps) {
        $outline['modules'][] = [
            'name'     => $mName,
            'chapters' => $mChaps
        ];
    }

    api_json($outline, 200);

} catch (Exception $e) {
    api_json(["error" => "Database error: " . errors_from_exception($e, 'learnAI/tools/outline')['error']], 500);
}
