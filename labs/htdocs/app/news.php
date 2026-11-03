<?php
// /app/news.php — News page route target (Cybersecurity news aggregation)
// Routes via .htaccess: /news → app/news.php
// Public read page; admin-only fetch/delete. UI lives in src/template/pages/news.php

require_once __DIR__ . '/../src/load.php';

$isViewerLoggedIn = Session::getAuthStatus() === Constants::STATUS_LOGGEDIN;
$isAdmin = AuthMiddleware::isAdmin();

// Public read — only set IS_PUBLIC_PAGE for signed-out visitors
if (!$isViewerLoggedIn) {
    define('IS_PUBLIC_PAGE', true);
}

Session::$pageTitle = 'Cybersecurity News';

// generatePageBody() (called from _master.php) includes src/template/pages/news.php
Session::loadMaster();
