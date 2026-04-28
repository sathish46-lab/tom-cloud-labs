<?php
require_once '../src/load.php';

if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    header("Location: /signin");
    exit;
}

Session::$pageTitle = "Dashboard";
Session::loadMaster();