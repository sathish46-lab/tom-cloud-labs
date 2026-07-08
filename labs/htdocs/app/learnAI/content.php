<?php
require_once __DIR__ . '/../../src/load.php';

if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    header("Location: /signin");
    exit;
}

$chapter_id = $_GET['id'] ?? null;
if (!$chapter_id) {
    header("Location: /learn");
    exit;
}

Session::$pageTitle = "Chapter Content | Learn AI";
Session::set('is_learn_ai', true);
Session::set('footer', true);
Session::addCustomJs('/js/learnAI/layout.js');
Session::loadMaster();
