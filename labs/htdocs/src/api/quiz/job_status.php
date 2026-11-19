<?php
/**
 * API: Fetch Live Job Status for Quiz Generation
 */

require_once __DIR__ . '/../../load.php';
use TomLabs\Labs\Quiz;

$user = AuthMiddleware::requireAuth();
$userId = (int)$user->getUserId();

$jobId = $_GET['job_id'] ?? null;

if (!$jobId) {
api_json(['error' => 'Missing job_id'], 400);
}

try {
    $job = Quiz::getJobStatus($jobId);
    
    if (!$job) {
api_json(['error' => 'Job not found'], 400);
    }

    // Ownership check: only the job owner can view status
    if ((int)($job['user_id'] ?? 0) !== $userId) {
api_json(['error' => 'Forbidden'], 403);
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

    api_json([
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
    ], 200);

} catch (Exception $e) {
    $ref = errors_report(['context' => 'quiz.job_status', 'message' => $e->getMessage()]);
    api_json(['error' => errors_public($ref)], 400);
}
