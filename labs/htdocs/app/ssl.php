<?php
require_once __DIR__ . '/../src/load.php';
require_once __DIR__ . '/../src/lib/core/SSLManager.class.php';

// Auth Protection
if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    Session::$pageTitle = "SSL Manager";
    Session::loadMaster();
    exit;
}

$user = Session::getUser();
$ssl = new SSLManager();

// Get certificates for this user
$certs = $ssl->getCertificates($user->getUserId());
$autoManaged = $ssl->getAutoManagedCount($certs);

Session::$pageTitle = "Home / SSL Manager";
Session::set('ssl_certificates', $certs);
Session::set('ssl_auto_managed', $autoManaged);
Session::loadMaster();
