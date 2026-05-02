<?php
require_once '../../src/load.php';
use TomLabs\Labs\Quiz;

// Auth Protection
if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    // Session expired UI
}

$topicId = $_GET['topic'] ?? null;
$subtopicId = $_GET['subtopic'] ?? null;

if (!$topicId || !$subtopicId) {
    header("Location: /quiz");
    exit;
}

Session::$pageTitle = "AI Quiz Generation";
Session::loadMaster();
