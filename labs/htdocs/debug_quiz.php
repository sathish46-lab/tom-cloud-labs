<?php
require_once 'src/load.php';
$db = DatabaseConnection::getDefaultDatabase();
$quiz = $db->quizzes->findOne();
echo json_encode($quiz, JSON_PRETTY_PRINT);
