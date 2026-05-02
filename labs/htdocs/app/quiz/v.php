<?php
require_once '../../src/load.php';
use TomLabs\Labs\Quiz;

// Auth Protection
if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    // Session expired UI
}

$hash = $_GET['hash'] ?? null;
if (!$hash) {
    header("Location: /quiz");
    exit;
}

$quiz = Quiz::getByHash($hash);
if (!$quiz) {
    header("Location: /quiz");
    exit;
}

// Fetch context for breadcrumbs and navigation
$subtopic = Quiz::getSubtopic($quiz['subtopic_id']);
$parent = Quiz::getCategory($quiz['category_id']);

Session::$property['current_quiz'] = $quiz;
Session::$property['parent_topic'] = $parent;
Session::$property['current_subtopic'] = $subtopic;

Session::$pageTitle = "Quiz / " . $parent['title'] . " / " . $subtopic['title'] . " / Spot Quiz";
Session::loadMaster();
