<?php
require_once 'htdocs/src/lib/load.php';

$db = DatabaseConnection::getDefaultDatabase();
$attempts = $db->quiz_attempts->find()->toArray();

echo "Total Attempts: " . count($attempts) . "\n";
foreach ($attempts as $a) {
    echo "User: " . ($a['user_email'] ?? $a['user_id'] ?? 'unknown') . " | Quiz: " . $a['quiz_hash'] . " | Score: " . $a['score'] . "\n";
}
