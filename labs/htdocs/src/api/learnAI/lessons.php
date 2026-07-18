<?php
/**
 * AJAX endpoint for filtering and fetching lessons (`/api/learnAI/lessons`)
 * Returns JSON containing pre-rendered HTML (`html` property) from shared partial `lessons_grid.php`
 */
require_once __DIR__ . '/../../load.php';

header('Content-Type: text/html; charset=utf-8');

if (Session::getAuthStatus() != Constants::STATUS_LOGGEDIN) {
    echo '<div class="col-12 text-center py-5"><p class="text-danger small">Unauthorized</p></div>';
    exit;
}

$db = DatabaseConnection::getDefaultDatabase();
$user = Session::getUser();
$currentUsername = $user ? $user->getUsername() : '';
$currentEmail = $user ? $user->getEmail() : '';
$currentUserId = $user ? (int)$user->getUserId() : 0;

$filterTab = strtolower(trim($_GET['filter'] ?? $_POST['filter'] ?? 'all'));
$filterLevel = trim($_GET['level'] ?? $_POST['level'] ?? 'All Levels');

$lessonsRaw = $db->ai_lessons->find([])->toArray();
$lessonsAll = [];
$seen = [];

foreach ($lessonsRaw as $l) {
    $title = $l['title'] ?? '';
    if (!isset($seen[$title])) {
        $isAuthor = false;
        if (!empty($l['author']) && strcasecmp($l['author'], $currentUsername) === 0) {
            $isAuthor = true;
        } elseif (!empty($l['author_email']) && strcasecmp($l['author_email'], $currentEmail) === 0) {
            $isAuthor = true;
        } elseif (!empty($l['user_id']) && (int)$l['user_id'] === $currentUserId && $currentUserId > 0) {
            $isAuthor = true;
        }

        $visibility = $l['visibility'] ?? 'Public';
        if (strcasecmp($visibility, 'Private') === 0 && !$isAuthor) {
            continue;
        }

        $likesList = $l['likes'] ?? [];
        if (!is_array($likesList)) {
            $likesList = is_object($likesList) && method_exists($likesList, 'getArrayCopy') ? $likesList->getArrayCopy() : (array)$likesList;
        }
        $likesCount = intval($l['likes_count'] ?? count($likesList));
        $likedByCurrent = false;
        if (in_array($currentUsername, $likesList) || ($currentUserId > 0 && in_array((string)$currentUserId, $likesList))) {
            $likedByCurrent = true;
        } else {
            try {
                if (!empty($currentUsername) && $db->ai_lesson_likes->findOne(['lesson_id' => (string)$l['_id'], 'username' => $currentUsername])) {
                    $likedByCurrent = true;
                }
            } catch (Throwable $t) {}
        }
        $l['likes_count'] = $likesCount;
        $l['liked_by_current'] = $likedByCurrent;
        
        $promptUnlocked = $isAuthor;
        if (!$promptUnlocked && $currentUserId > 0) {
            try {
                if ($db->ai_unlocked_prompts->findOne(['user_id' => $currentUserId, 'lesson_id' => (string)$l['_id']])) {
                    $promptUnlocked = true;
                }
            } catch (Throwable $t) {}
        }
        $l['prompt_unlocked'] = $promptUnlocked;

        $seen[$title] = true;
        $lessonsAll[] = $l;
    }
}

// Filter by Level if specified
if (strcasecmp($filterLevel, 'All Levels') !== 0 && !empty($filterLevel)) {
    $lessonsAll = array_filter($lessonsAll, function($l) use ($filterLevel) {
        $lLevel = $l['level'] ?? 'Beginner';
        return strcasecmp(trim($lLevel), trim($filterLevel)) === 0;
    });
}

// Filter and sort by Tab
$lessons = [];
if ($filterTab === 'continue') {
    foreach ($lessonsAll as $l) {
        if (!empty($l['progress']) && intval($l['progress']) > 0) {
            $lessons[] = $l;
        }
    }
    // If no specific in-progress lessons, show lessons with progress initialized
    if (empty($lessons)) {
        $lessons = $lessonsAll;
    }
} elseif ($filterTab === 'explore') {
    foreach ($lessonsAll as $l) {
        $vis = $l['visibility'] ?? 'Public';
        if (strcasecmp($vis, 'Public') === 0) {
            $lessons[] = $l;
        }
    }

} elseif ($filterTab === 'most_liked') {
    $lessons = $lessonsAll;
    usort($lessons, function($a, $b) {
        return intval($b['likes_count'] ?? 0) <=> intval($a['likes_count'] ?? 0);
    });
    // keep only those with > 0 likes if any exist
    $withLikes = array_filter($lessons, fn($l) => intval($l['likes_count'] ?? 0) > 0);
    if (!empty($withLikes)) {
        $lessons = array_values($withLikes);
    }
} elseif ($filterTab === 'editor_picks') {
    foreach ($lessonsAll as $l) {
        if (!empty($l['editor_pick']) || strcasecmp($l['level'] ?? '', 'Advanced') === 0 || intval($l['chapters_count'] ?? 0) >= 15) {
            $lessons[] = $l;
        }
    }
    if (empty($lessons)) {
        $lessons = $lessonsAll;
    }
} elseif ($filterTab === 'interacted') {
    foreach ($lessonsAll as $l) {
        if (!empty($l['liked_by_current']) || !empty($l['prompt_unlocked']) || (!empty($l['progress']) && intval($l['progress']) > 0)) {
            $lessons[] = $l;
        }
    }
    if (empty($lessons)) {
        $lessons = $lessonsAll;
    }
} elseif ($filterTab === 'my_likes') {
    foreach ($lessonsAll as $l) {
        if (!empty($l['liked_by_current'])) {
            $lessons[] = $l;
        }
    }
} elseif ($filterTab === 'my_lessons') {
    foreach ($lessonsAll as $l) {
        $isAuthor = false;
        if (!empty($l['author']) && strcasecmp($l['author'], $currentUsername) === 0) {
            $isAuthor = true;
        } elseif (!empty($l['author_email']) && strcasecmp($l['author_email'], $currentEmail) === 0) {
            $isAuthor = true;
        } elseif (!empty($l['user_id']) && (int)$l['user_id'] === $currentUserId && $currentUserId > 0) {
            $isAuthor = true;
        }
        if ($isAuthor) {
            $lessons[] = $l;
        }
    }
} elseif ($filterTab === 'my_syllabi') {
    foreach ($lessonsAll as $l) {
        if (!empty($l['is_syllabus']) || strcasecmp($l['type'] ?? '', 'syllabus') === 0) {
            $lessons[] = $l;
        }
    }
} else {
    // 'all' / 'For You'
    $lessons = $lessonsAll;
}

$lessons = array_values($lessons);

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 8;
$offset = ($page - 1) * $limit;
$lessons = array_slice($lessons, $offset, $limit);

if ($page > 1 && empty($lessons)) {
    exit; // Return empty response for subsequent pages if no more lessons
}

include __DIR__ . '/../../template/partials/learnAI/lessons_grid.php';
exit;
