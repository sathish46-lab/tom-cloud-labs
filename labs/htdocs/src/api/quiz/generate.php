<?php
/**
 * API: Trigger AI Quiz Generation
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../../load.php';
use TomLabs\Labs\Quiz;

// Auth Protection
if (!Session::getAuthStatus()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$topicId = $_GET['topic'] ?? null;
$subtopicId = $_GET['subtopic'] ?? null;
$diff = $_GET['diff'] ?? 'normal';

if (!$topicId || !$subtopicId) {
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

try {
    $response = Quiz::startGeneration($topicId, $subtopicId, $diff);
    echo json_encode($response);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
