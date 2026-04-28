<?php
require_once __DIR__ . '/_challenge_base.php';
$activeTab = 'dashboard';
$labTitle = Session::get('challenge_title', 'Challenge');
Session::$pageTitle = "Challenges / $labTitle / Dashboard";
Session::loadMaster();
