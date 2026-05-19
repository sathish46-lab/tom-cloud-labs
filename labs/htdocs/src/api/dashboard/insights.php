<?php
/**
 * Smart Insights API — Returns hourly activity distribution
 * GET /api/dashboard/insights
 * 
 * Aggregates user_activity collection by hour (0-23) for the last 30 days
 * and returns the peak productive window + bar chart data.
 */
require_once __DIR__ . '/../../load.php';

header('Content-Type: application/json');

if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user = Session::getUser();
$userId = (int)$user->getUserId();
$db = DatabaseConnection::getDefaultDatabase();

// Get activity from the last 30 days
$thirtyDaysAgo = time() - (30 * 24 * 60 * 60);

// Aggregate by hour
$pipeline = [
    ['$match' => [
        'user_id'   => $userId,
        'timestamp' => ['$gte' => $thirtyDaysAgo]
    ]],
    ['$group' => [
        '_id'   => '$hour',
        'count' => ['$sum' => 1]
    ]],
    ['$sort' => ['_id' => 1]]
];

$results = $db->user_activity->aggregate($pipeline);

// Build a 24-hour array (0-23)
$hourlyData = array_fill(0, 24, 0);
$totalHits = 0;

foreach ($results as $row) {
    $h = (int)$row['_id'];
    $hourlyData[$h] = (int)$row['count'];
    $totalHits += (int)$row['count'];
}

// Find peak window (2-hour sliding window)
$peakStart = 0;
$peakSum = 0;
for ($i = 0; $i < 24; $i++) {
    $windowSum = $hourlyData[$i] + $hourlyData[($i + 1) % 24];
    if ($windowSum > $peakSum) {
        $peakSum = $windowSum;
        $peakStart = $i;
    }
}
$peakEnd = ($peakStart + 2) % 24;

// Format hours for display (12-hour format)
function formatHour($h) {
    if ($h == 0) return '12 AM';
    if ($h == 12) return '12 PM';
    if ($h < 12) return $h . ' AM';
    return ($h - 12) . ' PM';
}

// Normalize bar values to 0-100 scale for the chart
$maxVal = max($hourlyData) ?: 1;
$bars = [];
for ($i = 0; $i < 24; $i++) {
    $bars[] = round(($hourlyData[$i] / $maxVal) * 100);
}

// Get total unique active days
$activeDays = $db->user_activity->distinct('date', [
    'user_id'   => $userId,
    'timestamp' => ['$gte' => $thirtyDaysAgo]
]);

// Get last login time
$lastActivity = $db->user_activity->findOne(
    ['user_id' => $userId],
    ['sort' => ['timestamp' => -1]]
);
$lastSeen = $lastActivity ? date('M j, g:i A', $lastActivity['timestamp']) : null;

echo json_encode([
    'peak_start'   => formatHour($peakStart),
    'peak_end'     => formatHour($peakEnd),
    'peak_label'   => formatHour($peakStart) . ' - ' . formatHour($peakEnd),
    'bars'         => $bars,
    'hourly_raw'   => $hourlyData,
    'total_visits' => $totalHits,
    'active_days'  => count($activeDays),
    'last_seen'    => $lastSeen,
    'has_data'     => $totalHits > 0
]);
