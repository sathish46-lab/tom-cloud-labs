<?php
require_once __DIR__ . '/../../src/load.php';

if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    header("Location: /signin");
    exit;
}

$lesson_id = $_GET['id'] ?? null;
if (!$lesson_id) {
    header("Location: /learn");
    exit;
}

Session::$pageTitle = "Lesson Details | Learn AI";
Session::set('is_learn_ai', true);
Session::set('footer', true);
Session::addCustomJs('/js/learnAI/layout.js');
Session::loadMaster();
