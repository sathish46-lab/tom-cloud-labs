<?php
require_once __DIR__ . '/../src/load.php';
require_once __DIR__ . '/../src/lib/core/SSLManager.class.php';

// Auth Protection
if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    Session::$pageTitle = "Domains";
    Session::loadMaster();
    exit;
}

$user = Session::getUser();
$db = DatabaseConnection::getClient()->selectDatabase('tom_labs_db');

$myDomains = iterator_to_array($db->domains->find(['user_id' => $user->getUserId()]));

$ssl = new SSLManager();
$certs = $ssl->getCertificates($user->getUserId());
$autoManaged = $ssl->getAutoManagedCount($certs);

Session::$pageTitle = "Domains";
Session::set('user_domains', $myDomains);
Session::set('ssl_certificates', $certs);
Session::set('ssl_auto_managed', $autoManaged);
Session::loadMaster();