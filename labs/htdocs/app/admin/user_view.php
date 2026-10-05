<?php
require_once __DIR__ . '/../../src/load.php';

if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    header("Location: /signin"); exit;
}

if (!AuthMiddleware::isAdmin()) {
    header("Location: /home"); exit;
}

$email = $_GET['email'] ?? '';
if (!$email) {
    header("Location: /admin/users"); exit;
}

$db = DatabaseConnection::getDefaultDatabase();
$userData = $db->users->findOne(['email' => $email]);

if (!$userData) {
    // Deleted accounts keep their snapshot — send the admin there instead.
    $delSnap = $db->deleted_users->findOne(['email' => $email], ['sort' => ['deleted_at' => -1]]);
    if ($delSnap) {
        header('Location: /admin/deleted-users/' . (string)$delSnap['_id']);
        exit;
    }
    header("Location: /admin/users"); exit;
}

$user = new User($email);

// Breadcrumb is derived from pageTitle split on " / " (see _master.php), so
// "Admin / Users / <name>" renders exactly Admin / Users / <name>.
$displayName = trim(($userData['first_name'] ?? '') . ' ' . ($userData['last_name'] ?? ''));
if ($displayName === '') $displayName = $userData['username'] ?? $email;

Session::$pageTitle = "Admin / Users / " . $displayName;
Session::loadMaster();
