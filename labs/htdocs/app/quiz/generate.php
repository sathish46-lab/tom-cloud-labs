<?php
require_once '../../src/load.php';
use TomLabs\Labs\Quiz;

// Auth Protection
if (!Session::getAuthStatus()) {
    header("Location: /signin");
    exit;
}

$topicId = $_GET['topic'] ?? null;
$subtopicId = $_GET['subtopic'] ?? null;

if (!$topicId || !$subtopicId) {
    header("Location: /quiz");
    exit;
}

Session::$pageTitle = "AI Quiz Generation";
Session::loadMaster();
