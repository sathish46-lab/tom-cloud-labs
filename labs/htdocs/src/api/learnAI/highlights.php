<?php
/**
 * Learn AI - Highlights API
 * GET: Returns saved highlights for a given chapter and user (user_id & email)
 * POST: Saves/updates user's highlights for a given chapter
 */
require_once __DIR__ . '/../../load.php';

header('Content-Type: application/json');

if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$user = Session::getUser();
$userId = (int)$user->getUserId();
$userEmail = $user->getEmail();

try {
    $db = DatabaseConnection::getDefaultDatabase();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $chapterId = $_GET['chapter_id'] ?? '';
        if (empty($chapterId)) {
            echo json_encode(['status' => 'success', 'chapter_id' => $chapterId, 'highlights' => [], 'drawings' => []]);
            exit;
        }

        $record = $db->ai_highlights->findOne([
            'chapter_id' => $chapterId,
            '$or' => [
                ['user_id' => $userId],
                ['email' => $userEmail]
            ]
        ]);

        $highlights = $record ? ($record['highlights'] ?? []) : [];
        $drawings = $record ? ($record['drawings'] ?? []) : [];
        if (is_object($highlights)) {
            $highlights = json_decode(json_encode($highlights), true);
        }
        if (is_object($drawings)) {
            $drawings = json_decode(json_encode($drawings), true);
        }

        echo json_encode(['status' => 'success', 'chapter_id' => $chapterId, 'highlights' => $highlights, 'drawings' => $drawings]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $chapterId = $input['chapter_id'] ?? '';
        $highlights = $input['highlights'] ?? [];
        $drawings = $input['drawings'] ?? [];

        if (empty($chapterId)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'chapter_id required']);
            exit;
        }

        $db->ai_highlights->updateOne(
            [
                'chapter_id' => $chapterId,
                '$or' => [
                    ['user_id' => $userId],
                    ['email' => $userEmail]
                ]
            ],
            [
                '$set' => [
                    'user_id' => $userId,
                    'email' => $userEmail,
                    'chapter_id' => $chapterId,
                    'highlights' => $highlights,
                    'drawings' => $drawings,
                    'updated_at' => time()
                ]
            ],
            ['upsert' => true]
        );

        echo json_encode(['status' => 'success', 'chapter_id' => $chapterId, 'highlights' => $highlights, 'drawings' => $drawings]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
