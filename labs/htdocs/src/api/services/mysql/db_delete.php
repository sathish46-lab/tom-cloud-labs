<?php
require_once "../../../load.php";
require_once "../../../lib/services/ServiceValidator.class.php";
require_once "../../../lib/services/mysql/Permission.class.php";

use TomLabs\Services\Mysql\Permission;
use TomLabs\Services\ServiceResponse;
use TomLabs\Services\ServiceStatusCodes;
use TomLabs\Services\ServiceValidator;

header('Content-Type: application/json');

$user = AuthMiddleware::requireAuth();

try {
    $data = ServiceValidator::input();
    $dbName = ServiceValidator::requiredField($data, 'db_name', 'Database name');
    $username = trim($data['mysql_username'] ?? '');
    (new Permission($user))->removeDatabase($dbName, $username)->send();
} catch (\ServiceValidationException $e) {
    ServiceResponse::error($e->getCode())->send();
}
