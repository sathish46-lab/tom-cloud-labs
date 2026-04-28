<?php
require_once __DIR__ . '/_challenge_base.php';
$activeTab = 'leaderboard';
$labTitle = Session::get('challenge_title', 'Challenge');
Session::$pageTitle = "Challenges / $labTitle / Leaderboard";
Session::loadMaster();
