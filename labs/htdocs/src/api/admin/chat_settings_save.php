<?php
require_once __DIR__ . '/../../load.php';

header('Content-Type: application/json');

$admin = AuthMiddleware::requireAdmin();
AuthMiddleware::requireCsrf();

$mode = trim($_POST['video_call_mode'] ?? '');

if (!in_array($mode, ChatSettings::MODES, true)) {
    echo json_encode(['status' => 'error', 'error' => 'Unknown video call mode']);
    exit;
}

$before = ChatSettings::get();

if (!ChatSettings::save($mode, (string)($admin->getEmail() ?? ''))) {
    errors_report(['context' => 'admin/chat_settings_save', 'message' => 'Failed writing chat.json']);
    echo json_encode(['status' => 'error', 'error' => 'Failed to save chat settings (file not writable)']);
    exit;
}

AuditLog::log('update', 'settings', 'chat.json', [
    'action' => 'set_video_call_mode',
    'from'   => $before['video_call_mode'] ?? null,
    'to'     => $mode,
], (string)($admin->getUserId() ?? 'unknown'));

echo json_encode([
    'status'  => 'success',
    'settings' => ChatSettings::get(),
]);
