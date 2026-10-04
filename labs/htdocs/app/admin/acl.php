<?php
require_once __DIR__ . '/../../src/load.php';

if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    header("Location: /signin"); exit;
}

if (!AuthMiddleware::isAdmin()) {
    header("Location: /home"); exit;
}

Session::$pageTitle = "Admin / Access Control";
Session::loadMaster();
