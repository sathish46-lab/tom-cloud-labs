<?php
require_once '../src/load.php';

if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    header("Location: /signin");
    exit;
}

// 1. AJAX Request: Save filters to session
if (isset($_GET['ajax'])) {
    $filtersToSave = $_GET;
    unset($filtersToSave['ajax']); // Don't save the ajax flag
    $_SESSION['challenge_filters'] = $filtersToSave;
    
    Session::generatePageBody();
    exit;
}



Session::$pageTitle = "Challenges";
Session::loadMaster();

