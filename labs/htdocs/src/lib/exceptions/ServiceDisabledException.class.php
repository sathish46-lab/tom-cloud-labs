<?php

require_once __DIR__ . '/ServiceValidationException.class.php';

/**
 * Thrown when an admin has disabled a service from the admin panel.
 * Carries ServiceStatusCodes::SERVICE_DISABLED so API responses get the
 * friendly "disabled by an administrator" message.
 */
class ServiceDisabledException extends ServiceValidationException {

    protected $message = "This service has been disabled by an administrator.";
    protected $code = 1010;

    public function __construct() {
        parent::__construct($this->message, $this->code);
    }

    public function __toString() {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }

}
