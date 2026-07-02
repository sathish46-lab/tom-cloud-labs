<?php
require_once __DIR__ . '/../../src/load.php';
$db = DatabaseConnection::getDefaultDatabase();

echo "Testing LabFeatures...\n";
$features = \TomLabs\Labs\LabFeatures::getSupportedFeatures('essentials');
print_r($features);

echo "Supports expose_web (essentials)? " . (\TomLabs\Labs\LabFeatures::supports('essentials', 'expose_web') ? 'YES' : 'NO') . "\n";
echo "Supports always_on (essentials)? " . (\TomLabs\Labs\LabFeatures::supports('essentials', 'always_on') ? 'YES' : 'NO') . "\n";
