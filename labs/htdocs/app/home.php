<?php
// /app/home.php
require_once __DIR__ . '/../src/load.php';

if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    Session::$pageTitle = "Home";
    Session::loadMaster();
    exit;
}

// Anti-hijacking: rotate the session id on every /home hit — new id, same
// session (auth/CSRF carry over), so the user stays logged in. Must run
// before any output.
SessionAuth::rotateSessionId();

define('IS_HOME_PAGE', true);
Session::$pageTitle = "Home";
Session::set('seo_description', 'Access your Tom Labs dashboard to manage your Docker containers, VPS nodes, and VPN environments efficiently.');
Session::set('seo_keywords', 'Tom Labs Dashboard, Cloud Infrastructure, Docker Management');
Session::loadMaster();
