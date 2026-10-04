<?php
require_once __DIR__ . '/../../src/load.php';
require_once __DIR__ . '/../../src/lib/core/Appearance.class.php';

if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    header("Location: /signin"); exit;
}

if (!AuthMiddleware::isAdmin()) {
    header("Location: /home"); exit;
}

Session::$pageTitle = "Admin / Appearance";
Session::loadMaster();
