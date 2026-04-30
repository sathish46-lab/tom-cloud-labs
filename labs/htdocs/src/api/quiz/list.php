<?php
require_once '../../load.php';
use TomLabs\Labs\Quiz;

header('Content-Type: text/html');

$subtopicId = $_GET['subtopic_id'] ?? null;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 8;
$difficulty = $_GET['difficulty'] ?? null;
if ($difficulty === 'all') $difficulty = null;

if (!$subtopicId) exit;

try {
    $quizzes = Quiz::getRecentForSubtopic($subtopicId, $limit, $offset, $difficulty);
    $user = Session::getUser();
    $userEmail = $user ? $user->getEmail() : null;

    foreach ($quizzes as $q) {
        $qDiff = strtolower($q['difficulty'] ?? 'normal');
        $qJolt = 2;
        if ($qDiff === 'easy') $qJolt = 1;
        elseif ($qDiff === 'hard') $qJolt = 5;
        $isAttempted = Quiz::hasAttempted($userEmail, $q['hash']);
        
        include __DIR__ . '/../../template/pages/quiz/_card.php';
    }
} catch (Exception $e) {
    echo "<!-- Error: " . $e->getMessage() . " -->";
}
