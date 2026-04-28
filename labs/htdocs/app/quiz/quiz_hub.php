<?php
require_once '../../src/load.php';

// Auth Protection
if (!Session::getAuthStatus()) {
    header("Location: /signin");
    exit;
}

Session::$pageTitle = "Spot Quiz";
Session::loadMaster();
