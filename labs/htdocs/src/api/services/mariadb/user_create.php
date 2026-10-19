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
    [$username, $password] = ServiceValidator::usernamePassword(ServiceValidator::input());
    (new Permission($user))->createUser($username, $password)->send();
} catch (\ServiceValidationException $e) {
    ServiceResponse::error($e->getCode())->send();
}
