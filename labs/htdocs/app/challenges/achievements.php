<?php
/**
 * Challenge Achievements Entry Point
 */
require_once __DIR__ . '/_challenge_base.php';
$activeTab = 'achievements';
$labTitle = Session::get('challenge_title', 'Challenge');
Session::$pageTitle = "Challenges / $labTitle / Achievements";
Session::loadMaster();
