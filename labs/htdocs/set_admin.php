<?php
require_once __DIR__ . '/src/load.php';
$db = DatabaseConnection::getDefaultDatabase();
$result = $db->users->updateOne(['email' => 'sathishp3223@gmail.com'], ['$set' => ['role' => 'superuser']]);
echo 'Matched: ' . $result->getMatchedCount() . ', Modified: ' . $result->getModifiedCount();
