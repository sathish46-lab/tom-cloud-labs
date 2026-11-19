<?php
require_once __DIR__ . '/../../load.php';

$admin = AuthMiddleware::requireAdmin();
AuthMiddleware::requireCsrf();

$mode = trim($_POST['video_call_mode'] ?? '');

if (!in_array($mode, ChatSettings::MODES, true)) {
api_json(['status' => 'error', 'error' => 'Unknown video call mode'], 400);
}

$before = ChatSettings::get();

if (!ChatSettings::save($mode, (string)($admin->getEmail() ?? ''))) {
    errors_report(['context' => 'admin/chat_settings_save', 'message' => 'Failed writing chat.json']);
api_json(['status' => 'error', 'error' => 'Failed to save chat settings (file not writable)'], 400);
}

AuditLog::log('update', 'settings', 'chat.json', [
    'action' => 'set_video_call_mode',
    'from'   => $before['video_call_mode'] ?? null,
    'to'     => $mode,
], (string)($admin->getUserId() ?? 'unknown'));

api_json([
    'status'  => 'success',
    'settings' => ChatSettings::get(),
], 200);
