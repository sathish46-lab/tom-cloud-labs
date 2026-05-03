<?php
require_once 'src/load.php';
$db = DatabaseConnection::getDefaultDatabase();
$quizzes = $db->quizzes->find([], ['limit' => 5])->toArray();
foreach ($quizzes as $q) {
    echo "Quiz: " . $q['title'] . "\n";
    echo "Keys: " . implode(", ", array_keys((array)$q)) . "\n";
    if (isset($q['tags'])) {
        echo "Tags Type: " . gettype($q['tags']) . (is_object($q['tags']) ? " (" . get_class($q['tags']) . ")" : "") . "\n";
        echo "Tags: " . json_encode($q['tags']) . "\n";
    } else {
        echo "Tags: NOT FOUND\n";
    }
    echo "-------------------\n";
}
