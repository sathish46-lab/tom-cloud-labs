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
    (new Permission($user))->createDatabase(
        trim($data['db_name'] ?? ''),
        $data['mysql_username'] ?? '',
        collation: trim($data['collation'] ?? '')
    )->send();
} catch (\ServiceValidationException $e) {
    ServiceResponse::error($e->getCode())->send();
}
