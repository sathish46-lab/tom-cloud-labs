<?php
/**
 * Learn AI - Highlights API
 * GET: Returns saved highlights for a given chapter and user (user_id & email)
 * POST: Saves/updates user's highlights for a given chapter
 */
require_once __DIR__ . '/../../load.php';

$user = AuthMiddleware::requireAuth();

$userId = (int)$user->getUserId();
$userEmail = $user->getEmail();

try {
    $db = DatabaseConnection::getDefaultDatabase();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $chapterId = $_GET['chapter_id'] ?? '';
        if (empty($chapterId)) {
api_json(['status' => 'success', 'chapter_id' => $chapterId, 'highlights' => [], 'drawings' => []], 200);
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

api_json(['status' => 'success', 'chapter_id' => $chapterId, 'highlights' => $highlights, 'drawings' => $drawings], 200);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $chapterId = $input['chapter_id'] ?? '';
        $highlights = $input['highlights'] ?? [];
        $drawings = $input['drawings'] ?? [];

        if (empty($chapterId)) {
api_json(['status' => 'error', 'message' => 'chapter_id required'], 400);
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

api_json(['status' => 'success', 'chapter_id' => $chapterId, 'highlights' => $highlights, 'drawings' => $drawings], 200);
    }

    api_json(['status' => 'error', 'message' => 'Method not allowed'], 405);
} catch (Exception $e) {
    api_json(['status' => 'error', 'message' => errors_from_exception($e, 'learnAI/highlights')['error']], 500);
}
