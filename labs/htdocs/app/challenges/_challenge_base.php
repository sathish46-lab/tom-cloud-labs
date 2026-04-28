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
        // List of known lab IDs (Slugs) to check against
        $knownLabs = ['zombie-breakout', 'shadow-partner', 'backrooms', 'block-with-buster', 'operation-warehouse', 'proxy-pipeline'];
        
        // Also check DB for any other challenges
        $challengesCursor = $db->challenges->find([]);
        foreach ($challengesCursor as $c) {
            if (!in_array($c['lab_id'], $knownLabs)) {
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

$status    = $instanceData['status'] ?? 'not_deployed';
$isRunning = ($status === 'running');

// ── Metadata Resolution ───────────────────────────────────────────
$challengeMeta = $db->challenges->findOne(['lab_id' => $labId]) ?? [];

// Store in Session for template access
Session::set('challenge_instance_hash', $instanceHash);
Session::set('challenge_lab_id',        $labId);
Session::set('challenge_status',        $status);
Session::set('challenge_title',         $challengeMeta['title']        ?? ucwords(str_replace('-', ' ', $labId)));
Session::set('challenge_desc',          $challengeMeta['description']  ?? 'Engage in real-world hacking scenarios and penetration testing.');
Session::set('challenge_image',         $challengeMeta['image_url']    ?? '/assets/img/challenges/shadow.png');
Session::set('challenge_max_zeal',      (int)($challengeMeta['max_zeal'] ?? 2232));
Session::set('challenge_tags',          $challengeMeta['tags']         ?? ['team', 'beta', 'not running']);
Session::set('challenge_event_name',    $challengeMeta['event_name']   ?? 'Yukthi Finale');
Session::set('challenge_is_ended',      (bool)($challengeMeta['is_ended']   ?? true));
Session::set('challenge_is_retired',    (bool)($challengeMeta['is_retired']  ?? false));
Session::set('challenge_total_tasks',   (int)($challengeMeta['total_tasks']  ?? 1));
