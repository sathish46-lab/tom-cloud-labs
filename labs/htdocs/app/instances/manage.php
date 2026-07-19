<?php
// /app/instances/manage.php
require_once __DIR__ . '/../../src/load.php';

$isAjax = (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    if (!empty($_GET['tab']) && $isAjax) {
        echo json_encode(['status' => 'error', 'error' => 'Unauthorized']);
        exit;
    }
    Session::$pageTitle = "Instances / Manage";
    Session::loadMaster();
    exit;
}

$user = Session::getUser();
$slug = $_GET['slug'] ?? '';

if (empty($slug)) {
    if (!empty($_GET['tab']) && $isAjax) {
        echo json_encode(['status' => 'error', 'error' => 'Missing slug']);
        exit;
    }
    header("Location: /instances");
    exit;
}

// Fetch instance data from the new 'instances' collection
$db = DatabaseConnection::getClient()->selectDatabase('tom_labs_db');
$instance = $db->instances->findOne(['slug' => $slug]);

if (!$instance) {
    // If not found in 'instances', optionally fallback or just leave it null
    // (For testing purposes, we'll proceed even if null so the UI can render)
}

// Handle AJAX tab request
if (!empty($_GET['tab']) && $isAjax) {
    $tab = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['tab']); // sanitize
    $partialPath = __DIR__ . "/../../src/template/pages/instances/manage/{$tab}.php";
    
    if (file_exists($partialPath)) {
        ob_start();
        include $partialPath;
        $html = ob_get_clean();
        
        echo json_encode(['status' => 'success', 'html' => $html]);
    } else {
        echo json_encode(['status' => 'error', 'error' => 'Tab not found']);
    }
    exit;
}

Session::$pageTitle = "Instances - " . htmlspecialchars($slug) . " | Tom Labs";
Session::loadMaster('instances/manage.php', ['slug' => $slug]);

