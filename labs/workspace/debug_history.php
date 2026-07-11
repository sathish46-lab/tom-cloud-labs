<?php
require_once '/var/www/labs/htdocs/src/load.php';
$db = DatabaseConnection::getDefaultDatabase();
$doc = $db->ai_chat_history->findOne(['user_id' => 1001]);
echo "isset: " . (isset($doc['messages']) ? 'yes' : 'no') . "\n";
echo "is_array: " . (is_array($doc['messages']) ? 'yes' : 'no') . "\n";
echo "is_object: " . (is_object($doc['messages']) ? 'yes' : 'no') . "\n";
echo "class: " . (is_object($doc['messages']) ? get_class($doc['messages']) : 'N/A') . "\n";
