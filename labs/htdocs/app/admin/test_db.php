<?php
require '/var/www/labs/vendor/autoload.php';
$client = new MongoDB\Client("mongodb://localhost:27017");
$db = $client->selectDatabase('labs');
$user = $db->users->findOne(['username' => 'sathish47']);
var_dump($user['ui_preferences']);
