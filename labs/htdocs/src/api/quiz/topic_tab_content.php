<?php
require_once '../../load.php';
use TomLabs\Labs\Quiz;

$topicId = $_GET['topic_id'] ?? null;
$tab = $_GET['tab'] ?? 'topics';
$user = Session::getUser();
$userEmail = $user ? $user->getEmail() : null;

if (!$topicId) exit;

switch ($tab) {
    case 'trending':
        $quizzes = Quiz::getTrendingForTopic($topicId);
        renderQuizGrid($quizzes, $userEmail);
        break;
    case 'completed':
        $quizzes = Quiz::getCompletedForUser($userEmail, $topicId);
        renderQuizGrid($quizzes, $userEmail);
        break;
    case 'leaderboard':
        $leaderboard = Quiz::getLeaderboardForTopic($topicId);
        renderLeaderboard($leaderboard);
        break;
    default:
        echo '<div class="col-12 text-center py-5"><h5 class="text-body-secondary opacity-50">Select a valid tab.</h5></div>';
        break;
}

function renderQuizGrid($quizzes, $userEmail) {
    if (empty($quizzes)) {
        echo '<div class="col-12 text-center py-5 animate__animated animate__fadeIn">
                <div class="empty-state-card opacity-50">
                    <i class="bx bx-folder-open display-1 mb-3"></i>
                    <h5 class="text-body-secondary">No quizzes found in this category.</h5>
                </div>
              </div>';
        return;
    }
    echo '<div class="row row-cols-1 row-cols-md-2 row-cols-xl-4 g-4 quiz-masonry-row animate__animated animate__fadeIn">';
    foreach ($quizzes as $q) {
        $qDiff = strtolower($q['difficulty'] ?? 'normal');
        $qJolt = ($qDiff === 'easy') ? 1 : (($qDiff === 'hard') ? 5 : 2);
        $isAttempted = $userEmail ? Quiz::hasAttempted($userEmail, $q['hash']) : false;
        include __DIR__ . '/../../template/pages/quiz/_card.php';
    }
    echo '</div>';
}

function renderLeaderboard($leaderboard) {
    include __DIR__ . '/../../template/pages/quiz/_leaderboard.php';
}
