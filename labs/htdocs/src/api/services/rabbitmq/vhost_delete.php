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
    $vhostName = ServiceValidator::requiredField($data, 'vhost_name', 'Vhost name');
    $username = trim($data['rabbitmq_username'] ?? '');
    (new Permission($user))->removeVhost($vhostName, $username)->send();
} catch (\ServiceValidationException $e) {
    ServiceResponse::error($e->getCode())->send();
}
