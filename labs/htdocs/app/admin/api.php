<?php
require_once __DIR__ . '/../../src/load.php';

if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    header("Location: /signin"); exit;
}

$user = Session::getUser();
if ($user->getRole() !== 'superuser') {
    header("Location: /home"); exit;
}

Session::$pageTitle = "Admin Panel - API & Features";
Session::loadMaster();
