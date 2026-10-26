<?php
/**
 * Learn AI - Lesson Generation Job Status API
 * Returns live progress and status of an AI lesson generation job.
 *
 * Pure reader: the worker (ai_worker.py process_lesson_job, generation via
 * labsctl src/learn/) writes real percentage/message/lesson_id into
 * ai_lesson_jobs — no Gemini calls or DB writes happen here.
 */
require_once __DIR__ . '/../../load.php';

header('Content-Type: application/json');

$user = AuthMiddleware::requireAuth();

$requestId = trim($_GET['request_id'] ?? $_POST['request_id'] ?? '');

if (empty($requestId)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'failed',
        'message' => 'request_id is required',
        'lesson_id' => null,
        'request_id' => null,
        'completed' => false,
        'failed' => true,
        'error_message' => 'request_id is required'
    ]);
    exit;
}

$db = DatabaseConnection::getDefaultDatabase();
$job = $db->ai_lesson_jobs->findOne(['request_id' => $requestId]);

if (!$job) {
    http_response_code(404);
    echo json_encode([
        'status' => 'failed',
        'message' => 'Job not found',
        'lesson_id' => null,
        'request_id' => $requestId,
        'completed' => false,
        'failed' => true,
        'error_message' => 'Job not found'
    ]);
    exit;
}

if (!empty($job['completed'])) {
    echo json_encode([
        'status' => 'completed',
        'message' => $job['message'] ?? 'Lesson generated successfully!',
        'lesson_id' => $job['lesson_id'] ?? null,
        'request_id' => $requestId,
        'completed' => true,
        'failed' => false,
        'error_message' => null,
        'percentage' => 100
    ]);
    exit;
}

if (!empty($job['failed']) || ($job['status'] ?? '') === 'failed') {
    $errorMessage = $job['error_message'] ?? 'Generation failed';
    echo json_encode([
        'status' => 'failed',
        'message' => $errorMessage,
        'lesson_id' => null,
        'request_id' => $requestId,
        'completed' => false,
        'failed' => true,
        'error_message' => $errorMessage,
        'percentage' => $job['percentage'] ?? 0
    ]);
    exit;
}

// Running — percentage/message written by the worker as generation progresses
echo json_encode([
    'status' => 'running',
    'message' => $job['message'] ?? 'Processing...',
    'lesson_id' => null,
    'request_id' => $requestId,
    'completed' => false,
    'failed' => false,
    'error_message' => null,
    'percentage' => $job['percentage'] ?? 15
]);
