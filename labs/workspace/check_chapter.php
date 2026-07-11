<?php
require_once '/var/www/labs/htdocs/src/load.php';
$db = DatabaseConnection::getDefaultDatabase();
$chapter = $db->ai_chapters->findOne(['_id' => new MongoDB\BSON\ObjectId('6a508dbf52f190aa360f9fd5')]);
echo "Title: " . $chapter['title'] . "\n";
echo "Has content_html: " . (isset($chapter['content_html']) ? 'yes' : 'no') . "\n";
echo "Content HTML snippet: \n" . substr($chapter['content_html'], 0, 100) . "...\n";
