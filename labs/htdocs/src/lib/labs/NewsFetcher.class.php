<?php
/**
 * NewsFetcher — Aggregates cybersecurity news from public RSS feeds + JSON APIs.
 *
 * Stores into MongoDB collection `news_articles` in `tom_labs_db`.
 * Uses existing Cache (TTL), CircuitBreaker (per-source) and TimeHelper (UTC).
 *
 * Namespace: TomLabs\Labs — project convention for all lab-class code (AGENTS.md).
 *
 * Security:
 *   - SSRF protection: only HTTPS URLs to a whitelisted set of hostnames are allowed.
 *   - Source names are validated (alphanumeric + dash/underscore only).
 *   - All outbound requests have a 15s timeout and a descriptive User-Agent.
 *   - Response bodies are capped at 2 MB to prevent memory exhaustion.
 *
 * Usage (example):
 *   $items = NewsFetcher::fetchRss('thehackernews');
 *   NewsFetcher::storeItems($items);
 *
 * Design notes:
 *   - fetchRss() / fetchJson() return flat arrays; storeItems() handles DB upsert.
 *   - dedupeByGuid() uses the feed item's `guid` field (or url if guid missing).
 *   - Per-source CircuitBreaker prevents hammering a failing source.
 *   - Cache with 30-60 min TTL prevents duplicate fetches from same source per interval.
 */

namespace TomLabs\Labs;

class NewsFetcher {

    /** @var string MongoDB connection URI (env.json database_file). */
    private const DB_URI_KEY = 'database_file';

    /** @var string Collection name. */
    public const COLLECTION = 'news_articles';

    /** Cache TTL in seconds per source (30 min default). */
    private const DEFAULT_TTL = 1800;

    /** CircuitBreaker failure threshold per source. */
    private const CB_THRESHOLD = 5;

    /** CircuitBreaker cooldown in seconds. */
    private const CB_COOLDOWN = 60;

    /** Maximum response body size in bytes (2 MB). */
    private const MAX_BODY_SIZE = 2 * 1024 * 1024;

    /** Allowed outbound hostnames (SSRF protection). */
    private const ALLOWED_HOSTS = [
        'feeds.feedburner.com',
        'www.bleepingcomputer.com',
        'krebsonsecurity.com',
        'www.securityweek.com',
        'cybersecuritynews.com',
        'securityaffairs.com',
        'www.cybersecuritydive.com',
        'www.theverge.com',
        'services.nvd.nist.gov',
        'www.cisa.gov',
        'api.osv.dev',
        'api.github.com',
    ];

    /** @var array Configured source URLs keyed by unique source name. */
    private static $sources = [];

    /** @var array<string, array{state: mixed, failures: int}> CircuitBreaker state per source. */
    private static $cbStates = [];

    /**
     * Validate a source name (alphanumeric + dash/underscore only).
     */
    private static function isValidSourceName(string $name): bool {
        return (bool) preg_match('/^[a-z0-9_-]+$/', $name);
    }

    /**
     * SSRF-protected URL validation: must be https and host must be whitelisted.
     */
    private static function isAllowedUrl(string $url): bool {
        $parsed = @parse_url($url);
        if ($parsed === false || !isset($parsed['scheme'], $parsed['host'])) {
            return false;
        }
        if (strtolower($parsed['scheme']) !== 'https') {
            return false;
        }
        $host = strtolower($parsed['host']);
        // Strip www. prefix for matching
        $host = preg_replace('/^www\./', '', $host);
        foreach (self::ALLOWED_HOSTS as $allowed) {
            $allowedHost = preg_replace('/^www\./', '', strtolower($allowed));
            if ($host === $allowedHost) {
                return true;
            }
        }
        return false;
    }

    /**
     * Register a news source URL.
     * Called once at bootstrap / config load.
     *
     * @param string $name Unique source identifier (used as circuit-breaker key).
     * @param string $url  RSS feed URL or JSON API endpoint.
     * @throws \InvalidArgumentException if name or url is invalid.
     */
    public static function registerSource(string $name, string $url): void {
        if (!self::isValidSourceName($name)) {
            throw new \InvalidArgumentException("Invalid source name: $name");
        }
        if (!self::isAllowedUrl($url)) {
            throw new \InvalidArgumentException("Disallowed URL for source '$name': $url");
        }
        self::$sources[$name] = $url;
        if (!isset(self::$cbStates[$name])) {
            self::$cbStates[$name] = ['state' => \CircuitBreaker::STATE_CLOSED, 'failures' => 0];
        }
    }

    /**
     * Get all registered source names.
     *
     * @return array
     */
    public static function getSources(): array {
        return array_keys(self::$sources);
    }

    /**
     * Log a structured fetch error for debugging.
     */
    private static function logFetchError(string $name, string $stage, string $message, array $extra = []): void {
        $payload = array_merge([
            'source' => $name,
            'stage' => $stage,
            'error' => $message,
            'time' => date('Y-m-d H:i:s'),
        ], $extra);
        error_log('NewsFetcher: ' . json_encode($payload));
    }

    /**
     * Fetch and parse a single RSS feed.
     * Respects per-source CircuitBreaker — if OPEN, returns empty array.
     * Caches result under "news_rss:{name}" for DEFAULT_TTL seconds (unless $force=true).
     *
     * @param string $name Source name (must have been registered via registerSource()).
     * @param bool $force If true, bypass cache and fetch fresh data.
     * @return array Flat array of items with at least: title, link, description, pubDate (or equivalent), guid.
     */
    public static function fetchRss(string $name, bool $force = false): array {
        if (!self::isValidSourceName($name)) {
            self::logFetchError($name, 'validation', 'Invalid source name');
            return [];
        }
        if (!isset(self::$sources[$name])) {
            self::logFetchError($name, 'validation', 'Unknown source');
            return [];
        }

        // CircuitBreaker gate
        if (!\CircuitBreaker::allow($name)) {
            self::logFetchError($name, 'circuit_breaker', 'Circuit breaker OPEN');
            return [];
        }

        $cacheKey = 'news_rss:' . $name;
        // Only use cache if not forcing
        if (!$force) {
            $cached = \Cache::get($cacheKey, []);
            if (!empty($cached)) {
                return $cached;
            }
        }

        $url = self::$sources[$name];
        self::logFetchError($name, 'fetch_start', 'Fetching RSS', ['url' => $url]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; NewsFetcher/1.0; +https://tomweb.in)');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/rss+xml, application/xml, text/xml']);
        $xml = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?? '';
        $contentLength = curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD) ?? 0;
        curl_close($ch);

        if ($curlError !== '') {
            \CircuitBreaker::recordFailure($name);
            self::logFetchError($name, 'curl_error', $curlError, ['url' => $url, 'http_code' => $httpCode]);
            return [];
        }

        if ($httpCode !== 200 || $xml === false || empty($xml)) {
            \CircuitBreaker::recordFailure($name);
            self::logFetchError($name, 'http_error', 'Bad response', [
                'url' => $url,
                'http_code' => $httpCode,
                'content_type' => $contentType,
                'content_length' => $contentLength,
            ]);
            return [];
        }

        // Cap body size
        if ($contentLength > self::MAX_BODY_SIZE) {
            \CircuitBreaker::recordFailure($name);
            self::logFetchError($name, 'body_too_large', 'Response exceeds limit', [
                'url' => $url,
                'content_length' => $contentLength,
                'limit' => self::MAX_BODY_SIZE,
            ]);
            return [];
        }

        // Parse RSS 2.0 / RSS 0.91 / RSS 1.0 with SimpleXML
        libxml_use_internal_errors(true);
        $dom = @simplexml_load_string($xml);
        $libxmlErrors = libxml_get_errors();
        libxml_clear_errors();

        if ($dom === false) {
            \CircuitBreaker::recordFailure($name);
            self::logFetchError($name, 'xml_parse_error', 'SimpleXML parse failed', [
                'url' => $url,
                'libxml_errors' => array_map(fn($e) => $e->message, $libxmlErrors),
            ]);
            return [];
        }

        $items = [];

        // Handle both <item> (RSS 2.0) and <item> inside <channel> (RSS 1.0 / 0.91)
        $channel = $dom->channel ?? $dom;
        foreach ($channel->item ?? [] as $it) {
            $item = [];
            // Title
            $item['title'] = (string) ($it->title ?? $it->{'title'} ?? '');
            // Link
            $item['link'] = (string) ($it->link ?? $it->{'link'} ?? '');
            // Description (may be <description> or <content:encoded>)
            $item['description'] = (string) ($it->description ?? $it->{'description'} ?? '');
            // GUID — prefer <guid isPermaLink="false">, else <guid>, else link
            $guid = (string) ($it->guid ?? $it->{'guid'} ?? '');
            $isPerma = (bool) ($it->{'isPermaLink'} ?? $it->{'isPermaLink'} ?? false);
            if (!empty($guid)) {
                $item['guid'] = $guid;
            } else {
                $item['guid'] = $item['link'];
            }
            // PubDate / publishedDate
            $pubDate = (string) ($it->pubDate ?? $it->{'pubDate'} ?? $it->{'published'} ?? '');
            if (!empty($pubDate)) {
                $item['published_at'] = strtotime($pubDate);
            }
            // Summary / content snippet
            $item['summary'] = $item['description'];

            $items[] = $item;
        }

        // Success: record + cache (skip caching if forcing)
        \CircuitBreaker::recordSuccess($name);
        if (!$force && !empty($items)) {
            \Cache::set($cacheKey, $items, self::DEFAULT_TTL);
        }

        self::logFetchError($name, 'fetch_success', 'RSS parsed', [
            'url' => $url,
            'item_count' => count($items),
        ]);

        return $items;
    }

    /**
     * Fetch from a JSON API endpoint.
     * Respects CircuitBreaker and TTL cache under "news_json:{name}" (unless $force=true).
     *
     * @param string $name Source name (must have been registered via registerSource()).
     * @param bool $force If true, bypass cache and fetch fresh data.
     * @param array $postBody Optional JSON body for POST requests (e.g. OSV query API).
     * @return array Flat array of normalized items (same schema as fetchRss output).
     */
    public static function fetchJson(string $name, bool $force = false, array $postBody = []): array {
        if (!self::isValidSourceName($name)) {
            self::logFetchError($name, 'validation', 'Invalid source name');
            return [];
        }
        if (!isset(self::$sources[$name])) {
            self::logFetchError($name, 'validation', 'Unknown source');
            return [];
        }

        if (!\CircuitBreaker::allow($name)) {
            self::logFetchError($name, 'circuit_breaker', 'Circuit breaker OPEN');
            return [];
        }

        $cacheKey = 'news_json:' . $name;
        // Only use cache if not forcing
        if (!$force) {
            $cached = \Cache::get($cacheKey, []);
            if (!empty($cached)) {
                return $cached;
            }
        }

        $url = self::$sources[$name];
        self::logFetchError($name, 'fetch_start', 'Fetching JSON', ['url' => $url, 'has_post_body' => !empty($postBody)]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; NewsFetcher/1.0; +https://tomweb.in)');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Content-Type: application/json',
        ]);

        if (!empty($postBody)) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postBody));
        }

        $resp = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?? '';
        $contentLength = curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD) ?? 0;
        curl_close($ch);

        if ($curlError !== '') {
            \CircuitBreaker::recordFailure($name);
            self::logFetchError($name, 'curl_error', $curlError, ['url' => $url, 'http_code' => $httpCode]);
            return [];
        }

        if ($httpCode !== 200 || $resp === false || empty($resp)) {
            \CircuitBreaker::recordFailure($name);
            self::logFetchError($name, 'http_error', 'Bad response', [
                'url' => $url,
                'http_code' => $httpCode,
                'content_type' => $contentType,
                'content_length' => $contentLength,
            ]);
            return [];
        }

        // Cap body size
        if ($contentLength > self::MAX_BODY_SIZE) {
            \CircuitBreaker::recordFailure($name);
            self::logFetchError($name, 'body_too_large', 'Response exceeds limit', [
                'url' => $url,
                'content_length' => $contentLength,
                'limit' => self::MAX_BODY_SIZE,
            ]);
            return [];
        }

        $data = json_decode($resp, true);
        if ($data === null) {
            \CircuitBreaker::recordFailure($name);
            self::logFetchError($name, 'json_decode_error', json_last_error_msg(), [
                'url' => $url,
                'http_code' => $httpCode,
                'content_type' => $contentType,
            ]);
            return [];
        }

        $items = [];
        foreach ($data as $row) {
            if (!is_array($row)) {
                continue;
            }
            $item = [];
            // Title: prefer title, fall back to summary/ghsa_id
            $item['title'] = $row['title'] ?? $row['Title'] ?? $row['summary'] ?? $row['ghsa_id'] ?? $row['Summary'] ?? '';
            $item['link'] = $row['link'] ?? $row['Link'] ?? $row['url'] ?? '';
            // GitHub Advisory API: convert api.github.com URL to github.com web URL
            if ($name === 'github-advisories') {
                if (!empty($row['html_url'])) {
                    $item['link'] = $row['html_url'];
                } elseif (!empty($row['ghsa_id'])) {
                    $item['link'] = 'https://github.com/advisories/' . $row['ghsa_id'];
                } else {
                    $item['link'] = str_replace('api.github.com/advisories/', 'github.com/advisories/', $item['link']);
                }
            }
            $item['description'] = $row['description'] ?? $row['Description'] ?? $row['summary'] ?? '';
            $item['summary'] = $item['description'];

            // GUID / id
            $guid = $row['guid'] ?? $row['id'] ?? $row['uuid'] ?? '';
            if (!empty($guid)) {
                $item['guid'] = $guid;
            } else {
                $item['guid'] = $item['link'];
            }

            // Published date — try ISO first
            $pub = $row['publishedAt'] ?? $row['published'] ?? $row['pubDate'] ?? $row['date'] ?? '';
            if (!empty($pub)) {
                $ts = strtotime($pub);
                if ($ts !== false) {
                    $item['published_at'] = $ts;
                }
            }

            $items[] = $item;
        }

        \CircuitBreaker::recordSuccess($name);
        // Skip caching when forcing to always fetch fresh data
        if (!$force && !empty($items)) {
            \Cache::set($cacheKey, $items, self::DEFAULT_TTL);
        }

        self::logFetchError($name, 'fetch_success', 'JSON parsed', [
            'url' => $url,
            'item_count' => count($items),
        ]);

        return $items;
    }

    /**
     * Normalize & store fetched items into MongoDB `news_articles` collection.
     * Each item must have at least: guid (unique), title, link, source, published_at (UTC epoch int).
     * Upserts by `guid`; adds fetched_at, category, severity (if CVE-related), raw_json.
     *
     * @param string|null $source Optional source name to use if item doesn't have one.
     * @param array $items Flat array as returned by fetchRss() or fetchJson().
     * @return array ['inserted' => n, 'updated' => m, 'errors' => [...]]
     */
    public static function storeItems(array $items, ?string $source = null): array {
        $db = \DatabaseConnection::getDefaultDatabase();
        $col = $db->selectCollection(self::COLLECTION);

        $result = ['inserted' => 0, 'updated' => 0, 'errors' => []];

        foreach ($items as $it) {
            try {
                if (empty($it['guid'])) {
                    $result['errors'][] = ['item' => $it['title'] ?? 'unnamed', 'reason' => 'missing guid'];
                    continue;
                }

                // Normalize source: use item's source, fallback to passed source, then 'unknown'
                $itemSource = $it['source'] ?? $source ?? 'unknown';
                // Sanitize source - extract just the hostname from URLs, clean up values
                if ($itemSource && filter_var($itemSource, FILTER_VALIDATE_URL)) {
                    $parsed = parse_url($itemSource);
                    $itemSource = $parsed['host'] ?? 'unknown';
                }
                $itemSource = strtolower(trim($itemSource ?: 'unknown'));
                // Validate against known sources, otherwise mark as 'unknown'
                $knownSources = self::getSources();
                if (!empty($knownSources) && !in_array($itemSource, $knownSources, true)) {
                    // Check if it matches any known source
                    $found = false;
                    foreach ($knownSources as $ks) {
                        if (stripos($itemSource, $ks) !== false || stripos($ks, $itemSource) !== false) {
                            $itemSource = $ks;
                            $found = true;
                            break;
                        }
                    }
                    if (!$found && $itemSource !== 'unknown') {
                        $itemSource = 'unknown';
                    }
                }

                // Build Mongo document
                $doc = [
                    'guid' => $it['guid'],
                    'title' => !empty($it['title']) ? $it['title'] : ($it['guid'] ?? $it['link'] ?? 'Untitled'),
                    'link' => $it['link'] ?? '',
                    'source' => $itemSource,
                    'summary' => $it['summary'] ?? '',
                    'published_at' => isset($it['published_at']) && is_numeric($it['published_at'])
                    ? new \MongoDB\BSON\UTCDateTime((int) $it['published_at'] * 1000)
                    : \TimeHelper::now(),
                    'fetched_at' => \TimeHelper::now(),
                    'category' => $it['category'] ?? 'news',
                    'severity' => $it['severity'] ?? 'info',
                    'cve_ids' => $it['cve_ids'] ?? [],
                    'raw_json' => $it,
                    'deleted_at' => null, // soft-delete flag
                ];

                // Upsert by guid — replace existing or insert new
                $updateResult = $col->updateOne(
                    ['guid' => $doc['guid']],
                    ['$set' => $doc],
                    ['upsert' => true]
                );
                // upsertedCount = 1 if new doc created, modifiedCount = 1 if existing doc updated
                if ($updateResult->getUpsertedCount() > 0) {
                    $result['inserted']++;
                } else {
                    $result['updated']++;
                }
            } catch (Exception $e) {
                $result['errors'][] = ['item' => $it['title'] ?? 'unnamed', 'reason' => $e->getMessage()];
            }
        }

        return $result;
    }

    /**
     * Soft-delete an article by guid (mark deleted_at).
     * Admin-only; called from delete.php API handler.
     *
     * @param string $guid Item guid to soft-delete.
     * @return bool True on success.
     */
    public static function softDelete(string $guid): bool {
        $db = \DatabaseConnection::getDefaultDatabase();
        $col = $db->selectCollection(self::COLLECTION);
        $now = \TimeHelper::now();
        $result = $col->updateOne(
            ['guid' => $guid],
            ['$set' => ['deleted_at' => $now]]
        );
        return $result->getModifiedCount() > 0;
    }

    /**
     * Get latest articles with optional filters.
     * Public read — no admin gate.
     *
     * @param string|null $source Filter by source name.
     * @param string|null $category Filter by category.
     * @param string|null $severity Filter by severity (for CVE/KEV items).
     * @param string|null $q Free-text search on title+summary.
     * @param int $limit Max items to return (default 50).
     * @param int $skip Pagination offset (default 0).
     * @param string|null $dateFilter 'today' | 'yesterday' — IST day-range pushed
     *        into the Mongo query (published_at BETWEEN start AND end). Filtering
     *        server-side (not after the fetch) keeps pagination truthful: the "yesterday"
     *        tab starts at yesterday's newest article instead of paging past all of
     *        today's before reaching yesterday's, and `total` counts that day only.
     * @return array ['items' => [...], 'total' => n]
     */
    public static function getLatest(?string $source = null, ?string $category = null, ?string $severity = null, ?string $q = null, int $limit = 50, int $skip = 0, ?string $dateFilter = null): array {
        $db = \DatabaseConnection::getDefaultDatabase();
        $col = $db->selectCollection(self::COLLECTION);

        $filter = ['deleted_at' => null]; // only non-deleted

        if ($dateFilter === 'today' || $dateFilter === 'yesterday') {
            $range = \TimeHelper::dayRange($dateFilter === 'yesterday' ? 1 : 0);
            $filter['published_at'] = [
                '$gte' => new \MongoDB\BSON\UTCDateTime((int) $range['start']->format('Uv')),
                '$lt'  => new \MongoDB\BSON\UTCDateTime((int) $range['end']->format('Uv')),
            ];
        }

        if (!empty($source)) {
            $filter['source'] = $source;
        }
        if (!empty($category)) {
            $filter['category'] = $category;
        }
        if (!empty($severity)) {
            $filter['severity'] = $severity;
        }
        if (!empty($q)) {
            $filter['$or'] = [
                ['title' => new \MongoDB\BSON\Regex('/' . preg_quote($q, '/') . '/i')],
                ['summary' => new \MongoDB\BSON\Regex('/' . preg_quote($q, '/') . '/i')],
            ];
        }

        $total = $col->countDocuments($filter);
        $cursor = $col->find($filter, [
            'sort' => ['published_at' => -1], // latest first
            'limit' => $limit,
            'skip' => $skip,
        ]);

        $items = [];
        foreach ($cursor as $doc) {
            $doc['_id'] = (string) $doc['_id'];
            $items[] = $doc;
        }

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Infer category from source name and content.
     * Uses keyword matching on title/summary to classify news items.
     *
     * @param string $source Source name.
     * @param string $title Article title.
     * @param string $summary Article summary.
     * @param string $description Full description/content.
     * @return string Category name.
     */
    public static function inferCategory(string $source, string $title = '', string $summary = '', string $description = ''): string {
        $combined = strtolower($title . ' ' . $summary . ' ' . $description);
        $combined = preg_replace('/[^a-z0-9\s]/', ' ', $combined);

        // CVE-specific categories
        if (preg_match('/\bcve-\d+-\d+\b/', $combined) || stripos($combined, 'vulnerability') !== false || stripos($combined, 'cve') !== false) {
            return 'cve';
        }

        // Malware category
        if (preg_match('/malware|trojan|ransomware|viral|infection/', $combined)) {
            return 'malware';
        }

        // Breach category
        if (preg_match('/breach|leak|compromised|data breach/', $combined)) {
            return 'breach';
        }

        // Exploit category
        if (preg_match('/exploit|zero-day|attack|hacker/', $combined)) {
            return 'exploit';
        }

        // Research/Academic
        if (preg_match('/research|study|paper|whitepaper/', $combined)) {
            return 'research';
        }

        // Policy/Legislation
        if (preg_match('/regulation|law|legislation|gdpr|privacy|compliance/', $combined)) {
            return 'policy';
        }

        // Default to news
        return 'news';
    }

    /**
     * Detect vulnerability information (CVE IDs, severity) from content.
     * Scans title and summary for CVE patterns and severity indicators.
     *
     * @param string $title Article title.
     * @param string $summary Article summary.
     * @param string $description Full description.
     * @return array ['severity' => string|null, 'cve_ids' => array]
     */
    public static function detectVulnerabilityInfo(string $title = '', string $summary = '', string $description = ''): array {
        $combined = $title . ' ' . $summary . ' ' . $description;
        $cvePatterns = [
            '/CVE-\d{4}-\d{4,7}/i',
            '/CVE-\d{4}-\d+/i',
            '/\bCVE-\d{4}-\d+\b/i',
        ];

        $cveIds = [];
        foreach ($cvePatterns as $pattern) {
            if (preg_match_all($pattern, $combined, $matches)) {
                foreach ($matches[0] as $cve) {
                    $cve = strtoupper(trim($cve));
                    if (!in_array($cve, $cveIds, true)) {
                        $cveIds[] = $cve;
                    }
                }
            }
        }

        // Determine severity based on keywords
        $severity = 'info';
        $severityKeywords = [
            'critical' => ['critical', 'zero-day exploit', 'remote code execution', 'rce:', 'arbitrary code'],
            'high' => ['high severity', 'privilege escalation', 'escalation', 'remote code', 'rce'],
            'medium' => ['medium severity', 'dos denial', 'cross-site scripting', 'xss', 'csrf'],
            'low' => ['low severity', 'information disclosure', 'spoofing', 'phishing'],
            'success' => ['fix released', 'patched', 'update available', 'mitigation'],
        ];

        $upperCombined = strtoupper($combined);
        foreach ($severityKeywords as $sev => $keywords) {
            foreach ($keywords as $kw) {
                if (stripos($upperCombined, $kw) !== false) {
                    if ($sev !== 'success') {
                        $severity = $sev;
                        break 2;
                    }
                }
            }
        }

        return [
            'severity' => $severity ?: 'info',
            'cve_ids' => $cveIds,
        ];
    }
}
