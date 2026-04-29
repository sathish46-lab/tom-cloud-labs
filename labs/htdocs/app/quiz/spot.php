<?php
require_once '../../src/load.php';

// Auth Protection
if (!Session::getAuthStatus()) {
    header("Location: /signin");
    exit;
}

$quizId = $_GET['id'] ?? null;

if (!$quizId) {
    header("Location: /quiz");
    exit;
}

// Mocking quiz data retrieval based on ID
// In production, this would fetch from a DB
$quizTitle = "You Guard the Whispering Datastream in the Midnight Server Room";
$quizDesc = "You step into a role where safeguarding information is your everyday craft. These items examine how well you can identify the basic tools, configurations, and safeguards that keep confidential data from slipping away—whether in motion, at rest, or under lock and key.";
$quizTags = ["data-loss-prevention", "email-security", "policy", "backups", "business-continuity", "data-protection", "physical-security", "infrastructure", "cloud-security", "access-control", "encryption", "network-security", "data-in-transit"];

Session::$property['current_quiz'] = [
    'id' => $quizId,
    'title' => $quizTitle,
    'desc' => $quizDesc,
    'tags' => $quizTags,
    'difficulty' => 'Easy',
    'zeal' => 15,
    'zolt' => 2,
    'time' => '05:00'
];

Session::$pageTitle = "Spot Quiz - Evaluation";
Session::loadMaster();
