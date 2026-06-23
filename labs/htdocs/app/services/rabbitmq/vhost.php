<?php
require_once __DIR__ . '/../../../src/load.php';

if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    Session::$pageTitle = "Services / RabbitMQ Server";
    Session::loadMaster();
    exit;
}

Session::$pageTitle = "Services / RabbitMQ Server";
Session::loadMaster();
