<?php
require_once __DIR__ . '/../../src/load.php';

if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    header("Location: /signin"); exit;
}

$user = Session::getUser();
if ($user->getRole() !== 'superuser') {
    header("Location: /home"); exit; // Redirect non-superusers
}

$db = DatabaseConnection::getDefaultDatabase();



// Fetch global settings
$globalSettings = $db->global_settings->findOne(['_id' => 'lab_features']) ?? [];

Session::$pageTitle = "Admin Panel - Users";
Session::loadMaster();
