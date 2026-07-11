<?php
require_once '/var/www/labs/htdocs/src/load.php';
$db = DatabaseConnection::getDefaultDatabase();
$cursor = $db->ai_chat_history->find([]);
foreach ($cursor as $doc) {
    echo "ID: " . $doc['_id'] . "\n";
    echo "Lesson ID: " . ($doc['lesson_id'] ?? 'none') . "\n";
    echo "Chapter ID: " . ($doc['chapter_id'] ?? 'none') . "\n";
    echo "Messages count: " . (isset($doc['messages']) ? count($doc['messages']) : 0) . "\n";
    if (isset($doc['messages'])) {
        foreach ($doc['messages'] as $m) {
            echo " - [" . $m['role'] . "] " . substr($m['content'], 0, 50) . "...\n";
        }
    }
    echo "-----------------\n";
}
