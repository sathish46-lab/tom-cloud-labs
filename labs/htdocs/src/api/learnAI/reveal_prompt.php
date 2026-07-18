<?php
/**
 * Learn AI - Reveal Generation Prompt API
 * Outputs pure HTML modal fragment to view or confirm revealing a lesson's generation prompt
 */
require_once __DIR__ . '/../../load.php';

if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    http_response_code(401);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<div class="p-4 text-center text-danger">Unauthorized. Please log in.</div>';
    exit;
}

$user = Session::getUser();
$userId = (int)$user->getUserId();
$userEmail = $user->getEmail();
$username = $user->getUsername();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$lessonId = trim($input['lesson_id'] ?? $_POST['lesson_id'] ?? $_GET['lesson_id'] ?? '');

if (empty($lessonId)) {
    http_response_code(400);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<div class="p-4 text-center text-danger">lesson_id is required.</div>';
    exit;
}

try {
    $db = DatabaseConnection::getDefaultDatabase();
    $lesson = $db->ai_lessons->findOne(['_id' => new MongoDB\BSON\ObjectId($lessonId)]);
    if (!$lesson) {
        http_response_code(404);
        header('Content-Type: text/html; charset=UTF-8');
        echo '<div class="p-4 text-center text-danger">Lesson not found.</div>';
        exit;
    }

    // Check if user is author/owner
    $isAuthor = false;
    if (!empty($lesson['author']) && strcasecmp($lesson['author'], $username) === 0) {
        $isAuthor = true;
    } elseif (!empty($lesson['author_email']) && strcasecmp($lesson['author_email'], $userEmail) === 0) {
        $isAuthor = true;
    } elseif (!empty($lesson['user_id']) && (int)$lesson['user_id'] === $userId && $userId > 0) {
        $isAuthor = true;
    }

    // Check if already revealed
    $existingReveal = $db->ai_unlocked_prompts->findOne(['user_id' => $userId, 'lesson_id' => (string)$lessonId]);
    $alreadyRevealed = ($isAuthor || $existingReveal) ? true : false;

    // Check current Jolt fuel
    $stats = \TomLabs\Labs\Quiz::getUserStats($userEmail);
    $currentJolt = (int)($stats['jolt'] ?? 0);

    // Determine prompt text
    $promptText = trim($lesson['prompt'] ?? $lesson['original_prompt'] ?? '');
    if ($promptText === '') {
        $job = $db->ai_lesson_jobs->findOne(['lesson_id' => (string)$lessonId]);
        if (!$job) {
            $job = $db->ai_lesson_jobs->findOne(['topic' => $lesson['title'] ?? '']);
        }
        if ($job && !empty($job['topic'])) {
            $promptText = trim($job['topic']);
        } else {
            $promptText = "Act as an expert instructor. I want to learn about " . ($lesson['title'] ?? 'this topic') . ". " . ($lesson['description'] ?? '');
        }
    }

    $titleHtml = htmlspecialchars($lesson['title'] ?? 'Lesson');
    $levelHtml = htmlspecialchars(strtolower($lesson['level'] ?? 'beginner'));
    $dateHtml = strtolower(date('M d, Y', $lesson['created_at'] ?? time()));
    $promptHtml = htmlspecialchars($promptText);
    $safeLessonId = htmlspecialchars($lessonId);
    $currentJoltFormatted = number_format($currentJolt);
    $remainingJoltFormatted = number_format(max(0, $currentJolt - 1));

    header('Content-Type: text/html; charset=UTF-8');

    if (!$alreadyRevealed) {
        // Cost confirmation modal in pure HTML
        echo <<<HTML
<div class="modal-header border-0 pt-4 px-4">
    <h5 class="modal-title fw-bold">Reveal Generation Prompt</h5>
    <button type="button" class="btn-close btn-close-white" data-coreui-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body px-4 pt-2 pb-4">
    <div class="mb-3">
        🏷 You are about to reveal the generation prompt for <strong>{$titleHtml}</strong>
    </div>
    
    <hr class="text-secondary opacity-25">
    
    <div class="row text-center g-2 my-3">
        <div class="col-4">
            <div class="small fw-bold mb-1">Reveal Cost</div>
            <div>1 ⚡️</div>
        </div>
        <div class="col-4">
            <div class="small fw-bold mb-1">Available Jolt</div>
            <div>{$currentJoltFormatted} ⚡️</div>
        </div>
        <div class="col-4">
            <div class="small fw-bold mb-1">Jolt Remaining</div>
            <div>{$remainingJoltFormatted} ⚡️</div>
        </div>
    </div>
    
    <hr class="text-secondary opacity-25">
    
    <div class="mb-4 mt-3">
        <h6 class="mb-2">Important Notes</h6>
        <ul class="list-unstyled mb-0 d-flex flex-column gap-2 small" style="color: #d97706;">
            <li>• This will reveal the original prompt used to generate this lesson</li>
            <li>• 1 ⚡️ Jolts will be deducted (one-time payment)</li>
            <li>• Once revealed, you can view it anytime for free</li>
        </ul>
    </div>
    
    <div class="d-flex justify-content-end gap-2 mt-4">
        <button type="button" class="btn btn-secondary rounded-pill px-4" data-coreui-dismiss="modal">Cancel</button>
        <button type="button" class="btn rounded-pill px-4 text-white btn-confirm-reveal-prompt" style="background: linear-gradient(135deg, #8b5cf6, #6366f1); border: none;" data-lesson-id="{$safeLessonId}">Reveal Prompt</button>
    </div>
</div>
HTML;
    } else {
        // Revealed prompt modal in exact requested pure HTML format
        echo <<<HTML
<div class="modal-header border-0 pt-4 px-4">
    <h5 class="modal-title fw-bold">Generation Prompt</h5>
    <button type="button" class="btn-close btn-close-white" data-coreui-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body px-4 pt-2 pb-4">
    <div class="mb-3">
        💡 This is the original prompt used to generate <strong>{$titleHtml}</strong>
    </div>

    <div class="card mb-4" style="background-color: var(--cui-body-bg); border: 1px solid var(--cui-border-color);">
        <div class="card-body">
            <p class="mb-0 text-secondary" style="white-space: pre-wrap; font-size: 0.9rem;">{$promptHtml}</p>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mt-4">
        <div class="d-flex gap-2 flex-wrap">
            <span class="badge rounded-pill bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-3 py-1 fw-normal d-inline-flex align-items-center gap-1 shadow-sm">
                {$levelHtml}
            </span>
            <span class="badge rounded-pill bg-dark bg-opacity-75 border border-secondary border-opacity-25 text-secondary px-3 py-1 fw-normal d-inline-flex align-items-center gap-1 shadow-sm">
                {$dateHtml}
            </span>
        </div>
        <button type="button" class="btn btn-secondary rounded-pill px-4" data-coreui-dismiss="modal">Close</button>
    </div>
</div>
HTML;
    }
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<div class="p-4 text-center text-danger">Failed to retrieve prompt: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
