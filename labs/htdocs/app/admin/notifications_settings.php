<?php
require_once __DIR__ . '/../../src/load.php';
require_once __DIR__ . '/../../src/lib/core/NotificationSettings.class.php';
require_once __DIR__ . '/../../src/lib/core/UserNotificationSettings.class.php';

if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    header("Location: /signin"); exit;
}

if (!AuthMiddleware::isAdmin()) {
    header("Location: /home"); exit;
}

Session::$pageTitle = "Admin / Notification Settings";
Session::loadMaster();
