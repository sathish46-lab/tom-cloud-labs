<?php
// /var/www/labs/htdocs/src/worker/DeployLog.worker.php
require_once __DIR__ . '/../load.php';
require_once __DIR__ . '/../lib/core/RabbitClient.class.php';
use TomLabs\Labs\IPManager;

// 1. Force unbuffered output
if (function_exists('apache_setenv')) {
    @apache_setenv('no-gzip', 1);
}
@ini_set('zlib.output_compression', 0);
@ini_set('implicit_flush', 1);
putenv('PYTHONUNBUFFERED=1');

// 2. Decode Input
$username = base64_decode($argv[1] ?? 'admin');
$taskData  = json_decode(base64_decode($argv[2] ?? '{}'), true);

$instanceHash = $taskData['hash'] ?? 'unknown';
$action = $taskData['action'] ?? 'deploy'; 

$rabbit = new RabbitClient("logs_" . $instanceHash);

// 3. Wait for Browser WebSocket
sleep(2); 

$python = "/usr/bin/python3";
$script = "/opt/labs-control-panel/labsctl.py";

$isChallenge = !empty($taskData['is_challenge']);

if ($isChallenge) {
    $challengeId = $taskData['challenge_id'] ?? 'unknown';
    $cmd = "sudo $python $script challenge $action --user=$username --hash=$instanceHash --challenge=$challengeId";
} else {
    $labImage = ($taskData['lab'] ?? 'essentials') . ":lab";
    $cmd = "sudo $python $script $action $labImage --user=$username --hash=$instanceHash";

    // Append MinIO flags if present
    if (!empty($taskData['minio_console_domain'])) {
        $cmd .= " --minio-console-domain=" . escapeshellarg($taskData['minio_console_domain']);
    }
    if (!empty($taskData['minio_api_domain'])) {
        $cmd .= " --minio-api-domain=" . escapeshellarg($taskData['minio_api_domain']);
    }

    if (!empty($taskData['n8n_domain'])) {
        $cmd .= " --n8n-domain=" . escapeshellarg($taskData['n8n_domain']);
    }
}


// Redirect stderr to stdout
$cmd .= " 2>&1";

$handle = popen($cmd, 'r');
$success = false;

if (is_resource($handle)) {
    while (!feof($handle)) {
        $line = fgets($handle); 
        if ($line) {
            $trimmed = trim($line);
            
            // Attempt to parse as JSON from our structured logger
            $decoded = json_decode($trimmed, true);
            if (is_array($decoded) && isset($decoded['msg'])) {
                // It's structured
                $msgText = $decoded['msg'];
                $level = $decoded['level'] ?? 'info';
                
                // Add legacy prefixes for the UI if it expects them
                $prefixes = ["info" => "[*]", "success" => "[✓]", "error" => "[!]", "warn" => "[!]"];
                $prefix = $prefixes[$level] ?? "[*]";
                $rabbit->sendMessage(['log' => "$prefix $msgText"]);
                
                if (strpos($msgText, 'Deployment Complete') !== false || 
                    strpos($msgText, 'Code-server started successfully') !== false ||
                    strpos($msgText, 'Code-server is already running') !== false) {
                    $success = true;
                }
            } else {
                // Fallback for raw output (e.g., docker build output, raw errors)
                $rabbit->sendMessage(['log' => $trimmed]);
                
                if (strpos($trimmed, '[✓] Deployment Complete') !== false || 
                    strpos($trimmed, 'Deployment Complete') !== false ||
                    strpos($trimmed, 'Code-server started successfully') !== false) {
                    $success = true;
                }
            }
        }
    }
    pclose($handle);
}

if (!$success && $action === 'deploy') {
    $db = DatabaseConnection::getClient()->selectDatabase('tom_labs_db');
    $db->deployed_labs->updateOne(
        ['instance_hash' => $instanceHash],
        ['$set' => ['status' => 'failed', 'error' => 'Orchestrator timeout or kernel error']]
    );
    $rabbit->sendMessage(['log' => '[!] Deployment failed. Reverting system state...']);
}
?>