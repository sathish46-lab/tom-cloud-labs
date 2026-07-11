<?php
require_once '/var/www/labs/htdocs/src/load.php';
$db = DatabaseConnection::getDefaultDatabase();
$doc = $db->ai_chat_history->findOne(['lesson_id' => '']);
if ($doc) {
    echo "FOUND document with empty lesson_id!\n";
    echo "Messages count: " . count($doc['messages']) . "\n";
} else {
    echo "No document with empty lesson_id.\n";
}
