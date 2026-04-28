<?php
require_once __DIR__ . '/_challenge_base.php';
$activeTab = 'challenges';
$labTitle = Session::get('challenge_title', 'Challenge');
Session::$pageTitle = "Challenges / $labTitle / Challenges";
Session::loadMaster();
