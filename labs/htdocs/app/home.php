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
Session::set('seo_description', 'Access your Tom Labs dashboard to manage your Docker containers, VPS nodes, and VPN environments efficiently.');
Session::set('seo_keywords', 'Tom Labs Dashboard, Cloud Infrastructure, Docker Management');
Session::loadMaster();
