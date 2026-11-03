<?php
require_once __DIR__ . '/../../../../src/load.php';

/**
 * News Fetch Logs — Admin page showing fetch operation history.
 */

$from  = $_GET['from'] ?? '';
$to    = $_GET['to'] ?? '';
$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = 25;

// Fetch log data directly from DB
try {
    $db = DatabaseConnection::getDefaultDatabase();
    $col = $db->selectCollection('news_fetch_logs');

    $filter = [];
    if ($from) {
        $f = new \DateTimeImmutable($from);
        $filter['started_at'] = ['$gte' => $f];
    }
    if ($to) {
        $t = new \DateTimeImmutable($to);
        $t->modify('+1 day');
        if (isset($filter['started_at']['$gte'])) {
            $filter['started_at']['$lt'] = $t;
        } else {
            $filter['started_at'] = ['$lt' => $t];
        }
    }

    $total = $col->countDocuments($filter);
    $cursor = $col->find($filter, [
        'sort' => ['started_at' => -1],
        'skip' => ($page - 1) * $limit,
        'limit' => $limit,
    ]);

    $items = [];
    foreach ($cursor as $doc) {
        $rawSources = $doc['sources'] ?? [];
        // BSONArray from MongoDB isn't a plain PHP array — convert it
        $sources = is_array($rawSources) ? $rawSources : iterator_to_array($rawSources ?? []);
        $items[] = [
            'started_at' => $doc['started_at'],
            'completed_at' => $doc['completed_at'] ?? $doc['started_at'],
            'sources' => $sources,
            'total_fetched' => $doc['total_fetched'] ?? 0,
            'total_inserted' => $doc['total_inserted'] ?? 0,
            'total_updated' => $doc['total_updated'] ?? 0,
            'total_errors' => $doc['total_errors'] ?? 0,
            'triggered_by' => $doc['triggered_by'] ?? 'system',
        ];
    }
} catch (Exception $e) {
    $items = [];
    $total = 0;
    $logError = $e->getMessage();
}

$totalPages = max(1, (int)ceil($total / $limit));

$hasError = isset($logError);

$formatDate = function($dt) {
    if ($dt instanceof \DateTimeInterface) return $dt->format('Y-m-d H:i');
    if ($dt instanceof \MongoDB\BSON\UTCDateTime) return $dt->toDateTime()->format('Y-m-d H:i');
    if (is_string($dt)) {
        try { return (new \DateTimeImmutable($dt))->format('Y-m-d H:i'); } catch (Exception $e) {}
    }
    return '—';
};
?>
<style>
.nf-card { transition: all 0.2s; }
.nf-card:hover { transform: translateY(-2px); box-shadow: 0 8px 22px rgba(0,0,0,0.28); }
.nf-table thead th { font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; white-space: nowrap; }
.nf-row:hover { background: rgba(var(--cui-body-color-rgb,255,255,255),0.04); }
</style>

<div class="blur banner mb-3 rounded-0 border-bottom border-secondary border-opacity-10">
    <div class="card-body p-0" style="margin-left: 1rem; margin-right: 1rem;">
        <div class="container-fluid pt-3 pb-1">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="position-relative flex-shrink-0">
                        <div class="avatar lab-header-avatar">
                            <div class="avatar-img d-flex align-items-center justify-content-center bg-dark bg-opacity-25 rounded-circle p-2">
                                <i class='bx bx-crown'></i>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex flex-column gap-1">
                        <h3 class="fw-bold mb-0 ls-tight lab-header-title">News Fetch Logs</h3>
                        <div class="d-flex flex-wrap align-items-center gap-2 small">
                            <span class="text-secondary opacity-75">Track every news fetch run with source detail and errors.</span>
                        </div>
                    </div>
                </div>
                <a href="/home" data-no-boost="true"
                   class="btn btn-sm btn-outline-secondary border-secondary border-opacity-25 rounded-pill px-3 text-nowrap">
                    <i class='bx bx-left-arrow-circle me-1'></i>Back to Labs
                </a>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid px-4">

    <!-- Error display -->
    <?php if ($hasError): ?>
    <div class="alert alert-danger border-0 rounded-3 mb-3 small" role="alert">
        <i class='bx bx-error-circle me-2'></i>Error loading logs: <?= htmlspecialchars($logError) ?>
    </div>
    <?php endif; ?>

    <!-- Filters -->
    <form method="get" action="/admin/news-logs" hx-boost="false" autocomplete="off" class="mb-4">
        <div class="row g-2 align-items-end">
            <div class="col-lg-3 col-md-4">
                <label class="form-label small fw-semibold">From</label>
                <input type="date" name="from" value="<?= htmlspecialchars($from) ?>"
                       class="form-control form-control-sm bg-transparent border-secondary border-opacity-25" style="max-width:180px;">
            </div>
            <div class="col-lg-3 col-md-4">
                <label class="form-label small fw-semibold">To</label>
                <input type="date" name="to" value="<?= htmlspecialchars($to) ?>"
                       class="form-control form-control-sm bg-transparent border-secondary border-opacity-25" style="max-width:180px;">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3">Apply</button>
                <?php if ($from || $to): ?>
                <a href="/admin/news-logs" hx-boost="false" class="btn btn-sm btn-outline-secondary border-secondary border-opacity-25 rounded-pill px-3">Clear</a>
                <?php endif; ?>
            </div>
        </div>
    </form>

    <div class="card border-0 overflow-hidden blur shadow-sm nf-table">
        <div class="table-responsive">
            <table class="table align-middle small mb-0">
                <thead class="table-dark opacity-75">
                    <tr>
                        <th class="ps-4">Time</th>
                        <th>Triggered By</th>
                        <th>Sources</th>
                        <th class="text-end">Fetched</th>
                        <th class="text-end">New</th>
                        <th class="text-end">Duplicates</th>
                        <th class="text-end">Errors</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$items): ?>
                    <tr>
                        <td colspan="7" class="p-4 text-center text-muted">
                            <i class='bx bx-inboxbx fs-1 d-block mb-2 opacity-50'></i>
                            No fetch logs found<?= ($from || $to) ? ' for the selected range' : '' ?>.
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($items as $item): ?>
                    <tr class="nf-row">
                        <td class="ps-4 text-nowrap small text-body-secondary">
                            <?= htmlspecialchars($formatDate($item['started_at'] ?? null)) ?>
                        </td>
                        <td>
                            <code class="small fw-semibold text-secondary">
                                <?= htmlspecialchars($item['triggered_by'] ?? 'system') ?>
                            </code>
                        </td>
                        <td class="text-nowrap">
                            <?php if (!empty($item['sources'])): ?>
                                <?php $names = array_map(fn($s) => htmlspecialchars($s['name'] ?? ''), $item['sources']); ?>
                                <span class="badge rounded-pill text-bg-primary"><?= count($item['sources']) ?> sources, <?= implode(', ', array_slice($names, 0, 3)) ?><?= count($item['sources']) > 3 ? '...' : '' ?></span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end text-body-secondary">
                            <?= number_format($item['total_fetched'] ?? 0) ?>
                        </td>
                        <td class="text-end text-success">
                            <?= number_format($item['total_inserted'] ?? 0) ?>
                        </td>
                        <td class="text-end text-warning">
                            <?= number_format($item['total_updated'] ?? 0) ?>
                        </td>
                        <td class="text-end text-danger">
                            <?= number_format($item['total_errors'] ?? 0) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                    <tr>
                        <td colspan="7" class="p-0">
                            <nav class="d-flex justify-content-between align-items-center py-3 px-4" aria-label="Log pages">
                                <div class="small text-body-secondary">
                                    Page <?= $page ?> of <?= $totalPages ?> · <?= number_format($total) ?> logs
                                </div>
                                <div class="d-flex gap-1">
                                    <?php if ($page > 1): ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>"
                                       class="btn btn-sm btn-outline-secondary border-secondary border-opacity-25 rounded-pill px-2">
                                        <i class='bx bx-chevron-left'></i>
                                    </a>
                                    <?php endif; ?>
                                    <?php
                                    $start = max(1, $page - 2);
                                    $end = min($totalPages, $page + 2);
                                    if ($start > 1): ?>
                                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>"
                                           class="btn btn-sm btn-outline-secondary border-secondary border-opacity-25 rounded-pill px-2 <?= $page === 1 ? 'active' : '' ?>">1</a>
                                        <?php if ($start > 2): ?><span class="px-1 text-muted">…</span><?php endif; ?>
                                    <?php endif;
                                    for ($i = $start; $i <= $end; $i++): ?>
                                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"
                                           class="btn btn-sm btn-outline-secondary border-secondary border-opacity-25 rounded-pill px-2 <?= $page === $i ? 'active' : '' ?>"><?= $i ?></a>
                                    <?php endfor;
                                    if ($end < $totalPages): ?>
                                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $totalPages])) ?>"
                                           class="btn btn-sm btn-outline-secondary border-secondary border-opacity-25 rounded-pill px-2 <?= $page === $totalPages ? 'active' : '' ?>"><?= $totalPages ?></a>
                                    <?php endif; ?>
                                    <?php if ($page < $totalPages): ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>"
                                       class="btn btn-sm btn-outline-secondary border-secondary border-opacity-25 rounded-pill px-2">
                                        <i class='bx bx-chevron-right'></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </nav>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>