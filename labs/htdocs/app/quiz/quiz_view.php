<?php
require_once '../../src/load.php';

// Auth Protection
if (!Session::getAuthStatus()) {
    header("Location: /signin");
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: /quiz");
    exit;
}

// Find the topic in JSON
$jsonFile = __DIR__ . '/../../src/data/quiz_topics.json';
$quizData = file_exists($jsonFile) ? json_decode(file_get_contents($jsonFile), true) : [];
$currentTopic = null;

foreach ($quizData as $category => $items) {
    foreach ($items as $item) {
        if ($item['id'] === $id) {
            $currentTopic = $item;
            break 2;
        }
    }
}

if (!$currentTopic) {
    header("Location: /quiz");
    exit;
}

Session::$property['current_topic'] = $currentTopic;
Session::$pageTitle = $currentTopic['title'];
Session::loadMaster();
