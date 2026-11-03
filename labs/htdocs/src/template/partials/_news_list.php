<?php
/**
 * News list partial — Renders a paginated list of news articles.
 * Used by both initial page load and HTMX/ajax refreshes.
 *
 * Layout: flexible card grid with gap.
 * Date filtering: today / yesterday (API level filtering).
 * Source grouping: When no source filter is selected, groups all articles by source.
 *
 * Query params supported:
 *   ?source=, ?category=, ?severity=, ?q=, ?skip=, ?limit=, ?date_filter=
 */

require_once __DIR__ . '/../../lib/labs/NewsFetcher.class.php';

$source = $_GET['source'] ?? null;
$category = $_GET['category'] ?? null;
$severity = $_GET['severity'] ?? null;
$q = $_GET['q'] ?? null;
$date_filter = $_GET['date_filter'] ?? null; // 'today', 'yesterday', null
$limit = (int)($_GET['limit'] ?? 20);
$skip = (int)($_GET['skip'] ?? 0);

// Validate limit
if ($limit < 1 || $limit > 100) {
    $limit = 20;
}

$fetcher = new \TomLabs\Labs\NewsFetcher();
// When date_filter is today/yesterday, exclude GitHub Advisories (own tab) unless explicitly selected
$excludeSource = null;
if (($date_filter === 'today' || $date_filter === 'yesterday') && empty($source)) {
    $excludeSource = 'github-advisories';
}
// $date_filter ('today'/'yesterday') is pushed into the Mongo query itself so
// pagination starts inside the requested day — see NewsFetcher::getLatest()
$list = $fetcher->getLatest($source, $category, $severity, $q, $limit, $skip, $date_filter, $excludeSource);

$total = $list['total'];
$items = $list['items'];

// Source grouping removed — items display in DB order (newest first),
// so sources interleave naturally by published date instead of clumping.

$hasMore = $total > $skip + $limit;
$nextSkip = $skip + $limit;
?>
<style>
/* news-grid layout lives in _news.scss — JS masonry (roadmap dashboard style) */
.news-sentinel {
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
}
</style>

<?php if (empty($items)): ?>
    <div class="col-12 w-100 text-center py-5">
        <h5 class="text-body-emphasis fw-semibold mb-2" style="font-size: 1.1rem;">No news articles found!</h5>
        <p class="text-body-secondary mb-0" style="font-size: 0.95rem;">Fetch some articles to get started. 📰</p>
    </div>
<?php else: ?>
    <div class="news-grid" id="news-list-container">
        <?php foreach ($items as $item): ?>
        <?php include __DIR__ . '/_news_card.php'; ?>
        <?php endforeach; ?>
    </div>

    <?php if ($hasMore): ?>
    <div class="col-12 text-center mt-4 mb-4" id="news-sentinel-wrapper">
        <div class="news-sentinel" id="news-sentinel">
            <i class="bx bx-loader-circle bx-spin fs-4 text-primary"></i>
        </div>
    </div>
    <input type="hidden" id="news-next-skip" value="<?= $nextSkip ?>">
    <input type="hidden" id="news-next-limit" value="<?= $limit ?>">
    <input type="hidden" id="news-next-source" value="<?= htmlspecialchars($_GET['source'] ?? '') ?>">
    <input type="hidden" id="news-next-category" value="<?= htmlspecialchars($_GET['category'] ?? '') ?>">
    <input type="hidden" id="news-next-severity" value="<?= htmlspecialchars($_GET['severity'] ?? '') ?>">
    <input type="hidden" id="news-next-q" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
    <input type="hidden" id="news-next-date-filter" value="<?= htmlspecialchars($date_filter ?? '') ?>">
    <input type="hidden" id="news-has-more" value="1">
    <?php else: ?>
    <div class="col-12 text-center mt-4 mb-4">
        <div class="news-sentinel" style="opacity:0.5;">
            <i class="bx bx-check-circle text-success"></i> All news loaded
        </div>
    </div>
    <input type="hidden" id="news-has-more" value="0">
    <?php endif; ?>
    <?php endif; ?>
<?php echo '<div id="news-total-count" data-total="' . (int) $total . '"></div>'; ?>