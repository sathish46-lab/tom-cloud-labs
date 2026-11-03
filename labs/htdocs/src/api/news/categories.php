<?php
/**
 * News categories API — GET /api/news/categories
 * Returns {status:'success', data:{sources:[...], categories:[...], severities:[...]}}
 * Public read — no auth required. Lists distinct source names, categories and severities
 * currently present in the news_articles collection.
 */

require_once __DIR__ . '/../../../src/load.php';

require_once __DIR__ . '/../../../src/lib/labs/NewsFetcher.class.php';

$fetcher = new \TomLabs\Labs\NewsFetcher();
$db = DatabaseConnection::getDefaultDatabase();
$col = $db->selectCollection($fetcher::COLLECTION);

// Distinct source names
$sources = $col->distinct('source');

// Distinct categories
$categories = $col->distinct('category');

// Distinct severities
$severities = $col->distinct('severity');

// Also include the full configured source list so the UI always has the complete set
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
    'cisa-kev' => 'https://www.cisa.gov/known-exploited-vulnerabilities-catalog',
    'osv' => 'https://api.osv.dev/v1/query',
    'github-advisories' => 'https://github.com/advisories',
];

// Merge configured sources with DB-observed sources
$allSources = array_unique(array_merge(array_keys($configSources), $sources));

header('Content-Type: application/json');
echo json_encode([
    'status' => 'success',
    'data' => [
        'sources' => $allSources,
        'categories' => $categories,
        'severities' => $severities,
    ]
]);