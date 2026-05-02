<?php
require_once '../../src/load.php';

// Auth Protection
if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    // Show professional session expired UI
}

Session::$pageTitle = "Quiz";
Session::loadMaster();
