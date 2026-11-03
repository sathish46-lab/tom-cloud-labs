<?php
/**
 * News fetch API — POST /api/news/fetch
 * Admin-only: triggers fetch+store for all configured RSS/JSON sources.
 * Requires valid CSRF token (X-CSRF-Token header or _csrf_token POST field).
 *
 * Query params:
 *   ?force=1   - Bypass cache and fetch fresh data from all sources
 *
 * Returns per-source counts + errors (non-fatal — one failing source never kills the whole run).
 */

require_once __DIR__ . '/../../../src/load.php';

// Auth + CSRF gate
$auth = AuthMiddleware::requireAdmin(); // 401 if not superuser, 403 if not admin
$user = $auth;

// CSRF validation
AuthMiddleware::requireCsrf(); // 403 if invalid

// DB connection for fetch log
$db = DatabaseConnection::getDefaultDatabase();

// Check for force parameter to bypass cache
$force = !empty($_POST['force']) || !empty($_GET['force']);

// Configure sources — keyed by unique name. Register them here (same names used by NewsFetcher).
// These will appear in the /news UI source filter.
$configSources = [
    'thehackernews' => 'https://feeds.feedburner.com/TheHackersNews',
    'bleepingcomputer' => 'https://www.bleepingcomputer.com/feed/',
    'krebsonsecurity' => 'https://krebsonsecurity.com/feed/',
    'securityweek' => 'https://www.securityweek.com/feed/',
    'cybersecuritynews' => 'https://cybersecuritynews.com/feed/',
    'securityaffairs' => 'https://securityaffairs.com/feed/',
    'cybersecuritydive' => 'https://www.cybersecuritydive.com/rss/',
    'theverge-security' => 'https://www.theverge.com/rss/cyber-security/index.xml',
    'nvd-cve' => 'https://services.nvd.nist.gov/rest/json/cves/2.0?resultsPerPage=20',
    'cisa-kev' => 'https://www.cisa.gov/sites/default/files/feeds/known_exploited_vulnerabilities.json',
    'osv' => 'https://api.osv.dev/v1/query',
    'github-advisories' => 'https://api.github.com/advisories',
];

// Register all sources with NewsFetcher (idempotent — re-registering is safe)
foreach ($configSources as $name => $url) {
    \TomLabs\Labs\NewsFetcher::registerSource($name, $url);
}
$startedAt = \TimeHelper::now();

$result = [
    'sources' => [],
    'total_fetched' => 0,
    'total_inserted' => 0,
    'total_updated' => 0,
    'total_duplicates' => 0,
    'total_errors' => 0,
    'errors' => [],
    'force' => $force
];

// Fetch from each source
foreach ($configSources as $name => $url) {
    $items = [];
    $sourceLabel = $name; // Use the configured source name as the label

    // Log if forcing cache bypass
    if ($force) {
        error_log("News fetch: forcing fresh fetch for '$name'");
    }

    // Special handling: OSV requires POST with JSON query body
    $postBody = [];
    if ($name === 'osv') {
        $postBody = ['query' => 'osv_schema', 'limit' => 20];
    }

    if (str_ends_with($url, '.xml') || str_contains($url, 'feeds.feedburner.com') || str_contains($url, '/feed/')) {
        $items = \TomLabs\Labs\NewsFetcher::fetchRss($name, $force);
    } else {
        $items = \TomLabs\Labs\NewsFetcher::fetchJson($name, $force, $postBody);
    }

    $fetchedCount = count($items);

    // Process and tag each item with source, category, and severity before storing
    foreach ($items as &$it) {
        // Ensure source is set to the canonical source name
        $it['source'] = $sourceLabel;

        // Auto-determine category based on source/content
        if (empty($it['category'])) {
            $it['category'] = \TomLabs\Labs\NewsFetcher::inferCategory($sourceLabel, $it['title'] ?? '', $it['summary'] ?? '', $it['description'] ?? '');
        }

        // Auto-detect severity and CVE IDs from content
        if (empty($it['severity']) || $it['severity'] === 'info') {
            $detected = \TomLabs\Labs\NewsFetcher::detectVulnerabilityInfo($it['title'] ?? '', $it['summary'] ?? '', $it['description'] ?? '');
            if (!empty($detected['severity'])) {
                $it['severity'] = $detected['severity'];
            }
            if (!empty($detected['cve_ids'])) {
                $it['cve_ids'] = $detected['cve_ids'];
            }
        }
    }
    unset($it);

    $store = \TomLabs\Labs\NewsFetcher::storeItems($items, $sourceLabel);

    // Build per-source result with full details
    $sourceResult = [
        'name' => $name,
        'url' => $url,
        'fetched' => $fetchedCount,
        'new' => $store['inserted'],
        'duplicate' => $store['updated'],
        'errors' => count($store['errors']),
    ];
    $result['sources'][] = $sourceResult;

    // Log individual source results for audit trail
    error_log("News fetch: $name - fetched:$fetchedCount new:{$store['inserted']} dup:{$store['updated']} err:" . count($store['errors']));
    $result['total_fetched'] += $fetchedCount;
    $result['total_inserted'] += $store['inserted'];
    $result['total_updated'] += $store['updated'];
    $result['total_duplicates'] += $store['updated']; // Updated = duplicates
    $result['total_errors'] += $sourceResult['errors'];

    // Aggregate per-source errors into overall result (non-fatal)
    foreach ($store['errors'] as $e) {
        $result['errors'][] = ['source' => $name, 'item' => $e['item'], 'reason' => $e['reason']];
    }
}

// Soft-delete any articles whose source is no longer configured (optional cleanup)
/* $activeGuids = array_column(NewsFetcher::getLatest(), function($i) { return $i['guid']; });
... */
// Actually skip — we only soft-delete on explicit admin request via delete.php

// Build human-readable summary message
$summary = "Fetched " . $result['total_fetched'] . " articles";
if ($force) {
    $summary .= " (forced refresh)";
}
$summary .= ": " . $result['total_inserted'] . " new, " . $result['total_duplicates'] . " duplicate";
if ($result['total_errors'] > 0) {
    $summary .= ", " . $result['total_errors'] . " errors";
}

// Store fetch log entry (UTCDateTime via TimeHelper — DateTimeImmutable serializes to an empty BSON document)
$db->news_fetch_logs->insertOne([
    'started_at' => $startedAt,
    'completed_at' => \TimeHelper::now(),
    'sources' => $result['sources'],
    'total_fetched' => $result['total_fetched'],
    'total_inserted' => $result['total_inserted'],
    'total_updated' => $result['total_duplicates'],
    'total_errors' => $result['total_errors'],
    'error_details' => $result['errors'],
    'triggered_by' => 'admin UI',
    'force' => $force,
    'session_user' => $user->getUsername() ?? 'unknown',
]);

// Update news_settings.last_fetch so the admin UI "Next fetch" label moves forward
try {
    $db->global_settings->updateOne(
        ['_id' => 'news_settings'],
        ['$set' => [
            'last_fetch' => \TimeHelper::now(),
            'last_fetch_by' => $user->getUsername() ?? 'admin',
            'updated_at' => \TimeHelper::now(),
        ]],
        ['upsert' => true]
    );
} catch (\Throwable $e) {
    // Non-fatal: fetch log already written; log and continue
    error_log('News fetch: failed to update news_settings.last_fetch: ' . $e->getMessage());
}

header('Content-Type: application/json');
echo json_encode([
    'status' => 'success',
    'message' => $summary,
    'data' => $result
]);