<?php
require_once "../../../load.php";
require_once "../../../lib/services/ServiceValidator.class.php";
require_once "../../../lib/services/rabbitmq/Permission.class.php";

use TomLabs\Services\Rabbitmq\Permission;
use TomLabs\Services\ServiceResponse;
use TomLabs\Services\ServiceStatusCodes;
use TomLabs\Services\ServiceValidator;

header('Content-Type: application/json');

$user = AuthMiddleware::requireAuth();

try {
    $data = ServiceValidator::input();
    (new Permission($user))->createVhost(
        $data['rabbitmq_username'] ?? '',
        trim($data['vhost_name'] ?? ''),
        trim($data['collation'] ?? '')
    )->send();
} catch (\ServiceValidationException $e) {
    ServiceResponse::error($e->getCode())->send();
}
