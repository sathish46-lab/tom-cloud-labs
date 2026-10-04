<?php
require_once __DIR__ . '/../../src/load.php';

if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    header("Location: /signin"); exit;
}

if (!AuthMiddleware::isAdmin()) {
    header("Location: /home"); exit;
}

$db = DatabaseConnection::getDefaultDatabase();



// Fetch global settings
$globalSettings = $db->global_settings->findOne(['_id' => 'lab_features']) ?? [];

Session::$pageTitle = "Admin / Users";
Session::loadMaster();
