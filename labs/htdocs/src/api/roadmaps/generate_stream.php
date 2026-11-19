<?php
/**
 * Roadmaps - Generate Stream API
 * Creates a job and pushes to RabbitMQ for Python worker to stream
 */
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/utils/config.php';
require_once __DIR__ . '/../../../src/lib/core/DatabaseConnection.class.php';
require_once __DIR__ . '/../../../src/lib/core/RabbitClient.class.php';

// ── AUTH CHECK ──
$user = AuthMiddleware::requireAuth();

$userId = (int)$user->getUserId();
$username = $user->getUsername();
$email = $user->getEmail();

// ── INPUT VALIDATION ──
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$prompt = trim($input['prompt'] ?? '');
$level = trim($input['level'] ?? 'Beginner');
$visibility = trim($input['visibility'] ?? 'private');

// Validate level
$validLevels = ['Beginner', 'Intermediate', 'Advanced'];
if (!in_array($level, $validLevels)) $level = 'Beginner';

// Validate visibility
$validVisibility = ['public', 'private'];
if (!in_array($visibility, $validVisibility)) $visibility = 'private';

// Sanitize
$prompt = htmlspecialchars($prompt, ENT_QUOTES, 'UTF-8');

if (empty($prompt) || mb_strlen($prompt) < 10) {
api_json(['error' => 'Prompt must be at least 10 characters'], 400);
}

if (mb_strlen($prompt) > 2000) {
api_json(['error' => 'Prompt must be under 2000 characters'], 400);
}

$db = DatabaseConnection::getDefaultDatabase();

// ── RATE LIMIT: max 3 in-flight jobs per user ──
$inflightCount = $db->ai_roadmap_jobs->countDocuments([
    'user_id' => $userId,
    'status' => ['$in' => ['running', 'streaming']],
]);

if ($inflightCount >= 3) {
api_json(['error' => 'Too many roadmaps being generated. Please wait.'], 429);
}

// ── CREATE JOB ──
$jobId = sha1(uniqid('rm_', true) . $userId . microtime(true));

$job = [
    'request_id' => $jobId,
    'user_id' => $userId,
    'prompt' => $prompt,
    'level' => $level,
    'visibility' => $visibility,
    'status' => 'streaming',
    'percentage' => 0,
    'roadmap_id' => null,
    'slug' => null,
    'error_message' => null,
    'created_at' => new MongoDB\BSON\UTCDateTime(),
    'updated_at' => new MongoDB\BSON\UTCDateTime(),
];

$db->ai_roadmap_jobs->insertOne($job);

// ── PUSH TO RABBITMQ ──
try {
    $rabbit = new RabbitClient();
    $rabbit->sendToQueue('ai_jobs', json_encode([
        'type' => 'roadmap_generation',
        'job_id' => $jobId,
        'user_id' => $userId,
        'username' => $username,
        'email' => $email,
        'prompt' => $prompt,
        'level' => $level,
        'visibility' => $visibility,
        'session_id' => $jobId,  // reuse as session for WebSocket topic
    ]));
} catch (Exception $e) {
    // Update job as failed
    $db->ai_roadmap_jobs->updateOne(
        ['request_id' => $jobId],
        ['$set' => ['status' => 'failed', 'error_message' => 'Failed to queue job: ' . $e->getMessage()]]
    );
api_json(['error' => 'Failed to start generation. Please try again.'], 500);
}

api_json([
    'status' => 'ok',
    'job_id' => $jobId,
    'message' => 'Generation started',
], 200);
