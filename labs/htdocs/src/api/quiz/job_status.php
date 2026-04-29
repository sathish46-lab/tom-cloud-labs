<?php
/**
 * API: Fetch Live Job Status for Quiz Generation
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../../load.php';
use TomLabs\Labs\Quiz;

$jobId = $_GET['job_id'] ?? null;

if (!$jobId) {
    echo json_encode(['error' => 'Missing job_id']);
    exit;
}

try {
    $job = Quiz::getJobStatus($jobId);
    
    if (!$job) {
        echo json_encode(['error' => 'Job not found']);
        exit;
    }

    echo json_encode([
        'available' => $job['available'] ?? false,
        'percentage' => $job['percentage'] ?? 0,
        'status_text' => $job['status_text'] ?? '',
        'generation_started' => $job['generation_started'] ?? null,
        'generation_attempt' => $job['generation_attempt'] ?? 0,
        'generation_success' => $job['generation_success'] ?? false,
        'generation_failed' => $job['generation_failed'] ?? false,
        'generation_started_at' => $job['generation_started_at'] ?? 0,
        'generation_success_at' => $job['generation_success_at'] ?? false,
        'generation_failed_at' => $job['generation_failed_at'] ?? false,
        'result_hash' => $job['result_hash'] ?? null
    ]);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
