<?php
/**
 * AI Tool API: Read File Content from Lab Container
 * Called by ai_worker.py when the AI triggers the read_file_content tool.
 */
require_once __DIR__ . '/../../../load.php';

AuthMiddleware::requireInternalToken();

$input = json_decode(file_get_contents('php://input'), true);
$userId = $input['user_id'] ?? null;
$filePath = $input['file_path'] ?? '';

if (empty($userId) || empty($filePath)) {
api_json(['status' => 'error', 'error' => 'Missing user_id or file_path'], 400);
}

try {
    $db = DatabaseConnection::getDefaultDatabase();
    $inst = $db->machine_labs->findOne([
        'user_id' => (int)$userId,
        'status' => 'running'
    ]);
    $labDoc = $inst;

    if (!$labDoc || empty($labDoc['instance_hash'])) {
api_json(['status' => 'error', 'error' => 'No running lab instance found for this user'], 400);
    }
    $instanceHash = $labDoc['instance_hash'];
} catch (Exception $e) {
api_json(['status' => 'error', 'error' => 'Database error: ' . errors_from_exception($e, 'learnAI/tools/read_file')['error']], 500);
}

// Block sensitive paths
$blockedPaths = ['/etc/shadow', '/etc/gshadow', '/.labsconfig'];
foreach ($blockedPaths as $blocked) {
    if (strpos(realpath($filePath) ?: $filePath, $blocked) !== false || $filePath === $blocked) {
api_json(['status' => 'error', 'error' => 'Access to this file is restricted'], 403);
    }
}

try {
    $db = DatabaseConnection::getClient()->selectDatabase('tom_labs_db');
    $inst = $db->machine_labs->findOne(['instance_hash' => $instanceHash]);
    $labData = $inst;

    if (!$labData || ($labData['status'] ?? 'offline') !== 'running') {
        throw new Exception('Lab not found or not running');
    }

    $escapedPath = escapeshellarg($filePath);
    $escapedHash = escapeshellarg($instanceHash);

    $dockerCmd = "docker exec {$escapedHash} cat {$escapedPath} 2>&1";

    $output = [];
    $exitCode = 0;
    exec($dockerCmd, $output, $exitCode);

    $content = implode("\n", $output);

    // Limit to 8KB
    if (strlen($content) > 8192) {
        $content = substr($content, 0, 8192) . "\n... (file truncated to 8KB)";
    }

    if ($exitCode !== 0) {
        throw new Exception("File read failed: {$content}");
    }

    api_json([
        'status'  => 'success',
        'content' => $content,
        'path'    => $filePath
    ], 200);

} catch (Exception $e) {
    api_json(['status' => 'error', 'error' => errors_from_exception($e, 'learnAI/tools/read_file')['error']], 500);
}
