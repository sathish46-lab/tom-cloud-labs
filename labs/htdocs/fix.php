<?php
require_once __DIR__ . "/src/load.php";
$db = DatabaseConnection::getClient()->selectDatabase('tom_labs_db');
$result = $db->deployed_labs->deleteMany(["lab_type" => ['$exists' => false]]);
echo "Deleted: " . $result->getDeletedCount();
