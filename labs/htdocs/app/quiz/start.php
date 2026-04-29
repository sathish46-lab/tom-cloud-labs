<?php
require_once '../../src/load.php';

// Auth Protection
if (!Session::getAuthStatus()) {
    header("Location: /signin");
    exit;
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

if (!$parentTopic || !$subtopic) {
    header("Location: /quiz");
    exit;
}

Session::$property['parent_topic'] = $parentTopic;
Session::$property['current_subtopic'] = $subtopic;

Session::$pageTitle = $subtopic['title'] . " - Spot Quiz";
Session::loadMaster();
