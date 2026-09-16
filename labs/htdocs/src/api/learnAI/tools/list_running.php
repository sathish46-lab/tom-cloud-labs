<?php
/**
 * Learn AI Tool API - List Running Labs
 * Called internally by ai_worker.py
 */
require_once __DIR__ . '/../../../load.php';

header('Content-Type: application/json');

AuthMiddleware::requireInternalToken();

// Parse payload
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
    $runningDocs = $db->machine_labs->find([
        'deploy.user_id' => (int)$userId,
        'deploy.status' => 'running'
    ]);

    $labNames = [
        'essentials' => 'Essentials Lab',
        'minio' => 'MinIO S3 Storage',
        'n8n' => 'n8n Workflow Lab',
        'docker_lab' => 'Tom Docker Lab'
    ];

    $runningLabs = [];
    foreach ($runningDocs as $doc) {
        $deploy = $doc['deploy'] ?? [];
        $labType = $deploy['lab_type'] ?? 'unknown';
        $runningLabs[] = [
            'id' => $labType,
            'name' => $labNames[$labType] ?? 'Unknown Lab',
            'instance_id' => $deploy['instance_hash'] ?? '',
            'status' => 'running',
            'ip' => $deploy['internal_ip'] ?? 'Unknown'
        ];
    }

    echo json_encode(["running_labs" => $runningLabs]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}
