<?php
/**
 * Learn AI - Chat History API
 * Returns chat history for a given chapter (AJAX endpoint)
 */
require_once __DIR__ . '/../../load.php';

header('Content-Type: text/html');

// 1. Validate Session
if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    http_response_code(401);
    echo '<div class="text-center p-3 text-danger small">Unauthorized.</div>';
    exit;
}

$user = Session::getUser();
$userId = (int)$user->getUserId();

// 2. Get parameters from query string
$lessonId = $_GET['lesson_id'] ?? '';
$chapterId = $_GET['chapter_id'] ?? '';

try {
    $db = DatabaseConnection::getDefaultDatabase();
    
    // First try shared lesson history
    $chatDoc = null;
    if (!empty($lessonId)) {
        $chatDoc = $db->ai_chat_history->findOne([
            'user_id' => $userId,
            'lesson_id' => (string)$lessonId
        ]);
    }
    
    // Fallback to chapter specific history
    if (!$chatDoc && !empty($chapterId)) {
        $chatDoc = $db->ai_chat_history->findOne([
            'user_id' => $userId,
            'chapter_id' => (string)$chapterId
        ]);
    }
    
    $messages = [];
    $summary = null;
    
    if ($chatDoc && isset($chatDoc['messages'])) {
        foreach ($chatDoc['messages'] as $m) {
            $msg = (array)$m;
            if (($msg['role'] ?? '') === 'system_summary') {
                $summary = [
                    'content' => $msg['content'] ?? '',
                    'summarized_count' => (int)($msg['summarized_count'] ?? 0),
                    'timestamp' => (int)($msg['timestamp'] ?? 0)
                ];
            } else {
                $messages[] = [
                    'role' => $msg['role'] ?? 'user',
                    'content' => $msg['content'] ?? '',
                    'timestamp' => (int)($msg['timestamp'] ?? 0),
                    'usage' => isset($msg['usage']) ? (array)$msg['usage'] : null
                ];
            }
        }
    }
    
    // Sort messages chronologically
    usort($messages, function($a, $b) {
        return $a['timestamp'] - $b['timestamp'];
    });
    
    $userAvatar = Session::getAvatar();
    $userAvatarStyle = Session::getAvatarStyle();
    $aiAvatar = "/assets/logo/logo.png";
    
    // Output HTML directly
    $html = '';
    
    if ($summary) {
        $summaryText = nl2br(htmlspecialchars($summary['content']));
        $html .= '
        <div class="message-row sys-row text-center my-2 w-100 justify-content-center">
            <div class="msg-bubble bg-dark bg-opacity-50 border border-secondary border-opacity-10 py-2 px-3 text-secondary" style="font-size: 0.75rem; border-radius: 8px; max-width: 80%;">
                <div class="d-flex flex-column align-items-center">
                    <i class="bx bx-history mb-1 opacity-50"></i>
                    <strong>Previous Session Summary:</strong>
                    <p class="m-0 mt-1 text-secondary" style="font-size: 0.8rem; line-height: 1.5;">' . $summaryText . '</p>
                </div>
            </div>
        </div>';
    }
    
    foreach ($messages as $msg) {
        $content = nl2br(htmlspecialchars($msg['content']));
        if ($msg['role'] === 'user') {
            $html .= '
            <div class="message-row user-row ms-auto">
                <div class="msg-bubble">
                    <p class="m-0">' . $content . '</p>
                </div>
                <!-- <div class="msg-avatar">
                    <img src="' . $userAvatar . '" style="width: 30px; ' . $userAvatarStyle . '" alt="User">
                </div> -->
            </div>';
        } else {
            $usageAttrs = '';
            if (!empty($msg['usage'])) {
                $usage = $msg['usage'];
                $inp = isset($usage['input_tokens']) ? (int)$usage['input_tokens'] : 0;
                $out = isset($usage['output_tokens']) ? (int)$usage['output_tokens'] : 0;
                $cache = isset($usage['cached_tokens']) ? (int)$usage['cached_tokens'] : 0;
                $total = isset($usage['total_tokens']) ? (int)$usage['total_tokens'] : 0;
                $usageAttrs = sprintf(' data-input-tokens="%d" data-output-tokens="%d" data-cached-tokens="%d" data-total-tokens="%d"', $inp, $out, $cache, $total);
            }
            $html .= '
            <div class="message-row ai-row"' . $usageAttrs . '>
                <div class="msg-avatar">
                    <img src="' . $aiAvatar . '" style="width: 30px;" alt="AI">
                </div>
                <div class="msg-bubble">
                    <p class="m-0">' . $content . '</p>
                </div>
            </div>';
        }
    }
    
    echo $html;

} catch (Exception $e) {
    http_response_code(500);
    echo '<div class="text-center p-3 text-danger small">Failed to load chat history.</div>';
}
