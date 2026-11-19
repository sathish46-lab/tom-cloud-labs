<?php
/**
 * Learn AI Tool API - Check Lab Status
 * Called internally by ai_worker.py
 */
require_once __DIR__ . '/../../../load.php';

AuthMiddleware::requireInternalToken();

// Parse payload
$input = json_decode(file_get_contents('php://input'), true);
$userId = $input['user_id'] ?? null;
$lessonId = $input['lesson_id'] ?? null;

if (!$userId) {
api_json(["error" => "user_id is required"], 400);
}

try {
    $db = DatabaseConnection::getDefaultDatabase();
    
    // We get the active running lab.
    // For simplicity, we just find any running lab for this user.
    // If the orchestrator has specific mapping for lesson->lab, it can be added here.
    $inst = $db->machine_labs->findOne([
        'deploy.user_id' => (int)$userId,
        'deploy.status' => 'running'
    ]);
    $labDoc = $inst ? ($inst['deploy'] ?? []) : null;

    $labNames = [
        'essentials' => 'Essentials Lab',
        'minio' => 'MinIO S3 Storage',
        'n8n' => 'n8n Workflow Lab',
        'docker_lab' => 'Tom Docker Lab'
    ];

    if ($labDoc) {
        $labType = $labDoc['lab_type'] ?? 'unknown';
        $labName = $labNames[$labType] ?? 'Unknown Lab';
        
        $response = [
            'name' => $labName,
            'ip' => $labDoc['internal_ip'] ?? 'Unknown',
            'status' => 'running',
            'hash' => $labDoc['instance_hash'] ?? ''
        ];
    } else {
        $response = [
            'name' => 'Unknown',
            'ip' => 'Instance Down',
            'status' => 'offline',
            'hash' => ''
        ];
    }

    api_json($response, 200);

} catch (Exception $e) {
    api_json(["error" => "Database error: " . errors_from_exception($e, 'learnAI/tools/connection_info')['error']], 500);
}
