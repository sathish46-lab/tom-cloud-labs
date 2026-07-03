<?php
require_once '../load.php';
require_once '../lib/core/VPN.class.php';
$dbResources = VPN::request('ip', 'all', ['device' => 'wg0']);
echo json_encode($dbResources);
