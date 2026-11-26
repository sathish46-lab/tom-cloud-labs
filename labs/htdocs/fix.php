<?php
/**
 * ============================================================================
 * TOM LABS — Lab Cleanup Script (fix.php)
 * ============================================================================
 * Purpose:
 *   Delete orphaned machine_labs records that are missing the
 *   `deploy.lab_type` field. These are typically leftover documents from
 *   interrupted deployments, partial imports, or legacy data that never
 *   completed the lab provisioning flow.
 *
 * Usage:
 *   # Via CLI (inside container):
 *   php /var/www/labs/htdocs/fix.php
 *
 *   # Via HTTP (browser/curl, superuser only):
 *   https://dev.tomweb.in/fix.php
 *
 * Auth:
 *   Requires an authenticated superuser session. Returns 401 if not logged in,
 *   403 if the user is not a superuser.
 *
 * What it does:
 *   1. Connects to the `tom_labs_db` database.
 *   2. Deletes all documents in `machine_labs` where `deploy.lab_type` does
 *      not exist (`deploy.lab_type: { $exists: false }`).
 *   3. Reports how many documents were deleted.
 *   4. Logs the admin action to the server error log.
 *
 * @author Tom Labs
 * @version 1.0
 * ============================================================================
 */
require_once __DIR__ . "/src/load.php";

// S4: Require superuser authentication
if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}
$user = Session::getUser();
if (!$user || $user->getRole() !== 'superuser') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden: superuser required']);
    exit;
}

// Log the action
error_log("ADMIN ACTION: fix.php called by {$user->getEmail()} ({$user->getUserId()})");

$db = DatabaseConnection::getClient()->selectDatabase('tom_labs_db');
$result = $db->machine_labs->deleteMany(["deploy.lab_type" => ['$exists' => false]]);
echo "Deleted: " . $result->getDeletedCount();
