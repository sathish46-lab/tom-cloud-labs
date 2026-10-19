<?php
require_once "../../../load.php";
require_once "../../../lib/services/ServiceValidator.class.php";
require_once "../../../lib/services/postgresql/Permission.class.php";

use TomLabs\Services\Postgresql\Permission;
use TomLabs\Services\ServiceResponse;
use TomLabs\Services\ServiceStatusCodes;
use TomLabs\Services\ServiceValidator;

header('Content-Type: application/json');

$user = AuthMiddleware::requireAuth();

try {
    $data = ServiceValidator::input();
    $username = ServiceValidator::requiredField($data, 'username', 'Username');
    (new Permission($user))->deleteUser($username)->send();
} catch (\ServiceValidationException $e) {
    ServiceResponse::error($e->getCode())->send();
}
