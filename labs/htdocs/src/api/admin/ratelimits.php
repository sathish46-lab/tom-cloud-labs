<?php
/**
 * POST /api/admin/ratelimits
 *
 * Admin-only. Lists active rate limit counters and clears them.
 *
 * Actions (via POST body 'action'):
 *   - 'list'      : Return all active rate limit counters (default)
 *   - 'clear_all' : Delete every rate limit counter file
 *   - 'clear_one' : Delete a single counter file (requires 'file' param)
 *
 * Response: {"status":"success","data":[...],"removed":N}
 */
require_once __DIR__ . '/../../load.php';

$admin = AuthMiddleware::requireAdmin();
AuthMiddleware::requireCsrf();

$action = $_POST['action'] ?? 'list';

$storageDirs = [];
$globalDir = is_dir('/dev/shm') && is_writable('/dev/shm') ? '/dev/shm/ratelimit' : '/tmp/ratelimit';
$actionDir = is_dir('/dev/shm') && is_writable('/dev/shm') ? '/dev/shm/ratelimit_actions' : '/tmp/ratelimit_actions';
$storageDirs[] = $globalDir;
$storageDirs[] = $actionDir;

$now = time();
$counters = [];

foreach ($storageDirs as $dir) {
    if (!is_dir($dir)) {
        continue;
    }
    foreach (glob($dir . '/*.count') as $file) {
        $basename = basename($file);
        // Parse: <kind>_<md5>_<window>.count  or  <actionkey>_<window>.count
        $parts = explode('_', $basename);
        $windowPart = array_pop($parts); // <window>.count
        $windowNum = (int) str_replace('.count', '', $windowPart);
        $keyName = implode('_', $parts);

        $resetTime = $windowNum * 900; // default assumption; actual reset = next window boundary
        $remaining = max(0, $resetTime - $now);
        $content = @file_get_contents($file);
        $count = (int) $content;

        // Determine type from key prefix
        $type = 'global';
        if (str_starts_with($keyName, 'em_')) {
            $label = 'Email: ' . substr($keyName, 3);
            $type = 'email';
        } elseif (str_starts_with($keyName, 'ip_')) {
            $label = 'IP: ' . substr($keyName, 3);
            $type = 'ip';
        } elseif (str_starts_with($keyName, 'usr_')) {
            $label = 'User: ' . substr($keyName, 4);
            $type = 'user';
        } else {
            $label = 'Action: ' . $keyName;
            $type = 'action';
        }

        $counters[] = [
            'file'   => $basename,
            'path'   => $file,
            'type'   => $type,
            'label'  => $label,
            'count'  => $count,
            'window' => $windowNum,
            'reset_in' => $remaining,
            'mtime'  => @filemtime($file),
        ];
    }
}

// Sort: most recent first
usort($counters, function ($a, $b) {
    return ($b['mtime'] ?? 0) - ($a['mtime'] ?? 0);
});

if ($action === 'clear_all') {
    $removed = 0;
    $failed = 0;
    foreach ($storageDirs as $dir) {
        if (!is_dir($dir)) {
            continue;
        }
        foreach (glob($dir . '/*.count') as $file) {
            if (@unlink($file)) {
                $removed++;
            } else {
                $failed++;
            }
        }
    }
    AuditLog::log('clear', 'all_ratelimits', 'ratelimits', ['removed_by' => (string)($admin->getEmail() ?? $_SESSION['username'] ?? 'unknown'), 'removed' => $removed]);
    api_json(['status' => 'success', 'message' => "Cleared $removed rate limit counter(s).", 'removed' => $removed, 'failed' => $failed], 200);
}

if ($action === 'clear_one') {
    $file = $_POST['file'] ?? '';
    if ($file === '' || !preg_match('/^[a-z0-9_]+_[0-9]+\.count$/', $file)) {
        api_json(['status' => 'error', 'error' => 'Invalid file name.'], 400);
    }
    $found = false;
    foreach ($storageDirs as $dir) {
        $candidate = $dir . '/' . $file;
        if (file_exists($candidate)) {
            $found = true;
            if (@unlink($candidate)) {
                AuditLog::log('clear', 'ratelimit', 'ratelimits', ['removed_by' => (string)($admin->getEmail() ?? $_SESSION['username'] ?? 'unknown'), 'file' => $file]);
                api_json(['status' => 'success', 'message' => 'Cleared rate limit counter: ' . htmlspecialchars($file), 'removed' => 1], 200);
            } else {
                api_json(['status' => 'error', 'error' => 'Failed to delete counter file.'], 500);
            }
        }
    }
    if (!$found) {
        api_json(['status' => 'error', 'error' => 'Counter file not found.'], 404);
    }
}

// Default: list
api_json(['status' => 'success', 'data' => $counters, 'total' => count($counters)], 200);
