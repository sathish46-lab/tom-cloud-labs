<?php
require_once __DIR__ . '/../src/load.php';
require_once __DIR__ . '/../src/utils/profile.php';

$isViewerLoggedIn = Session::getAuthStatus() === Constants::STATUS_LOGGEDIN;

if (!$isViewerLoggedIn) {
    // Signed-out visitors may read a profile. IS_PUBLIC_PAGE tells
    // SessionRenderer to skip the session-expired wall and lets _master.php
    // drop the sidebar/header chrome that assumes a signed-in user.
    define('IS_PUBLIC_PAGE', true);
}

$target = profile_resolve($_GET['username'] ?? '');

if (!$target && !$isViewerLoggedIn) {
    // "/account" and unknown handles mean nothing without a viewer identity.
    header('Location: /signin');
    exit;
}

$label = '';
if ($target) {
    $label = trim(($target['first_name'] ?? '') . ' ' . ($target['last_name'] ?? ''));
    if ($label === '') {
        $label = (string)($target['username'] ?? '');
    }
    // _master.php reads this when IS_PUBLIC_PAGE is set to choose the
    // wallpaper: either the owner's own background or the admin's fixed one.
    Session::set('profile_owner_email', (string)($target['email'] ?? ''));
}

Session::$pageTitle = ($label !== '' ? $label : 'Profile') . ' — Profile';
Session::loadMaster();
