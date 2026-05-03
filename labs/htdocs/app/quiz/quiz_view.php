<?php
require_once '../../src/load.php';

// Auth Protection
if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    // Show professional session expired UI
}

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: /quiz");
    exit;
}

use TomLabs\Labs\Quiz;

$currentTopic = Quiz::getCategory($id);
if (!$currentTopic) {
    header("Location: /quiz");
    exit;
}

$subtopics = Quiz::getSubtopicsForCategory($id);
$activeTab = strtolower(trim($_GET['tab'] ?? 'topics'));

Session::$property['current_topic'] = $currentTopic;
Session::$property['current_topic']['subtopics'] = $subtopics;
Session::$property['active_tab'] = $activeTab;

Session::$pageTitle = "Quiz / " . $currentTopic['title'];
Session::loadMaster();
