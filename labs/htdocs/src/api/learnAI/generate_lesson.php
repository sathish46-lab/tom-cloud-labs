<?php
/**
 * Learn AI - Generate Lesson API
 * Initiates AI Lesson Generation and returns a request_id to track progress
 */
require_once __DIR__ . '/../../load.php';
require_once __DIR__ . '/../../lib/core/RabbitClient.class.php';

header('Content-Type: application/json');

$user = AuthMiddleware::requireAuth();


$userId = (int)$user->getUserId();
$db = DatabaseConnection::getDefaultDatabase();

// Support JSON POST or standard POST/GET parameters
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$topic = trim($input['topic'] ?? $input['query'] ?? $_POST['topic'] ?? $_GET['topic'] ?? '');
$level = trim($input['level'] ?? $_POST['level'] ?? $_GET['level'] ?? 'Beginner');

if (empty($topic)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'failed',
        'message' => 'Topic description is required',
        'lesson_id' => null,
        'request_id' => null,
        'completed' => false,
        'failed' => true,
        'error_message' => 'Topic description is required'
    ]);
    exit;
}

// Generate unique 40-char SHA1 request_id
$requestId = sha1(uniqid('lesson_gen_', true) . $userId . microtime(true));

$job = [
    'request_id' => $requestId,
    'user_id' => $userId,
    'username' => $user->getUsername(),
    'topic' => $topic,
    'level' => $level,
    'status' => 'running',
    'message' => 'running',
    'lesson_id' => null,
    'completed' => false,
    'failed' => false,
    'error_message' => null,
    'percentage' => 15,
    'created_at' => time()
];

try {
    $db->ai_lesson_jobs->insertOne($job);
} catch (Exception $e) {
    // If table/db insertion fails, return error
}

// Queue generation to the AI worker (labsctl src/learn/curriculum generates the curriculum)
try {
    $rabbit = new RabbitClient();
    $rabbit->sendToQueue('lesson_generation', [
        'request_id' => $requestId,
        'user_id' => $userId,
        'username' => $user->getUsername(),
        'topic' => $topic,
        'level' => $level,
    ]);
} catch (Exception $e) {
    $db->ai_lesson_jobs->updateOne(
        ['request_id' => $requestId],
        ['$set' => [
            'status' => 'failed',
            'failed' => true,
            'error_message' => 'Could not queue lesson generation'
        ]]
    );
    http_response_code(500);
    echo json_encode([
        'status' => 'failed',
        'message' => 'Could not queue lesson generation',
        'lesson_id' => null,
        'request_id' => $requestId,
        'completed' => false,
        'failed' => true,
        'error_message' => 'Could not queue lesson generation'
    ]);
    exit;
}

echo json_encode([
    'status' => 'running',
    'message' => 'running',
    'lesson_id' => null,
    'request_id' => $requestId,
    'completed' => false,
    'failed' => false,
    'error_message' => null
]);
