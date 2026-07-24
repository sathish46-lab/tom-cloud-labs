<?php
require_once '../../load.php';

header('Content-Type: application/json');

echo json_encode([
    'status' => 'ok',
    'message' => 'Rate limit test endpoint working.',
    'time' => date('Y-m-d H:i:s'),
    'tip' => 'Run: for i in $(seq 1 310); do curl -s -o /dev/null -w "%{http_code} " http://localhost:9081/api/test/ratelimit; done; echo "" — to trigger 429'
]);
