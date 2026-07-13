<?php
/**
 * Learn AI Tool API - Get Lesson Outline
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

if (!$lessonId) {
    http_response_code(400);
    echo json_encode(["error" => "lesson_id is required"]);
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

    echo json_encode($outline);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}
