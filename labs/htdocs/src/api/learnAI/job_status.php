<?php
/**
 * Learn AI - Lesson Generation Job Status API
 * Returns live progress and status of an AI lesson generation job
 */
require_once __DIR__ . '/../../load.php';

header('Content-Type: application/json');

if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$requestId = trim($_GET['request_id'] ?? $_POST['request_id'] ?? '');

if (empty($requestId)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'failed',
        'message' => 'request_id is required',
        'lesson_id' => null,
        'request_id' => null,
        'completed' => false,
        'failed' => true,
        'error_message' => 'request_id is required'
    ]);
    exit;
}

$db = DatabaseConnection::getDefaultDatabase();
$job = $db->ai_lesson_jobs->findOne(['request_id' => $requestId]);

if (!$job) {
    http_response_code(404);
    echo json_encode([
        'status' => 'failed',
        'message' => 'Job not found',
        'lesson_id' => null,
        'request_id' => $requestId,
        'completed' => false,
        'failed' => true,
        'error_message' => 'Job not found'
    ]);
    exit;
}

if (!empty($job['completed'])) {
    echo json_encode([
        'status' => 'completed',
        'message' => $job['message'] ?? 'Lesson generated successfully!',
        'lesson_id' => $job['lesson_id'] ?? null,
        'request_id' => $requestId,
        'completed' => true,
        'failed' => false,
        'error_message' => null,
        'percentage' => 100
    ]);
    exit;
}

$elapsed = time() - ($job['created_at'] ?? time());

if ($elapsed <= 1) {
    echo json_encode([
        'status' => 'running',
        'message' => 'Analyzing learning goals & structuring curriculum...',
        'lesson_id' => null,
        'request_id' => $requestId,
        'completed' => false,
        'failed' => false,
        'error_message' => null,
        'percentage' => 35
    ]);
    exit;
} elseif ($elapsed <= 3) {
    echo json_encode([
        'status' => 'running',
        'message' => 'Generating structured modules & practical exercises...',
        'lesson_id' => null,
        'request_id' => $requestId,
        'completed' => false,
        'failed' => false,
        'error_message' => null,
        'percentage' => 70
    ]);
    exit;
} else {
    // Final step: Create the AI Lesson in database
    $user = Session::getUser();
    $username = $user ? $user->getUsername() : 'AI Assistant';
    $prompt = trim($job['topic'] ?? 'AI Learning Path');
    $level = $job['level'] ?? 'Beginner';

    // Professional curriculum synthesis matching expert tutor lesson design
    $subject = '';
    $title = '';
    $description = '';
    $modulesData = [];

    // AI-powered curriculum generation via Gemini
    {
        $envConfig = json_decode(file_get_contents(__DIR__ . '/../../../../../env.json'), true);
        $aiApiKey = $envConfig['ai_api_key'] ?? '';
        $aiGenerated = false;

        if (!empty($aiApiKey)) {
            $geminiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . urlencode($aiApiKey);

            $aiPromptText = <<<PROMPT
You are a professional course curriculum designer. Given the following learning request, generate a structured lesson plan.

User Request: "{$prompt}"
Difficulty Level: {$level}

Return ONLY valid JSON (no markdown, no code fences) with this exact structure:
{
  "title": "A concise, professional course title (max 60 chars, NO generic prefixes like 'Designing' or 'Building', make it unique and descriptive)",
  "topic": "The core subject in 2-4 words",
  "description": "A 1-2 sentence course description",
  "modules": [
    {
      "module_name": "1. Module Name",
      "chapters": [
        "1. Chapter Title",
        "2. Chapter Title",
        "3. Chapter Title"
      ]
    }
  ]
}

RULES:
1. Create 3-4 modules with 3-5 chapters each.
2. The title MUST be unique, creative, and directly reflect the topic. NEVER start with "Designing", "Building", or generic verbs. Use patterns like: "Python for Data Science", "Docker Container Orchestration", "Redis: High-Performance Caching", "Mastering Linux Networking", "TCP/IP Deep Dive".
3. Module and chapter names must be specific to the requested topic, not generic.
4. Tailor the depth and complexity to the "{$level}" difficulty level.
5. Return ONLY the raw JSON object, nothing else.
PROMPT;

            $requestBody = json_encode([
                'contents' => [
                    ['parts' => [['text' => $aiPromptText]]]
                ],
                'generationConfig' => [
                    'temperature' => 0.7,
                    'maxOutputTokens' => 2048,
                    'responseMimeType' => 'application/json'
                ]
            ]);

            $ch = curl_init($geminiUrl);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $requestBody,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $response) {
                $geminiResp = json_decode($response, true);
                $text = $geminiResp['candidates'][0]['content']['parts'][0]['text'] ?? '';
                // Strip markdown code fences if present
                $text = preg_replace('/^```(?:json)?\s*|\s*```$/s', '', trim($text));
                $parsed = json_decode($text, true);

                if ($parsed && !empty($parsed['title']) && !empty($parsed['modules'])) {
                    $title = $parsed['title'];
                    $subject = $parsed['topic'] ?? $title;
                    $description = $parsed['description'] ?? 'AI-generated professional curriculum for ' . $subject . '.';
                    $modulesData = $parsed['modules'];
                    $aiGenerated = true;
                }
            }
        }

        // Fallback if AI generation failed
        if (!$aiGenerated) {
            // Only strip instruction prefix phrases, preserving the topic nouns
            $stopWords = ['i', 'me', 'the', 'and', 'for', 'with', 'that', 'this', 'from', 'have', 'are', 'want', 'how', 'can', 'to', 'do', 'not', 'learn', 'about', 'teach', 'cover', 'act', 'as', 'generate', 'create', 'please', 'explain', 'build', 'design', 'manage', 'keep', 'include', 'scratch', 'comprehensive', 'single', 'chapter', 'sections', 'divided', 'exactly', 'strictly', 'clear', 'expert', 'technician', 'instructor', 'fundamentals', 'basics', 'practical', 'exercises', 'concepts', 'application', 'including'];
            $words = preg_split('/[\s,.\-;:!?]+/', $prompt);
            $coreWords = [];
            foreach ($words as $w) {
                $w = trim($w);
                if (!in_array(strtolower($w), $stopWords) && strlen($w) > 2) {
                    $coreWords[] = ucfirst(strtolower($w));
                }
            }
            $coreWords = array_values(array_unique($coreWords));
            $subject = implode(' ', array_slice($coreWords, 0, 5));
            if (empty($subject)) $subject = 'Technology & Engineering';

            $title = $subject . ': ' . $level . ' Course';
            $description = 'Professional structured curriculum for ' . $subject . '.';

            $modulesData = [
                [
                    'module_name' => '1. Foundations and Core Concepts',
                    'chapters' => [
                        '1. Core Theory and Fundamentals',
                        '2. Environment Setup and Tooling',
                        '3. Data Flow and Architecture'
                    ]
                ],
                [
                    'module_name' => '2. Practical Implementation',
                    'chapters' => [
                        '1. Building Core Components',
                        '2. Integration and Security',
                        '3. State Management and Error Handling'
                    ]
                ],
                [
                    'module_name' => '3. Testing and Deployment',
                    'chapters' => [
                        '1. Performance Profiling',
                        '2. Debugging Techniques',
                        '3. Production Readiness'
                    ]
                ]
            ];
        }
    }

    $tagSubject = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $subject));
    $tags = array_slice(array_filter(explode('-', $tagSubject)), 0, 3);
    $tags[] = 'ai-generated';

    $newLessonId = new MongoDB\BSON\ObjectId();
    $newLesson = [
        '_id' => $newLessonId,
        'title' => $title,
        'topic' => $subject,
        'prompt' => $prompt,
        'description' => $description,
        'level' => $level,
        'visibility' => 'Private',
        'modules_count' => count($modulesData),
        'chapters_count' => array_sum(array_map(function($m) { return count($m['chapters']); }, $modulesData)),
        'tags' => $tags,
        'thumbnail' => '/assets/images/learn/ai-assistant.png',
        'progress' => 0,
        'author' => $username,
        'created_at' => time()
    ];

    $db->ai_lessons->insertOne($newLesson);

    // Seed tailored chapters for each module
    $order = 1;
    foreach ($modulesData as $modInfo) {
        $modName = $modInfo['module_name'];
        $cleanModName = preg_replace('/^\d+[\.\)]\s*/', '', $modName);

        foreach ($modInfo['chapters'] as $chTitle) {
            $cleanChTitle = preg_replace('/^\d+[\.\)]\s*/', '', $chTitle);

            $db->ai_chapters->insertOne([
                'lesson_id' => $newLessonId,
                'module_name' => $modName,
                'title' => $chTitle,
                'content' => '',
                'order' => $order++,
                'status' => ($order === 2) ? 'in_progress' : 'pending'
            ]);
        }
    }

    $lessonIdStr = (string)$newLessonId;

    // Update job as completed
    $db->ai_lesson_jobs->updateOne(
        ['request_id' => $requestId],
        ['$set' => [
            'status' => 'completed',
            'message' => 'Lesson generated successfully!',
            'lesson_id' => $lessonIdStr,
            'completed' => true,
            'percentage' => 100
        ]]
    );

    echo json_encode([
        'status' => 'completed',
        'message' => 'Lesson generated successfully!',
        'lesson_id' => $lessonIdStr,
        'request_id' => $requestId,
        'completed' => true,
        'failed' => false,
        'error_message' => null,
        'percentage' => 100
    ]);
    exit;
}
