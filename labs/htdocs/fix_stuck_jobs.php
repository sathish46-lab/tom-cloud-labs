<?php
/**
 * ============================================================================
 * TOM LABS — AI Job Recovery Script (fix_stuck_jobs.php)
 * ============================================================================
 * Purpose:
 *   Reset AI roadmap/content jobs that are stuck in "running" or "streaming"
 *   status back to "failed" so they can be retried or cleaned up.
 *
 *   This typically happens when a worker crashes mid-job, the container
 *   restarts mid-stream, or a WebSocket connection drops before the job
 *   reaches a terminal state.
 *
 * Usage:
 *   # Via CLI (inside container):
 *   php /var/www/labs/htdocs/fix_stuck_jobs.php
 *
 *   # Via HTTP (browser/curl, superuser only):
 *   https://dev.tomweb.in/fix_stuck_jobs.php
 *
 * Auth:
 *   Requires an authenticated superuser session. Returns 401 if not logged in,
 *   403 if the user is not a superuser.
 *
 * What it does:
 *   1. Updates all documents in `ai_roadmap_jobs` with status in
 *      ['running', 'streaming'] → status: 'failed',
 *         error_message: 'Reset - stale job'.
 *   2. Reports how many documents were modified.
 *   3. Counts and reports any remaining running/streaming jobs.
 *
 * @author Tom Labs
 * @version 1.0
 * ============================================================================
 */
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/utils/config.php';
require_once __DIR__ . '/src/lib/core/DatabaseConnection.class.php';
require_once __DIR__ . '/src/lib/core/Session.class.php';
require_once __DIR__ . '/src/lib/core/Constants.class.php';

// Auth check — only superusers can run this
if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}
$user = Session::getUser();
if ($user->getRole() !== 'superuser') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Forbidden: superuser only']);
    exit;
}

$db = DatabaseConnection::getDefaultDatabase();

// Reset all stuck running/streaming jobs
$result = $db->ai_roadmap_jobs->updateMany(
    ['status' => ['$in' => ['running', 'streaming']]],
    ['$set' => ['status' => 'failed', 'error_message' => 'Reset - stale job']]
);
echo "Reset " . $result->getModifiedCount() . " stuck jobs\n";

// Show remaining
$count = $db->ai_roadmap_jobs->countDocuments(['status' => ['$in' => ['running', 'streaming']]]);
echo "Remaining running/streaming: " . $count . "\n";
