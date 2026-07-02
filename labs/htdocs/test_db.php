<?php
require_once __DIR__ . '/src/load.php';
$db = DatabaseConnection::getDefaultDatabase();
$doc = $db->global_settings->findOne(['_id' => 'lab_feature_matrix']);
print_r($doc ? $doc->getArrayCopy() : []);
