<?php
require_once __DIR__ . '/../src/load.php';

if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    header("Location: /auth/signin.php"); exit;
}

$user = Session::getUser();
$requestedUsername = $_GET['username'] ?? '';

// Security: Only allow users to view their own account for now
if ($requestedUsername !== $user->getUsername()) {
    header("Location: /" . $user->getUsername()); exit;
}

$db = DatabaseConnection::getDefaultDatabase();

// Fetch existing keys from MongoDB
$sshKeys = $db->ssh_keys->find(['user_id' => $user->getUserId()])->toArray();

Session::$pageTitle = "Account Settings - " . $user->getUsername();
Session::set('ssh_keys', $sshKeys);

// Use your existing Master Layout logic
Session::loadMaster();