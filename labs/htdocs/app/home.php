<?php
// /app/home.php
require_once __DIR__ . '/../src/load.php';

if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    Session::$pageTitle = "Home";
    Session::loadMaster();
    exit;
}

define('IS_HOME_PAGE', true);
Session::$pageTitle = "Home";
Session::loadMaster();
