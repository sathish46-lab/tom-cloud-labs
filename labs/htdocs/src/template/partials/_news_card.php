<?php
/**
 * News card partial — shared between initial render and AJAX endpoints.
 * Matches learn AI lesson-card design: liquid-rim card, h-100, shadow-lg, flex-column body.
 *
 * Expected $item array keys:
 *   guid, title, link, source, summary, published_at (UTCDateTime), fetched_at,
 *   category, severity, cve_ids[], deleted_at
 */

$item = $item ?? [];
if (empty($item)) {
    return;
}

$title = htmlspecialchars($item['title'] ?? '', ENT_QUOTES, 'UTF-8');
$link = htmlspecialchars($item['link'] ?? '', ENT_QUOTES, 'UTF-8');
$source = htmlspecialchars($item['source'] ?? '', ENT_QUOTES, 'UTF-8');
$rawSummary = htmlspecialchars($item['summary'] ?? '', ENT_QUOTES, 'UTF-8');
// Strip leading "### Summary" or "## Summary" prefix from feed content
$rawSummary = preg_replace('/^#{1,3}\s*Summary\s*/i', '', $rawSummary);
$rawSummary = trim($rawSummary);
$category = htmlspecialchars($item['category'] ?? 'news', ENT_QUOTES, 'UTF-8');
$severity = htmlspecialchars($item['severity'] ?? 'info', ENT_QUOTES, 'UTF-8');
$guid = htmlspecialchars($item['guid'] ?? '', ENT_QUOTES, 'UTF-8');

// Short summary: 10-15 words max, clean ellipsis
$summaryWords = explode(' ', trim($rawSummary));
if (count($summaryWords) > 15) {
    $summary = implode(' ', array_slice($summaryWords, 0, 15)) . ' …';
} else {
    $summary = trim($rawSummary);
}

// Sanitize source label - show "All Sources" for empty/unknown
if (is_string($source) && $source !== '' && $source !== 'unknown') {
    $sourceLabel = htmlspecialchars(substr($source, 0, 30), ENT_QUOTES, 'UTF-8');
} else {
    $sourceLabel = 'All Sources';
}

// Category label mapping for nicer display
$categoryLabels = [
    'cve' => 'CVE',
    'malware' => 'Malware',
    'breach' => 'Breach',
    'exploit' => 'Exploit',
    'research' => 'Research',
    'policy' => 'Policy',
    'news' => 'News',
    '' => 'News',
];

// Handle category - show friendly name or "Unknown" if not recognized
if ($category !== '' && isset($categoryLabels[$category])) {
    $categoryLabel = $categoryLabels[$category];
} elseif ($category !== '') {
    $categoryLabel = htmlspecialchars($category, ENT_QUOTES, 'UTF-8');
} else {
    $categoryLabel = 'Unknown';
}

$publishedStr = '';
if (!empty($item['published_at'])) {
    $publishedStr = TimeHelper::format($item['published_at']);
}

$cveIds = $item['cve_ids'] ?? [];
$cveHtml = '';
if (!empty($cveIds)) {
    $cveHtml = '<div class="d-flex flex-wrap gap-1 mt-2">';
    foreach ($cveIds as $cve) {
        $cveHtml .= '<span class="badge bg-info text-dark">' . htmlspecialchars($cve, ENT_QUOTES, 'UTF-8') . '</span>';
    }
    $cveHtml .= '</div>';
}

$severityClass = '';
switch ($severity) {
    case 'critical': $severityClass = 'bg-danger text-white'; break;
    case 'high': $severityClass = 'bg-danger text-white'; break;
    case 'medium': $severityClass = 'bg-warning text-dark'; break;
    case 'low': $severityClass = 'bg-info text-dark'; break;
    case 'success': $severityClass = 'bg-success text-white'; break;
    default: $severityClass = 'bg-secondary text-white'; break;
}

$sourceClass = 'bg-secondary text-dark';
$categoryClass = 'bg-primary text-white';
?>
<div class="card liquid-rim news-card hvr-grow shadow-lg border-0" data-guid="<?= $guid ?>">
    <div class="card-body d-flex flex-column p-3">
        <!-- Badges row -->
        <div class="d-flex flex-wrap gap-2 align-items-start mb-2">
            <span class="badge <?= $severityClass ?> rounded-pill fw-bold small px-2 py-1">
                <?= $severity ?>
            </span>
            <span class="badge <?= $sourceClass ?> rounded-pill fw-bold small px-2 py-1">
                <?= $sourceLabel ?>
            </span>
            <span class="badge <?= $categoryClass ?> rounded-pill fw-bold small px-2 py-1">
                <?= $categoryLabel ?>
            </span>
        </div>

        <!-- Title -->
        <h6 class="card-title fw-bold mb-2 text-white">
            <a href="<?= $link ?: '#' ?>" target="_blank" rel="noopener" class="text-decoration-none stretched-link">
                <?= $title ?: 'Untitled' ?>
            </a>
        </h6>

        <!-- Short summary -->
        <?php if ($summary): ?>
        <p class="card-text text-body mb-3 small news-desc-clamp"><?= $summary ?></p>
        <?php endif; ?>

        <?= $cveHtml ?>

        <!-- Footer: date + guid in single line -->
        <div class="d-flex align-items-center justify-content-between pt-3 border-top border-secondary border-opacity-10 mt-auto">
            <small class="text-muted card-footer-line">
                <i class="bx bx-time-five me-1 flex-shrink-0"></i><?= $publishedStr ?: 'Invalid Date' ?>
                <span class="ms-2">·</span>
                <i class="bx bx-link-alt me-1"></i>guid: <code class="text-secondary"><?= substr($guid, 0, 24) ?>…</code>
            </small>
        </div>
    </div>
</div>
