<?php
require_once '../../load.php';

use TomLabs\Labs\Quiz;

header('Content-Type: application/json');

if (Session::getUser()) {
    $userEmail = Session::getUser()->getEmail();
    $quizHash = $_POST['hash'] ?? null;

    if ($quizHash && $userEmail) {
        Quiz::recordView($userEmail, $quizHash);
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Missing hash']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
}
