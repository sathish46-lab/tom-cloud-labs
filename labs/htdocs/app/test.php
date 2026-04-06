<?php
// CHANGE THIS LINE: Use __DIR__ to ensure the path is always correct
require_once __DIR__ . '/../src/load.php'; 

if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    header("Location: /signin"); // Use the clean URL
    exit;
}

Session::$pageTitle = "Labs"; 
Session::set('footer', false); 
Session::set('brokenPage', false);

Session::loadMaster();