<?php
/**
 * Shared bootstrap for ALL challenge inner pages.
 * URL Pattern: /challenges/{tab}/{hash-or-slug}
 */
require_once __DIR__ . '/../../src/load.php';

if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    header("Location: /signin"); exit;
}

$user = Session::getUser();
$userEmail = strtolower(trim($user->getEmail())); // Normalize email

// ── Parse URL segments ────────────────────────────────────────────
$uri   = trim(strtok($_SERVER['REQUEST_URI'], '?'), '/');
$parts = explode('/', $uri);

$activeTab = $parts[1] ?? 'dashboard';
$segment   = $parts[2] ?? '';

if (empty($segment)) {
    header("Location: /challenges"); exit;
}

$db = DatabaseConnection::getDefaultDatabase();

// ── Resolve hash vs slug ──────────────────────────────────────────
if (preg_match('/^[a-f0-9]{32}$/', $segment)) {
    // 1. It's a HASH (Private Session URL)
    $instanceHash = $segment;
    $instanceData = $db->challenge_instances->findOne(['instance_hash' => $instanceHash]);
    
    $labId = $instanceData['lab_id'] ?? null;
    
    // If not in instance data, reconstruct from known labs
    if (!$labId) {
        // Dynamically load known lab IDs (Slugs) from the challenge configuration keys
        $readmesConfig = require __DIR__ . '/../../src/config/challenge_readmes.php';
        $knownLabs = array_keys($readmesConfig);
        
        // Also check DB for any other challenges
        $challengesCursor = $db->challenges->find([]);
        foreach ($challengesCursor as $c) {
            if (!empty($c['lab_id']) && !in_array($c['lab_id'], $knownLabs)) {
                $knownLabs[] = $c['lab_id'];
            }
        }

        foreach ($knownLabs as $slug) {
            if (md5($userEmail . $slug) === $instanceHash) {
                $labId = $slug;
                break;
            }
        }
    }
    
    // Final fallback
    $labId = $labId ?? Session::get('challenge_lab_id') ?? 'unknown';

    if (!$instanceData) {
        $instanceData = [
            'instance_hash' => $instanceHash,
            'lab_id'        => $labId,
            'status'        => 'not_deployed',
        ];
    }
} else {
    // 2. It's a SLUG (Shareable URL)
    $labId        = $segment;
    $instanceHash = md5($userEmail . $labId);
    
    // Store slug in Session
    Session::set('challenge_lab_id', $labId);

    // Redirect to canonical hash URL
    header("Location: /challenges/{$activeTab}/{$instanceHash}");
    exit;
}

$durationMinutes = 15; // default
$challengePoints = 2232; // default
$jsonMeta = [];

// Prevent unauthorized manual URL navigation to locked/unreleased challenges
$jsonPath = __DIR__ . '/../../src/config/challenges.json';
if (file_exists($jsonPath)) {
    $challengesList = json_decode(file_get_contents($jsonPath), true) ?? [];
    foreach ($challengesList as $cItem) {
        if ($cItem['lab_id'] === $labId) {
            $jsonMeta = $cItem;
            $challengePoints = isset($cItem['points']) ? (int)$cItem['points'] : 2232;
            
            // Determine default duration based on difficulty tags
            $difficulty = 'easy';
            if (isset($cItem['tags']) && is_array($cItem['tags'])) {
                foreach ($cItem['tags'] as $tag) {
                    $tText = strtolower($tag['text'] ?? '');
                    if ($tText === 'easy' || $tText === 'medium' || $tText === 'hard') {
                        $difficulty = $tText;
                        break;
                    }
                }
            }
            $durationMap = [
                'easy' => 15,
                'medium' => 30,
                'hard' => 45
            ];
            $durationMinutes = isset($cItem['duration']) ? (int)$cItem['duration'] : ($durationMap[$difficulty] ?? 15);

            $releaseDateStr = isset($cItem['release_date']) ? $cItem['release_date'] : '';
            $releaseTimeStr = isset($cItem['release_time']) ? $cItem['release_time'] : '12:00 AM';
            $releaseTime = strtotime($releaseDateStr . ' ' . $releaseTimeStr);
            if ($releaseTime === false) {
                $releaseTime = 0;
            }
            
            $isReleased = ($releaseTime <= time());
            $isChallengeActive = isset($cItem['challenge']) ? (bool)$cItem['challenge'] : true;
            $isUnlocked = ($isReleased && $isChallengeActive);
            
            if (!$isUnlocked) {
                header("Location: /challenges");
                exit;
            }
            break;
        }
    }
}

$status    = $instanceData['status'] ?? 'not_deployed';
$createdAt = $instanceData['created_at'] ?? time();
// Use the DB's stored expires_at (set by the deployer) as the single source of truth
$expiresAt = $instanceData['expires_at'] ?? ($createdAt + ($durationMinutes * 60));
if ($status === 'running' && time() >= $expiresAt) {
    $status = 'stopped';
    // Proactively update DB so the state is consistent even before the reaper runs
    $db->challenge_instances->updateOne(
        ['instance_hash' => $instanceHash],
        ['$set' => ['status' => 'stopped', 'mission_started' => false, 'updated_at' => time()]]
    );
}
$isRunning = ($status === 'running' || $status === 'completed');

// ── Metadata Resolution ───────────────────────────────────────────
$challengeMeta = $db->challenges->findOne(['lab_id' => $labId]) ?? [];

// Load extra task metadata from challenge_tasks.json if exists
$challengeTasksMeta = [];
$tasksJsonPath = __DIR__ . '/../../src/config/challenge_tasks.json';
if (file_exists($tasksJsonPath)) {
    $tasksConfig = json_decode(file_get_contents($tasksJsonPath), true) ?? [];
    $challengeTasksMeta = $tasksConfig[$labId][0] ?? [];
}

// Store in Session for template access
Session::set('challenge_instance_hash', $instanceHash);
Session::set('challenge_lab_id',        $labId);
Session::set('challenge_status',        $status);
Session::set('challenge_duration',      $durationMinutes);
Session::set('challenge_title',         $challengeMeta['title']        ?? $jsonMeta['name'] ?? $challengeTasksMeta['title'] ?? ucwords(str_replace('-', ' ', $labId)));
Session::set('challenge_desc',          $challengeMeta['description']  ?? $challengeTasksMeta['description'] ?? 'Engage in real-world hacking scenarios and penetration testing.');
Session::set('challenge_image',         $challengeMeta['image_url']    ?? $jsonMeta['image'] ?? '/assets/img/challenges/shadow.png');
Session::set('challenge_max_zeal',      $challengePoints);
Session::set('challenge_tags',          $challengeMeta['tags']         ?? $jsonMeta['tags'] ?? ['team', 'beta', 'not running']);
Session::set('challenge_event_name',    $challengeMeta['event_name']   ?? $jsonMeta['ribbon_text2'] ?? 'Yukthi Finale');
Session::set('challenge_is_ended',      (bool)($challengeMeta['is_ended']   ?? true));
Session::set('challenge_is_retired',    (bool)($challengeMeta['is_retired']  ?? false));
Session::set('challenge_total_tasks',   (int)($challengeMeta['total_tasks']  ?? 1));
