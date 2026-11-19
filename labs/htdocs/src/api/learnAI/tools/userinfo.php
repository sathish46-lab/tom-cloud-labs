<?php
/**
 * Learn AI Tool API - Get Lab User Info
 * Called internally by ai_worker.py
 */
require_once __DIR__ . '/../../../load.php';

AuthMiddleware::requireInternalToken();

// Parse payload
$input = json_decode(file_get_contents('php://input'), true);
$userId = $input['user_id'] ?? null;

if (!$userId) {
api_json(["error" => "user_id is required"], 400);
}

try {
    $db = DatabaseConnection::getDefaultDatabase();
    
    // Get User Profile
    $userDoc = $db->users->findOne(['_id' => (int)$userId]);
    $username = $userDoc['username'] ?? 'unknown';
    $email = $userDoc['email'] ?? 'unknown';

    // Get Active Lab
    $activeLab = null;
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
        
        $activeLab = [
            'lab_name' => $labName,
            'ip' => $labDoc['internal_ip'] ?? 'Unknown',
            'instance_id' => $labDoc['instance_hash'] ?? ''
        ];
    } else {
        $activeLab = [
            'lab_name' => 'Unknown',
            'ip' => 'Instance Down',
            'instance_id' => ''
        ];
    }

    $response = [
        "username" => $username,
        "email" => $email,
        "lab_name" => $activeLab['lab_name'],
        "ip" => $activeLab['ip'],
        "instance_id" => $activeLab['instance_id']
    ];

    api_json($response, 200);

} catch (Exception $e) {
    api_json(["error" => "Database error: " . errors_from_exception($e, 'learnAI/tools/userinfo')['error']], 500);
}
