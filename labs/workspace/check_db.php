<?php
require '/var/www/labs/htdocs/src/lib/Autoloader.php';
$db = DatabaseConnection::getDefaultDatabase();
$cursor = $db->ai_chat_history->find([]);
foreach ($cursor as $doc) {
    echo "ID: " . $doc['_id'] . "\n";
    echo "User: " . ($doc['user_id'] ?? 'none') . "\n";
    echo "Lesson ID: " . ($doc['lesson_id'] ?? 'none') . "\n";
    echo "Chapter ID: " . ($doc['chapter_id'] ?? 'none') . "\n";
    echo "Messages count: " . (isset($doc['messages']) ? count($doc['messages']) : 0) . "\n";
    echo "-----------------\n";
}
