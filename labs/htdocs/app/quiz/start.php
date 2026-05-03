<?php
require_once '../../src/load.php';

// Auth Protection
// Auth Protection is handled within the master layout for a better UX
if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    // We don't redirect here so that the user can see the "Session Expired" UI
    // inside the professional layout (sidebar, etc.)
}

$parentId = $_GET['parent'] ?? null;
$subtopicId = $_GET['topic_id'] ?? null;

if (!$parentId || !$subtopicId) {
    header("Location: /quiz");
    exit;
}

use TomLabs\Labs\Quiz;

$parentTopic = Quiz::getCategory($parentId);
$subtopic = Quiz::getSubtopic($subtopicId);
$activeTab = strtolower(trim($_GET['tab'] ?? 'recent'));

if (!$parentTopic || !$subtopic) {
    header("Location: /quiz");
    exit;
}

Session::$property['parent_topic'] = $parentTopic;
Session::$property['current_subtopic'] = $subtopic;
Session::$property['active_tab'] = $activeTab;

// Persistent difficulty filter from cookie
$difficultyFilter = $_COOKIE['quiz_difficulty_filter'] ?? 'normal';
Session::$property['difficulty_filter'] = $difficultyFilter;

Session::$pageTitle = "Quiz / " . $parentTopic['title'] . " / " . $subtopic['title'];
Session::loadMaster();
