<?php
require_once __DIR__ . '/src/load.php';

echo "Essentials: " . (\TomLabs\Labs\LabFeatures::supports('essentials', 'expose_web') ? 'YES' : 'NO') . "\n";
echo "MinIO: " . (\TomLabs\Labs\LabFeatures::supports('minio', 'expose_web') ? 'YES' : 'NO') . "\n";
echo "n8n: " . (\TomLabs\Labs\LabFeatures::supports('n8n', 'expose_web') ? 'YES' : 'NO') . "\n";
echo "Docker: " . (\TomLabs\Labs\LabFeatures::supports('docker_lab', 'expose_web') ? 'YES' : 'NO') . "\n";
