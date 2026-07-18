<?php
/**
 * AJAX HTMX endpoint for autocomplete searching lessons
 * Returns HTML to populate the dropdown below the prompt textarea.
 */
require_once __DIR__ . '/../../load.php';

header('Content-Type: text/html; charset=utf-8');

if (Session::getAuthStatus() != Constants::STATUS_LOGGEDIN) {
    exit;
}

$query = trim($_POST['query'] ?? '');
if (strlen($query) < 2) {
    // Return empty state
    echo '<div class="col-12 w-100 text-center py-5 mt-4">';
    echo '<h5 class="text-body-emphasis fw-semibold mb-2" style="font-size: 1.1rem;">Type a topic to search existing lessons 🔍</h5>';
    echo '</div>';
    exit;
}

$db = DatabaseConnection::getDefaultDatabase();
$user = Session::getUser();
$currentUsername = $user ? $user->getUsername() : '';
$currentEmail = $user ? $user->getEmail() : '';
$currentUserId = $user ? (int)$user->getUserId() : 0;

// Split query into keywords and build an $and filter where each keyword must match at least one field
$words = preg_split('/\s+/', $query);
$words = array_filter($words, function($w) { return strlen($w) >= 2; });
$stopWords = ['the', 'and', 'for', 'with', 'that', 'this', 'from', 'have', 'are', 'want', 'learn', 'teach', 'how', 'can', 'about', 'cover', 'like', 'using'];
$words = array_filter($words, function($w) use ($stopWords) { return !in_array(strtolower($w), $stopWords); });
$words = array_values(array_slice($words, 0, 6)); // Max 6 keywords

if (empty($words)) {
    echo '<div class="col-12 w-100 text-center py-5 mt-4">';
    echo '<h5 class="text-body-emphasis fw-semibold mb-2" style="font-size: 1.1rem;">No lessons found! 🎓</h5>';
    echo '<p class="text-body-secondary mb-0" style="font-size: 0.95rem;">Try different keywords</p>';
    echo '</div>';
    exit;
}

$andConditions = [];
foreach ($words as $w) {
    $safeWord = preg_quote($w);
    $andConditions[] = [
        '$or' => [
            ['title' => new MongoDB\BSON\Regex($safeWord, 'i')],
            ['topic' => new MongoDB\BSON\Regex($safeWord, 'i')],
            ['prompt' => new MongoDB\BSON\Regex($safeWord, 'i')]
        ]
    ];
}

$filter = count($andConditions) === 1 ? $andConditions[0] : ['$and' => $andConditions];

$lessonsRaw = $db->ai_lessons->find($filter, ['limit' => 20])->toArray();
$lessons = [];
$seen = [];

foreach ($lessonsRaw as $l) {
    $title = $l['title'] ?? '';
    if (isset($seen[$title])) {
        continue;
    }
    
    $isAuthor = false;
    if (!empty($l['author']) && strcasecmp($l['author'], $currentUsername) === 0) {
        $isAuthor = true;
    } elseif (!empty($l['author_email']) && strcasecmp($l['author_email'], $currentEmail) === 0) {
        $isAuthor = true;
    } elseif (!empty($l['user_id']) && (int)$l['user_id'] === $currentUserId && $currentUserId > 0) {
        $isAuthor = true;
    }

    $visibility = $l['visibility'] ?? 'Public';
    if (strcasecmp($visibility, 'Public') === 0 || $isAuthor) {
        $lessons[] = $l;
        $seen[$title] = true;
    }
    
    if (count($lessons) >= 20) {
        break; 
    }
}

// Render using the shared grid partial
include __DIR__ . '/../../template/partials/learnAI/lessons_grid.php';
