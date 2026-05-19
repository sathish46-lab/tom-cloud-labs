<?php
/**
 * Activity Tracker — Records user page visits with timestamps
 * POST /api/dashboard/track_activity
 * 
 * Stores each visit as a document in `user_activity` collection:
 *   { user_id, email, page, hour, timestamp, date }
 */
require_once __DIR__ . '/../../load.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user = Session::getUser();
$userId = (int)$user->getUserId();
$email = $user->getEmail();
$page = $_POST['page'] ?? '/';

$now = time();
$hour = (int)date('G', $now); // 0-23 hour format

$db = DatabaseConnection::getDefaultDatabase();

// Rate-limit: Only record once per 5 minutes per user to avoid flooding
$fiveMinutesAgo = $now - 300;
$recentHit = $db->user_activity->findOne([
    'user_id' => $userId,
    'timestamp' => ['$gte' => $fiveMinutesAgo]
], ['sort' => ['timestamp' => -1]]);

if ($recentHit) {
    echo json_encode(['status' => 'skipped', 'reason' => 'rate_limited']);
    exit;
}

$db->user_activity->insertOne([
    'user_id'   => $userId,
    'email'     => $email,
    'page'      => $page,
    'hour'      => $hour,
    'date'      => date('Y-m-d', $now),
    'timestamp' => $now,
    'created_at' => new MongoDB\BSON\UTCDateTime($now * 1000)
]);

echo json_encode(['status' => 'ok']);
