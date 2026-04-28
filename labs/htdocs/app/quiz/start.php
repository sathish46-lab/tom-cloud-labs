<?php
require_once '../../src/load.php';

// Auth Protection
if (!Session::getAuthStatus()) {
    header("Location: /signin");
    exit;
}

$parentId = $_GET['parent'] ?? null;
$topicId = $_GET['topic_id'] ?? null;

if (!$parentId || !$topicId) {
    header("Location: /quiz");
    exit;
}

// Find parent topic in JSON
$jsonFile = __DIR__ . '/../../src/data/quiz_topics.json';
$quizData = file_exists($jsonFile) ? json_decode(file_get_contents($jsonFile), true) : [];
$parentTopic = null;

foreach ($quizData as $category => $items) {
    foreach ($items as $item) {
        if ($item['id'] === $parentId) {
            $parentTopic = $item;
            break 2;
        }
    }
}

if (!$parentTopic) {
    header("Location: /quiz");
    exit;
}

// Find specific subtopic by ID
$subtopic = null;
foreach ($parentTopic['subtopics'] ?? [] as $sub) {
    if ($sub['id'] === $topicId) {
        $subtopic = $sub;
        break;
    }
}

if (!$subtopic) {
    header("Location: /quiz/" . $parentId);
    exit;
}

Session::$property['parent_topic'] = $parentTopic;
Session::$property['current_subtopic'] = $subtopic;

Session::$pageTitle = $subtopic['title'] . " - Spot Quiz";
Session::loadMaster();
