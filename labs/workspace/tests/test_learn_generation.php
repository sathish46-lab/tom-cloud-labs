<?php
/**
 * Learn AI - Lesson generation pipeline (generate_lesson -> RabbitMQ -> worker -> job_status reader).
 *
 * Covers: auth boundary, bad input, queue contract (job doc), job_status reader
 * (running/completed/failed synthetic docs), and a real end-to-end generation
 * through the live ai-worker (skipped if the worker does not complete in time).
 */
require_once __DIR__ . '/bootstrap.php';

$email = 'learn_gen_test@test.tomweb.in';
$token = create_test_user($email, 'user');
$cookie = 'session_token=' . $token;
$db = DatabaseConnection::getDefaultDatabase();

$createdRequestIds = [];
$createdLessonOids = [];
$createdTopics = [];

// ---------------------------------------------------------------------------
// Auth boundary
// ---------------------------------------------------------------------------
$resp = http_request('POST', '/api/learnAI/generate_lesson', [
    'headers' => ['Content-Type: application/json'],
    'body' => json_encode(['topic' => 'auth boundary probe']),
]);
test('generate_lesson rejects unauthenticated requests',
    in_array($resp['status'], [401, 403, 302]),
    'status=' . $resp['status']);

$resp = http_request('GET', '/api/learnAI/job_status?request_id=abcdef', [
    'headers' => ['Content-Type: application/json'],
]);
test('job_status rejects unauthenticated requests',
    in_array($resp['status'], [401, 403, 302]),
    'status=' . $resp['status']);

// ---------------------------------------------------------------------------
// Bad input
// ---------------------------------------------------------------------------
$resp = http_request('POST', '/api/learnAI/generate_lesson', [
    'headers' => ['Content-Type: application/json'],
    'body' => json_encode(['level' => 'Beginner']),
    'cookie' => $cookie,
]);
test('generate_lesson rejects missing topic with 400',
    $resp['status'] === 400 && (($resp['body_json']['failed'] ?? false) === true),
    'status=' . $resp['status'] . ' body=' . substr($resp['body'], 0, 120));

$resp = http_request('GET', '/api/learnAI/job_status', [
    'headers' => ['Content-Type: application/json'],
    'cookie' => $cookie,
]);
test('job_status rejects missing request_id with 400',
    $resp['status'] === 400,
    'status=' . $resp['status']);

$resp = http_request('GET', '/api/learnAI/job_status?request_id=' . str_repeat('a', 40), [
    'headers' => ['Content-Type: application/json'],
    'cookie' => $cookie,
]);
test('job_status returns 404 for unknown job',
    $resp['status'] === 404 && (($resp['body_json']['failed'] ?? false) === true),
    'status=' . $resp['status']);

// ---------------------------------------------------------------------------
// Happy path: enqueue contract (job doc written, request_id returned)
// ---------------------------------------------------------------------------
$topic = 'learn generation contract topic ' . uniqid();
$createdTopics[] = $topic;
$resp = http_request('POST', '/api/learnAI/generate_lesson', [
    'headers' => ['Content-Type: application/json'],
    'body' => json_encode(['topic' => $topic, 'level' => 'Beginner']),
    'cookie' => $cookie,
]);
$j = $resp['body_json'] ?? [];
$realRequestId = $j['request_id'] ?? '';
if ($realRequestId !== '') {
    $createdRequestIds[] = $realRequestId;
}
test('generate_lesson returns running status with 40-char request_id',
    $resp['status'] === 200 && ($j['status'] ?? '') === 'running' && preg_match('/^[0-9a-f]{40}$/', $realRequestId) === 1,
    'status=' . $resp['status'] . ' request_id=' . $realRequestId);

$jobDoc = $realRequestId !== '' ? $db->ai_lesson_jobs->findOne(['request_id' => $realRequestId]) : null;
test('generate_lesson persists job doc with topic/user/username',
    $jobDoc !== null
        && ($jobDoc['topic'] ?? '') === $topic
        && ($jobDoc['level'] ?? '') === 'Beginner'
        && (string)($jobDoc['user_id'] ?? '') !== ''
        && ($jobDoc['username'] ?? '') === explode('@', $email)[0],
    'doc=' . ($jobDoc ? json_encode(['topic' => $jobDoc['topic'] ?? null, 'username' => $jobDoc['username'] ?? null]) : 'null'));

// ---------------------------------------------------------------------------
// job_status reader — synthetic running / completed / failed docs
// ---------------------------------------------------------------------------
$runningId = 'test_running_' . uniqid();
$createdRequestIds[] = $runningId;
$db->ai_lesson_jobs->insertOne([
    'request_id' => $runningId, 'user_id' => 1, 'username' => 'learn_gen_test',
    'topic' => 'synthetic', 'level' => 'Beginner', 'status' => 'running',
    'message' => 'Custom running message', 'percentage' => 42,
    'completed' => false, 'failed' => false, 'created_at' => time(),
]);
$resp = http_request('GET', '/api/learnAI/job_status?request_id=' . $runningId, [
    'headers' => ['Content-Type: application/json'],
    'cookie' => $cookie,
]);
$j = $resp['body_json'] ?? [];
test('job_status running reads percentage/message from job doc',
    $resp['status'] === 200 && ($j['status'] ?? '') === 'running'
        && ($j['percentage'] ?? 0) === 42 && ($j['message'] ?? '') === 'Custom running message'
        && ($j['completed'] ?? true) === false && ($j['failed'] ?? true) === false,
    'body=' . substr($resp['body'], 0, 200));

$completedId = 'test_completed_' . uniqid();
$createdRequestIds[] = $completedId;
$db->ai_lesson_jobs->insertOne([
    'request_id' => $completedId, 'user_id' => 1, 'username' => 'learn_gen_test',
    'topic' => 'synthetic', 'level' => 'Beginner', 'status' => 'completed',
    'message' => 'Lesson generated successfully!', 'lesson_id' => '656f000000000000000000aa',
    'percentage' => 100, 'completed' => true, 'failed' => false, 'created_at' => time(),
]);
$resp = http_request('GET', '/api/learnAI/job_status?request_id=' . $completedId, [
    'headers' => ['Content-Type: application/json'],
    'cookie' => $cookie,
]);
$j = $resp['body_json'] ?? [];
test('job_status completed returns lesson_id and completed=true',
    ($j['status'] ?? '') === 'completed' && ($j['completed'] ?? false) === true
        && ($j['lesson_id'] ?? '') === '656f000000000000000000aa' && ($j['failed'] ?? true) === false,
    'body=' . substr($resp['body'], 0, 200));

$failedId = 'test_failed_' . uniqid();
$createdRequestIds[] = $failedId;
$db->ai_lesson_jobs->insertOne([
    'request_id' => $failedId, 'user_id' => 1, 'username' => 'learn_gen_test',
    'topic' => 'synthetic', 'level' => 'Beginner', 'status' => 'failed',
    'message' => 'boom', 'error_message' => 'boom exploded',
    'percentage' => 0, 'completed' => false, 'failed' => true, 'created_at' => time(),
]);
$resp = http_request('GET', '/api/learnAI/job_status?request_id=' . $failedId, [
    'headers' => ['Content-Type: application/json'],
    'cookie' => $cookie,
]);
$j = $resp['body_json'] ?? [];
test('job_status failed surfaces error_message',
    ($j['status'] ?? '') === 'failed' && ($j['failed'] ?? false) === true
        && ($j['error_message'] ?? '') === 'boom exploded',
    'body=' . substr($resp['body'], 0, 200));

// ---------------------------------------------------------------------------
// End-to-end: real generation via the live worker (labsctl curriculum)
// ---------------------------------------------------------------------------
$e2eTopic = 'learn generation e2e topic ' . uniqid();
$createdTopics[] = $e2eTopic;
$resp = http_request('POST', '/api/learnAI/generate_lesson', [
    'headers' => ['Content-Type: application/json'],
    'body' => json_encode(['topic' => $e2eTopic, 'level' => 'Beginner']),
    'cookie' => $cookie,
]);
$e2eId = ($resp['body_json']['request_id'] ?? '') ?: null;
if ($e2eId !== null) {
    $createdRequestIds[] = $e2eId;
}
test('e2e generate_lesson accepted', $e2eId !== null && preg_match('/^[0-9a-f]{40}$/', $e2eId) === 1, 'id=' . var_export($e2eId, true));

$e2eResult = null; // 'completed' | 'failed' | null (timeout)
$e2eError = null;
$e2eLessonId = null;
if ($e2eId !== null) {
    for ($i = 0; $i < 30; $i++) {
        sleep(3);
        $poll = http_request('GET', '/api/learnAI/job_status?request_id=' . $e2eId, [
            'headers' => ['Content-Type: application/json'],
            'cookie' => $cookie,
        ]);
        $pj = $poll['body_json'] ?? [];
        if (!empty($pj['completed'])) {
            $e2eResult = 'completed';
            $e2eLessonId = $pj['lesson_id'] ?? null;
            break;
        }
        if (!empty($pj['failed'])) {
            $e2eResult = 'failed';
            $e2eError = $pj['error_message'] ?? 'unknown';
            break;
        }
    }
}

if ($e2eResult === null) {
    skip('e2e lesson generation completes via worker', 'worker did not complete the job within 90s');
} else {
    test('e2e lesson generation completes via worker', $e2eResult === 'completed', 'error=' . var_export($e2eError, true));
}

if ($e2eResult === 'completed' && $e2eLessonId) {
    $lessonOid = null;
    try {
        $lessonOid = new MongoDB\BSON\ObjectId((string) $e2eLessonId);
        $createdLessonOids[] = $lessonOid;
    } catch (Exception $e) {
    }
    $lesson = $lessonOid ? $db->ai_lessons->findOne(['_id' => $lessonOid]) : null;
    test('e2e lesson doc written with prompt/level/author/visibility',
        $lesson !== null
            && ($lesson['prompt'] ?? '') === $e2eTopic
            && ($lesson['level'] ?? '') === 'Beginner'
            && ($lesson['author'] ?? '') === explode('@', $email)[0]
            && ($lesson['visibility'] ?? '') === 'Private'
            && !empty($lesson['title']) && !empty($lesson['modules_count']),
        'lesson=' . ($lesson ? json_encode(['title' => $lesson['title'] ?? null]) : 'null'));

    $chapters = $lessonOid ? $db->ai_chapters->find(['lesson_id' => $lessonOid])->toArray() : [];
    test('e2e chapters seeded with first chapter in_progress',
        count($chapters) > 0
            && ($chapters[0]['status'] ?? '') === 'in_progress'
            && ($chapters[0]['order'] ?? 0) == 1
            && ($chapters[0]['title'] ?? '') !== '',
        'chapters=' . count($chapters));
}

// ---------------------------------------------------------------------------
// E. Content partial resume (content_partial API + live generation lifecycle)
//     Auth model mirrors content_fetch.php (requireAuth only) — multi-tenant
//     ownership is not re-checked here for the same reason it isn't there.
// ---------------------------------------------------------------------------
$resp = http_request('GET', '/api/learnAI/content_partial?chapter_id=x');
test('content_partial rejects unauthenticated requests',
    in_array($resp['status'], [401, 403, 302]),
    'status=' . $resp['status']);

$resp = http_request('GET', '/api/learnAI/content_partial', [
    'headers' => ['Content-Type: application/json'],
    'cookie' => $cookie,
]);
test('content_partial rejects missing chapter_id with 400',
    $resp['status'] === 400,
    'status=' . $resp['status']);

$resp = http_request('GET', '/api/learnAI/content_partial?chapter_id=notanobjectid', [
    'headers' => ['Content-Type: application/json'],
    'cookie' => $cookie,
]);
test('content_partial rejects invalid chapter_id with 400',
    $resp['status'] === 400,
    'status=' . $resp['status']);

$missingOid = new MongoDB\BSON\ObjectId();
$resp = http_request('GET', '/api/learnAI/content_partial?chapter_id=' . (string) $missingOid, [
    'headers' => ['Content-Type: application/json'],
    'cookie' => $cookie,
]);
test('content_partial returns 404 for unknown chapter',
    $resp['status'] === 404,
    'status=' . $resp['status']);

// Standalone chapter (no lesson needed — worker falls back to defaults)
$eChapterOids = [];
$eChapterOid = new MongoDB\BSON\ObjectId();
$eChapterOids[] = $eChapterOid;
$db->ai_chapters->insertOne([
    '_id' => $eChapterOid,
    'lesson_id' => new MongoDB\BSON\ObjectId(),
    'module_name' => '1. Test Module',
    'title' => '1. Resume Test Chapter',
    'content' => '',
    'order' => 1,
    'status' => 'pending',
]);

$resp = http_request('GET', '/api/learnAI/content_partial?chapter_id=' . (string) $eChapterOid, [
    'headers' => ['Content-Type: application/json'],
    'cookie' => $cookie,
]);
$j = $resp['body_json'] ?? [];
test('content_partial reports none when no generation is in flight',
    ($j['status'] ?? '') === 'none' && ($j['streaming'] ?? true) === false && ($j['partial'] ?? 'x') === '',
    'body=' . substr($resp['body'], 0, 200));

$db->ai_chapters->updateOne(['_id' => $eChapterOid], ['$set' => [
    'content_partial' => 'Hello **resumed** world',
    'content_partial_session' => 'content_sess_partialseed',
    'content_partial_seq' => 7,
    'content_partial_status' => 'streaming',
    'content_partial_at' => time(),
]]);
$resp = http_request('GET', '/api/learnAI/content_partial?chapter_id=' . (string) $eChapterOid, [
    'headers' => ['Content-Type: application/json'],
    'cookie' => $cookie,
]);
$j = $resp['body_json'] ?? [];
test('content_partial streams a fresh persisted partial',
    ($j['status'] ?? '') === 'streaming' && ($j['streaming'] ?? false) === true
        && ($j['session_id'] ?? '') === 'content_sess_partialseed'
        && ($j['seq'] ?? 0) === 7 && ($j['partial'] ?? '') === 'Hello **resumed** world',
    'body=' . substr($resp['body'], 0, 250));

$db->ai_chapters->updateOne(['_id' => $eChapterOid], ['$set' => ['content_partial_at' => time() - 300]]);
$resp = http_request('GET', '/api/learnAI/content_partial?chapter_id=' . (string) $eChapterOid, [
    'headers' => ['Content-Type: application/json'],
    'cookie' => $cookie,
]);
$j = $resp['body_json'] ?? [];
test('content_partial marks stale streams as interrupted',
    ($j['status'] ?? '') === 'interrupted' && ($j['streaming'] ?? true) === false
        && ($j['partial'] ?? '') === 'Hello **resumed** world',
    'body=' . substr($resp['body'], 0, 250));

// Live E2E: trigger real content generation, observe partial persist + final save
$db->ai_chapters->updateOne(['_id' => $eChapterOid], ['$unset' => [
    'content_partial' => '', 'content_partial_session' => '', 'content_partial_seq' => '',
    'content_partial_status' => '', 'content_partial_at' => '',
]]);
$eSession = 'content_sess_e2e_' . uniqid();
$resp = http_request('POST', '/api/learnAI/content_generate', [
    'headers' => ['Content-Type: application/json'],
    'body' => json_encode([
        'chapter_id' => (string) $eChapterOid,
        'session_id' => $eSession,
        'message_id' => 'content_msg_e2e_' . uniqid(),
    ]),
    'cookie' => $cookie,
]);
test('content_generate queues a stream job',
    $resp['status'] === 200 && (($resp['body_json']['status'] ?? '') === 'success'),
    'status=' . $resp['status'] . ' body=' . substr($resp['body'], 0, 200));

$sawPartial = false;
$genDone = false;
for ($i = 0; $i < 300; $i++) {
    $doc = $db->ai_chapters->findOne(['_id' => $eChapterOid]);
    if ($doc !== null) {
        if (!empty($doc['content_partial']) && ($doc['content_partial_status'] ?? '') === 'streaming') {
            $sawPartial = true;
        }
        if (trim($doc['content'] ?? '') !== '' && trim($doc['content_html'] ?? '') !== ''
                && !isset($doc['content_partial'])) {
            $genDone = true;
            break;
        }
    }
    usleep(200000);
}
test('worker persists partial content while streaming', $sawPartial, 'sawPartial=' . var_export($sawPartial, true));
test('content generation completes and saves chapter content', $genDone, 'done=' . var_export($genDone, true));

$resp = http_request('GET', '/api/learnAI/content_partial?chapter_id=' . (string) $eChapterOid, [
    'headers' => ['Content-Type: application/json'],
    'cookie' => $cookie,
]);
$j = $resp['body_json'] ?? [];
test('content_partial cleared after generation completes',
    ($j['status'] ?? '') === 'none',
    'body=' . substr($resp['body'], 0, 200));

// ---------------------------------------------------------------------------
// Cleanup
// ---------------------------------------------------------------------------
try {
    if ($createdRequestIds) {
        $db->ai_lesson_jobs->deleteMany(['request_id' => ['$in' => $createdRequestIds]]);
    }
    // Both the contract and e2e POSTs are consumed by the worker — collect any
    // lesson it created for our topics (the contract-path one is not tracked
    // via a completed poll) together with the e2e lesson id.
    $lessonOids = $createdLessonOids;
    if ($createdTopics) {
        foreach ($db->ai_lessons->find(['prompt' => ['$in' => $createdTopics]])->toArray() as $l) {
            $lessonOids[] = $l['_id'];
        }
    }
    if ($lessonOids) {
        $db->ai_chapters->deleteMany(['lesson_id' => ['$in' => $lessonOids]]);
        $db->ai_lessons->deleteMany(['_id' => ['$in' => $lessonOids]]);
    }
    if ($eChapterOids) {
        $db->ai_chapters->deleteMany(['_id' => ['$in' => $eChapterOids]]);
    }
} catch (Exception $e) {
    echo "Cleanup warning: " . $e->getMessage() . "\n";
}
cleanup_test_user($email);

test_summary();
