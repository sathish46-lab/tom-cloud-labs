<?php
require_once '../../load.php';

use TomLabs\Labs\Quiz;

$user = AuthMiddleware::requireAuth();

$userEmail = $user->getEmail();
$quizHash = $_POST['hash'] ?? null;

if ($quizHash && $userEmail) {
    Quiz::recordView($userEmail, $quizHash);
    api_json(['status' => 'success'], 200);
} else {
    api_json(['status' => 'error', 'message' => 'Missing hash'], 400);
}
