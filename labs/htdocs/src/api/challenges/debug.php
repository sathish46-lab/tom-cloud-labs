<?php
require_once __DIR__ . '/../../load.php';
header('Content-Type: text/plain');
$db = DatabaseConnection::getClient()->selectDatabase('tom_labs_db');
$instance = $db->challenge_instances->findOne(['instance_hash' => '24045f331583852d3948a01b368fdbfd']);
$createdAt = $instance['created_at'] ?? time();
$expiresAt = $createdAt + (15 * 60);
$timeLeft = max(0, $expiresAt - time());
echo "createdAt: $createdAt\n";
echo "time(): " . time() . "\n";
echo "expiresAt: $expiresAt\n";
echo "timeLeft: $timeLeft\n";
