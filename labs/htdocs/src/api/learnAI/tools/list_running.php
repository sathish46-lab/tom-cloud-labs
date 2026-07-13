<?php
/**
 * Learn AI Tool API - List Running Labs
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
$userId = $input['user_id'] ?? null;

if (!$userId) {
    http_response_code(400);
    echo json_encode(["error" => "user_id is required"]);
    exit;
}

try {
    $db = DatabaseConnection::getDefaultDatabase();
    
    // Find all running labs for this user
    $runningDocs = $db->deployed_labs->find([
        'user_id' => (int)$userId,
        'status' => 'running'
    ]);

    $labNames = [
        'essentials' => 'Essentials Lab',
        'minio' => 'MinIO S3 Storage',
        'n8n' => 'n8n Workflow Lab',
        'docker_lab' => 'Tom Docker Lab'
    ];

    $runningLabs = [];
    foreach ($runningDocs as $doc) {
        $labType = $doc['lab_type'] ?? 'unknown';
        $runningLabs[] = [
            'id' => $labType,
            'name' => $labNames[$labType] ?? 'Unknown Lab',
            'instance_id' => $doc['instance_hash'] ?? '',
            'status' => 'running',
            'ip' => $doc['internal_ip'] ?? 'Unknown'
        ];
    }

    echo json_encode(["running_labs" => $runningLabs]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}
