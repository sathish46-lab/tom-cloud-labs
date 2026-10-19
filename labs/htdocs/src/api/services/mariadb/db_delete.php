<?php
require_once "../../../load.php";
require_once "../../../lib/services/ServiceValidator.class.php";
require_once "../../../lib/services/mariadb/Permission.class.php";

use TomLabs\Services\Mariadb\Permission;
use TomLabs\Services\ServiceResponse;
use TomLabs\Services\ServiceStatusCodes;
use TomLabs\Services\ServiceValidator;

header('Content-Type: application/json');

$user = AuthMiddleware::requireAuth();

try {
    $data = ServiceValidator::input();
    $dbName = ServiceValidator::requiredField($data, 'db_name', 'Database name');
    $username = trim($data['mariadb_username'] ?? '');
    (new Permission($user))->removeDatabase($dbName, $username)->send();
} catch (\ServiceValidationException $e) {
    ServiceResponse::error($e->getCode())->send();
}
