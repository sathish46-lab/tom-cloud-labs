<?php
/**
 * API: Fetch Live Job Status for Quiz Generation
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../../load.php';
use TomLabs\Labs\Quiz;

$user = AuthMiddleware::requireAuth();
$userId = (int)$user->getUserId();

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

    // Ownership check: only the job owner can view status
    if ((int)($job['user_id'] ?? 0) !== $userId) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }

    // The worker writes the upstream provider's raw text into status_text.
    // Audit it, then hand the browser a plain-English stand-in plus a reference.
    $statusText = (string)($job['status_text'] ?? '');
    $publicError = '';
    if (!empty($job['generation_failed'])) {
        $friendly = errors_sanitize($statusText);
        $ref = errors_report([
            'context'    => 'quiz.generate',
            'message'    => $statusText !== '' ? $statusText : 'AI generation failed',
            'user_email' => (string)($user->getEmail() ?? ''),
            'extra'      => ['job_id' => (string)$jobId, 'attempt' => (int)($job['generation_attempt'] ?? 0)],
        ]);
        $publicError = errors_public($ref, $friendly);
        $statusText = $friendly !== '' ? $friendly : 'Generation failed.';
    } elseif (errors_is_technical($statusText)) {
        $statusText = errors_to_user($statusText);
    }

    echo json_encode([
        'available' => $job['available'] ?? false,
        'percentage' => $job['percentage'] ?? 0,
        'status_text' => $statusText,
        'error' => $publicError,
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
    $ref = errors_report(['context' => 'quiz.job_status', 'message' => $e->getMessage()]);
    echo json_encode(['error' => errors_public($ref)]);
}
