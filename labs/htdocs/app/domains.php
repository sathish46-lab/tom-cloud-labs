<?php
require_once __DIR__ . '/../src/load.php';
$user = Session::getUser();
$db = DatabaseConnection::getClient()->selectDatabase('tom_labs_db');

$myDomains = iterator_to_array($db->domains->find(['user_id' => $user->getUserId()]));

Session::$pageTitle = "Domains";
Session::set('user_domains', $myDomains);
Session::loadMaster();