<?php
/**
 * News fetch CLI script - triggers fetching of cybersecurity news from all RSS/JSON sources.
 *
 * This is a standalone script meant to be run via cron or systemd timer.
 * It does NOT require authentication - it's a server-side automation task.
 *
 * Usage:
 *   php fetch.php [--source=source_name]
 *
 * Exit codes:
 *   0 - Success
 *   1 - Error
 */

// Bootstrap the application
require_once __DIR__ . '/../../src/load.php';

echo "[" . date('Y-m-d H:i:s') . "] Starting news fetch...\n";
$startedAt = \TimeHelper::now();

// Configure sources - same as in src/api/news/fetch.php
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

// Check for optional source filter
$sourceFilter = $argv[1] ?? null;
if ($sourceFilter && strpos($sourceFilter, '--source=') === 0) {
    $sourceFilter = substr($sourceFilter, 10);
    if (!isset($configSources[$sourceFilter])) {
        echo "ERROR: Unknown source '$sourceFilter'\n";
        exit(1);
    }
    $configSources = [$sourceFilter => $configSources[$sourceFilter]];
}

// ── Respect admin panel news automation settings ──
$db = DatabaseConnection::getDefaultDatabase();
$newsDoc = $db->global_settings->findOne(['_id' => 'news_settings']);
$newsSettings = ($newsDoc && is_object($newsDoc) && method_exists($newsDoc, 'getArrayCopy'))
    ? $newsDoc->getArrayCopy()
    : ((array)$newsDoc ?: []);

$enabled = !empty($newsSettings['enabled']);
$interval = (int)($newsSettings['interval'] ?? 60);
if ($interval < 30) {
    $interval = 30;
}
$lastFetch = isset($newsSettings['last_fetch']) ? $newsSettings['last_fetch'] : null;

if (!$enabled) {
    echo "[" . date('Y-m-d H:i:s') . "] News auto-fetch is DISABLED in admin panel. Skipping.\n";
    exit(0);
}

if ($lastFetch instanceof \MongoDB\BSON\UTCDateTime || $lastFetch instanceof \DateTimeInterface) {
    $lastFetchTs = $lastFetch instanceof \MongoDB\BSON\UTCDateTime
        ? $lastFetch->toDateTime()->getTimestamp()
        : $lastFetch->getTimestamp();
    $now = time();
    $diff = $now - $lastFetchTs;
    if ($diff < $interval * 60) {
        $remaining = ceil(($interval * 60 - $diff) / 60);
        echo "[" . date('Y-m-d H:i:s') . "] Last fetch " . max(1, (int) ceil($diff / 60)) . " minute(s) ago. Next fetch due in ~$remaining min. Skipping.\n";
        exit(0);
    }
}

// Register all sources with NewsFetcher
foreach ($configSources as $name => $url) {
    \TomLabs\Labs\NewsFetcher::registerSource($name, $url);
}

$result = ['total_inserted' => 0, 'total_updated' => 0, 'total_errors' => 0, 'sources' => []];

// Fetch from each source
foreach ($configSources as $name => $url) {
    $items = [];

    // Special handling: OSV requires POST with JSON query body
    $postBody = [];
    if ($name === 'osv') {
        $postBody = ['query' => 'osv_schema', 'limit' => 20];
    }

    if (str_ends_with($url, '.xml') || str_contains($url, 'feeds.feedburner.com') || str_contains($url, '/feed/')) {
        $items = \TomLabs\Labs\NewsFetcher::fetchRss($name);
    } else {
        $items = \TomLabs\Labs\NewsFetcher::fetchJson($name, false, $postBody);
    }

    // Process and tag each item
    foreach ($items as &$it) {
        $it['source'] = $name;

        // Auto-determine category if not set
        if (empty($it['category'])) {
            $it['category'] = \TomLabs\Labs\NewsFetcher::inferCategory($name, $it['title'] ?? '', $it['summary'] ?? '', $it['description'] ?? '');
        }

        // Auto-detect severity and CVE IDs
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

    $store = \TomLabs\Labs\NewsFetcher::storeItems($items, $name);

    $result['sources'][] = [
        'name' => $name,
        'fetched' => count($items),
        'inserted' => $store['inserted'],
        'updated' => $store['updated'],
        'errors' => count($store['errors']),
    ];
    $result['total_inserted'] += $store['inserted'];
    $result['total_updated'] += $store['updated'];
    $result['total_errors'] += count($store['errors']);

    echo "  [$name] " . count($items) . " items fetched, " . $store['inserted'] . " stored, " . $store['updated'] . " updated\n";
}

echo "[" . date('Y-m-d H:i:s') . "] Complete: {$result['total_inserted']} inserted, {$result['total_updated']} updated, {$result['total_errors']} errors\n";
echo "Total sources: " . count($result['sources']) . "\n";

// Record last fetch time (UTCDateTime via TimeHelper — a bare DateTimeImmutable
// serializes to an empty BSON document in this context and breaks reading later)
$db->global_settings->updateOne(
    ['_id' => 'news_settings'],
    ['$set' => [
        'last_fetch' => \TimeHelper::now(),
        'last_fetch_by' => 'scheduler',
    ]],
    ['upsert' => true]
);

// Calculate total fetched from sources
$totalFetched = 0;
foreach ($result['sources'] as $src) {
    $totalFetched += $src['fetched'];
}

// Store fetch log entry for admin UI
$completedAt = \TimeHelper::now();
$db->news_fetch_logs->insertOne([
    'started_at' => $startedAt,
    'completed_at' => $completedAt,
    'sources' => $result['sources'],
    'total_fetched' => $totalFetched,
    'total_inserted' => $result['total_inserted'],
    'total_updated' => $result['total_updated'],
    'total_errors' => $result['total_errors'],
    'error_details' => [],
    'triggered_by' => 'scheduler',
    'force' => false,
]);

exit(0);