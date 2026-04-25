<?php
require_once '../src/load.php';

if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    header("Location: /auth/signin.php");
    exit;
}

Session::$pageTitle = "Challenges";
Session::loadMaster();
