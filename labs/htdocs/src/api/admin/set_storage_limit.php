<?php
require_once __DIR__ . '/../../load.php';
require_once __DIR__ . '/../../utils/storage.php';

$admin = AuthMiddleware::requireAdmin();
AuthMiddleware::requireCsrf();

$email = trim($_POST['email'] ?? '');
$reset = isset($_POST['reset']) && $_POST['reset'] !== '' && $_POST['reset'] !== '0';

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    api_json(['status' => 'error', 'error' => 'Valid email required'], 400);
}

$limit = null;
if (!$reset) {
    // Accept an explicit byte count, or a human GB figure from the profile modal.
    if (isset($_POST['limit_bytes']) && $_POST['limit_bytes'] !== '') {
        $raw = $_POST['limit_bytes'];
        if (!is_numeric($raw)) {
            api_json(['status' => 'error', 'error' => 'Storage limit must be a number'], 400);
        }
        $limit = (int)$raw;
    } elseif (isset($_POST['limit_gb']) && $_POST['limit_gb'] !== '') {
        $raw = $_POST['limit_gb'];
        if (!is_numeric($raw) || (float)$raw <= 0) {
            api_json(['status' => 'error', 'error' => 'Storage limit must be greater than 0'], 400);
        }
        $limit = (int)round((float)$raw * 1073741824);
    } else {
        api_json(['status' => 'error', 'error' => 'Storage limit (GB) required'], 400);
    }

    if ($limit <= 0) {
        api_json(['status' => 'error', 'error' => 'Storage limit must be greater than 0'], 400);
    }
    if ($limit > STORAGE_LIMIT_MAX) {
        api_json(['status' => 'error', 'error' => 'Storage limit cannot exceed ' . storage_format_bytes(STORAGE_LIMIT_MAX)], 400);
    }
}

try {
    $db = DatabaseConnection::getDefaultDatabase();
    $target = $db->users->findOne(['email' => $email]);
    if (!$target) {
        api_json(['status' => 'error', 'error' => 'User not found'], 400);
    }

    $before = storage_limit_override_of((array)$target);
    $plan   = storage_plan_of((array)$target);

    if ($reset) {
        $db->users->updateOne(['email' => $email], ['$unset' => ['storage_limit_bytes' => '']]);
    } else {
        $db->users->updateOne(['email' => $email], ['$set' => ['storage_limit_bytes' => $limit]]);
    }

    // Keep the cached usage row in step so the Storage Quotas page is accurate
    // before the next re-audit.
    try {
        $cap = $reset ? storage_plan_limit($plan) : (int)$limit;
        $db->storage_usage->updateOne(
            ['user_email' => $email],
            ['$set' => ['limit_bytes' => $cap, 'limit_custom' => !$reset]],
            ['upsert' => true]
        );
    } catch (Throwable $e) {
        errors_report(['context' => 'admin/set_storage_limit', 'message' => 'set_storage_limit usage sync: ' . $e->getMessage()]);
    }

    $after = $reset ? null : (int)$limit;

    AuditLog::log(
        'update',
        'user',
        (string)($target['user_id'] ?? $email),
        [
            'field'        => 'storage_limit_bytes',
            'action'       => $reset ? 'reset_to_plan' : 'set_custom_limit',
            'from'         => $before,
            'to'           => $after,
            'plan'         => $plan,
            'effective'    => storage_effective_limit($after, $plan),
            'target_email' => $email,
        ],
        (string)($admin->getUserId() ?? 'unknown')
    );

    api_json([
        'status'        => 'success',
        'limit_bytes'   => $after,
        'plan'          => $plan,
        'effective'     => storage_effective_limit($after, $plan),
        'label'         => $after === null ? 'Plan default' : storage_format_bytes($after),
        'unchanged'     => $before === $after,
    ], 200);
} catch (Throwable $e) {
    errors_report(['context' => 'admin/set_storage_limit', 'message' => 'set_storage_limit: ' . $e->getMessage()]);
    api_json(['status' => 'error', 'error' => 'Failed to update storage limit'], 400);
}
