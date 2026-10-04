<?php
require_once __DIR__ . '/../../../../src/load.php';
require_once __DIR__ . '/../../../../src/utils/errors.php';

/* ------------------------------------------------------------------ filters */
$q = [
    'status'   => trim((string)($_GET['status'] ?? 'open')),
    'context'  => trim((string)($_GET['context'] ?? '')),
    'severity' => trim((string)($_GET['severity'] ?? '')),
    'user'     => trim((string)($_GET['user'] ?? '')),
    'q'        => trim((string)($_GET['q'] ?? '')),
    'from'     => trim((string)($_GET['from'] ?? '')),
    'to'       => trim((string)($_GET['to'] ?? '')),
];
if ($q['status'] !== 'all' && !isset(error_statuses()[$q['status']])) $q['status'] = 'open';

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;

$filter  = errors_filter($q);
$rows    = errors_rows($filter, $page, $perPage);
$count   = errors_count($filter);
$pages   = max(1, (int)ceil($count / $perPage));
$counts  = errors_counts();
$statuses= error_statuses();
$contexts= error_contexts();

/* Open errors are always counted, whatever the filter says. */
$openFilter = ['status' => 'open'];
$openCount  = errors_count($openFilter);

$fmtWhen = function ($v) {
    if ($v instanceof \MongoDB\BSON\UTCDateTime) return $v->toDateTime()->format('d M Y, H:i');
    if ($v instanceof DateTimeInterface) return $v->format('d M Y, H:i');
    if (is_int($v) || is_float($v)) return date('d M Y, H:i', (int)$v);
    if ($v === null || !is_scalar($v)) return '—';
    $t = strtotime((string)$v);
    return $t ? date('d M Y, H:i', $t) : '—';
};

$sevColor = ['error' => 'danger', 'warning' => 'warning', 'info' => 'info'];
$statusColor = ['open' => 'danger', 'acknowledged' => 'warning', 'resolved' => 'success'];

$qs = $q;
$buildQuery = function (array $overrides) use ($qs) {
    $m = array_merge($qs, $overrides);
    $m['page'] = 1;
    return $m;
};

$ctxLabel = function (string $ctx): string {
    $map = [
        'quiz.generate'       => 'Quiz generation',
        'roadmaps.generate'   => 'Roadmap generation',
        'roadmaps.ask'        => 'Roadmap chat',
        'learnAI.ask'         => 'LearnAI chat',
        'exception.api'       => 'API exception',
        'exception.page'      => 'Page exception',
        'user_visible'        => 'User-visible error',
        'quiz.job_status'     => 'Quiz job status',
    ];
    return $map[$ctx] ?? $ctx;
};
?>
<style>
.tx-card { border: 1px solid rgba(var(--cui-body-color-rgb,255,255,255),.10); border-radius: 1rem; }
.tx-summary { border: 1px solid rgba(var(--cui-body-color-rgb,255,255,255),.10); border-radius: 1.25rem;
              padding: 1.1rem 1.2rem; }
.tx-summary .tx-sum-val { font-size: 1.45rem; font-weight: 700; letter-spacing: -.5px; line-height: 1.2; }
.tx-summary .tx-sum-lbl { font-size: .74rem; text-transform: uppercase; letter-spacing: .06em;
                          color: var(--cui-body-color); opacity: .6; font-weight: 600; }
.tx-table thead th { font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; white-space: nowrap; }
.tx-table tbody tr:hover { background: rgba(var(--cui-body-color-rgb,255,255,255),.04); }
.tx-empty { border: 1px dashed rgba(var(--cui-body-color-rgb,255,255,255),.16); border-radius: 1rem;
            padding: 2.5rem 1.5rem; text-align: center; }
.tx-filter label { font-size: .74rem; font-weight: 600; opacity: .7; margin-bottom: .2rem; }
.tx-page { border: 1px solid rgba(var(--cui-body-color-rgb,255,255,255),.20); border-radius: 999px;
           min-width: 30px; height: 30px; padding: 0 .55rem; display: inline-flex; align-items: center;
           justify-content: center; font-size: .8rem; text-decoration: none; color: var(--cui-body-color); }
.tx-page:hover { background: rgba(var(--cui-body-color-rgb,255,255,255),.10); color: var(--cui-body-color); }
.tx-page.active { background: rgba(var(--cui-primary-rgb,33,150,243),.20);
                  border-color: rgba(var(--cui-primary-rgb,33,150,243),.55); font-weight: 700; }
.tx-page.disabled { opacity: .35; pointer-events: none; }
.err-detail { background: rgba(var(--cui-body-color-rgb,255,255,255),.04);
              border: 1px solid rgba(var(--cui-body-color-rgb,255,255,255),.10);
              border-radius: .75rem; padding: .75rem; white-space: pre-wrap;
              word-break: break-word; font-size: .78rem; }
summary::-webkit-details-marker { display: none; }
summary::marker { content: ''; }
.err-src { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .74rem;
             background: rgba(var(--cui-body-color-rgb,255,255,255),.06); border-radius: .4rem;
             padding: .05rem .4rem; }
.err-sec-lbl { font-size: .7rem; text-transform: uppercase; letter-spacing: .07em; font-weight: 700;
               opacity: .55; margin-bottom: .3rem; }
.err-raw { background: rgba(220, 53, 69, .10); border: 1px solid rgba(220, 53, 69, .35);
           border-radius: .75rem; padding: .75rem .85rem; white-space: pre-wrap; word-break: break-word;
           font-size: .78rem; color: var(--cui-body-color); max-height: 260px; overflow: auto; }
.err-kv { display: flex; gap: .6rem; font-size: .8rem; padding: .22rem 0;
          border-bottom: 1px dashed rgba(var(--cui-body-color-rgb,255,255,255),.10); }
.err-kv:last-child { border-bottom: 0; }
.err-kv dt { min-width: 88px; opacity: .6; font-weight: 600; margin: 0; }
.err-kv dd { margin: 0; word-break: break-word; }
.err-trace { background: rgba(var(--cui-body-color-rgb,255,255,255),.05); border-radius: .75rem;
             padding: .7rem .8rem; font-size: .74rem; white-space: pre-wrap; word-break: break-word;
             max-height: 220px; overflow: auto; opacity: .85; }
.err-ref { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-weight: 700; letter-spacing: .02em; }
</style>

<!-- ================================ Banner ================================ -->
<div class="blur banner mb-3 rounded-0 border-bottom border-secondary border-opacity-10">
    <div class="card-body p-0" style="margin-left:1rem;margin-right:1rem;">
        <div class="container-fluid pt-3 pb-1">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="d-flex align-items-center gap-3">
                    <div class="position-relative flex-shrink-0">
                        <div class="avatar lab-header-avatar">
                            <div class="avatar-img d-flex align-items-center justify-content-center bg-dark bg-opacity-25 rounded-circle p-2">
                                <i class='bx bx-error-circle'></i>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex flex-column gap-1">
                        <h3 class="fw-bold mb-0 ls-tight lab-header-title">Error Monitor</h3>
                        <div class="d-flex flex-wrap align-items-center gap-2 small">
                            <span class="text-secondary opacity-75">Admin Panel / Platform / Errors</span>
                            <span class="badge rounded-pill text-bg-secondary">Admin queue</span>
                        </div>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <?php if ($openCount > 0): ?>
                    <span class="badge rounded-pill text-bg-danger"><?= number_format($openCount) ?> open</span>
                    <?php endif; ?>
                    <a href="/admin" hx-boost="false" class="btn btn-sm btn-outline-secondary border-secondary border-opacity-25 rounded-pill px-3 text-nowrap">
                        <i class='bx bx-left-arrow-circle me-1'></i>Admin Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid px-4 pb-4">

    <!-- =============================== Filters =============================== -->
    <form class="tx-card blur p-3 mb-4" method="get" action="/admin/errors" hx-boost="false" autocomplete="off">
        <div class="row g-3 align-items-end">
            <div class="col-lg-2 col-md-3 tx-filter">
                <label for="errStatus">Status</label>
                <select id="errStatus" name="status" class="form-select form-select-sm bg-transparent border-secondary border-opacity-25">
                    <?php foreach ($statuses as $key => $label): ?>
                    <option value="<?= htmlspecialchars($key) ?>" <?= $q['status'] === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                    <option value="all" <?= $q['status'] === 'all' ? 'selected' : '' ?>>All</option>
                </select>
            </div>

            <div class="col-lg-2 col-md-3 tx-filter">
                <label for="errContext">Type</label>
                <select id="errContext" name="context" class="form-select form-select-sm bg-transparent border-secondary border-opacity-25">
                    <option value="">All types</option>
                    <?php foreach ($contexts as $ctx): ?>
                    <option value="<?= htmlspecialchars($ctx) ?>" <?= $q['context'] === $ctx ? 'selected' : '' ?>><?= htmlspecialchars($ctxLabel($ctx)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-lg-2 col-md-3 tx-filter">
                <label for="errSeverity">Severity</label>
                <select id="errSeverity" name="severity" class="form-select form-select-sm bg-transparent border-secondary border-opacity-25">
                    <option value="">All</option>
                    <option value="error"   <?= $q['severity'] === 'error'   ? 'selected' : '' ?>>Error</option>
                    <option value="warning" <?= $q['severity'] === 'warning' ? 'selected' : '' ?>>Warning</option>
                    <option value="info"    <?= $q['severity'] === 'info'    ? 'selected' : '' ?>>Info</option>
                </select>
            </div>

            <div class="col-lg-3 col-md-4 tx-filter">
                <label for="errUser">User</label>
                <input type="text" id="errUser" name="user" class="form-control form-control-sm bg-transparent border-secondary border-opacity-25"
                       value="<?= htmlspecialchars($q['user']) ?>" placeholder="Email, username or reference…">
            </div>

            <div class="col-lg-3 col-md-4 tx-filter">
                <label for="errQ">Search</label>
                <input type="text" id="errQ" name="q" class="form-control form-control-sm bg-transparent border-secondary border-opacity-25"
                       value="<?= htmlspecialchars($q['q']) ?>" placeholder="Reference or message…">
            </div>

            <div class="col-lg-2 col-md-3 tx-filter">
                <label for="errFrom">From</label>
                <input type="date" id="errFrom" name="from" value="<?= htmlspecialchars($q['from']) ?>"
                       class="form-control form-control-sm bg-transparent border-secondary border-opacity-25">
            </div>

            <div class="col-lg-2 col-md-3 tx-filter">
                <label for="errTo">To</label>
                <input type="date" id="errTo" name="to" value="<?= htmlspecialchars($q['to']) ?>"
                       class="form-control form-control-sm bg-transparent border-secondary border-opacity-25">
            </div>

            <div class="col-auto d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3">
                    Apply
                </button>
                <a href="/admin/errors" hx-boost="false" class="btn btn-sm btn-outline-secondary border-secondary border-opacity-25 rounded-pill px-3">
                    Clear
                </a>
            </div>
        </div>
    </form>

    <!-- ============================ Summary cards ============================ -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
            <div class="card border-0 blur shadow-sm tx-summary h-100">
                <div class="tx-sum-lbl mb-1">Open</div>
                <div class="tx-sum-val <?= $counts['open'] > 0 ? 'text-danger' : '' ?>"><?= number_format($counts['open']) ?></div>
                <div class="small text-body-secondary opacity-75 mt-1">Awaiting review</div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border-0 blur shadow-sm tx-summary h-100">
                <div class="tx-sum-lbl mb-1">Acknowledged</div>
                <div class="tx-sum-val text-warning"><?= number_format($counts['acknowledged']) ?></div>
                <div class="small text-body-secondary opacity-75 mt-1">Seen, still being worked</div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border-0 blur shadow-sm tx-summary h-100">
                <div class="tx-sum-lbl mb-1">Resolved</div>
                <div class="tx-sum-val text-success"><?= number_format($counts['resolved']) ?></div>
                <div class="small text-body-secondary opacity-75 mt-1">History</div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border-0 blur shadow-sm tx-summary h-100">
                <div class="tx-sum-lbl mb-1">Today</div>
                <div class="tx-sum-val"><?= number_format($counts['today']) ?></div>
                <div class="small text-body-secondary opacity-75 mt-1">
                    <?= number_format($counts['events']) ?> occurrences logged in total
                </div>
            </div>
        </div>
    </div>

    <!-- =============================== Table =============================== -->
    <div class="tx-card blur overflow-hidden mb-3">
        <div class="table-responsive">
            <table class="table align-middle mb-0 tx-table small">
                <thead class="table-dark opacity-75">
                    <tr>
                        <th class="ps-4">Time</th>
                        <th>Reference</th>
                        <th>Context</th>
                        <th>User</th>
                        <th>Message</th>
                        <th class="text-center">Hits</th>
                        <th>Status</th>
                        <th class="pe-4 text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?>
                    <tr>
                        <td colspan="8" class="p-4">
                            <div class="tx-empty text-body-secondary">
                                <i class='bx bx-check-shield fs-1 d-block mb-2 opacity-50'></i>
                                Nothing in this view.
                                <div class="small mt-1 opacity-75">
                                    <?= $q['status'] === 'open'
                                        ? 'No open errors — every failure seen so far has been handled.'
                                        : 'Adjust the filter to widen the search.' ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php else: foreach ($rows as $i => $row):
                        $ref     = (string)($row['ref'] ?? '');
                        $ctx     = (string)($row['context'] ?? '');
                        $sev     = (string)($row['severity'] ?? 'error');
                        $status  = (string)($row['status'] ?? 'open');
                        $msg     = (string)($row['message'] ?? '');
                        $detail  = (string)($row['detail'] ?? '');
                        $extra   = (array)($row['extra'] ?? []);
                        $em      = (string)($row['user_email'] ?? '');
                        $name    = (string)($row['username'] ?? '') ?: $em;
                        $hits    = (int)($row['count'] ?? 1);
                        $rid     = 'err-' . $i . '-' . preg_replace('/[^a-z0-9]/i', '', $ref);
                    ?>
                    <tr data-ref="<?= htmlspecialchars($ref) ?>">
                        <td class="ps-4 text-nowrap text-body-secondary"><?= htmlspecialchars($fmtWhen($row['created_at'] ?? null)) ?></td>
                        <td class="text-nowrap"><span class="err-ref"><?= htmlspecialchars($ref) ?></span></td>
                        <td class="text-nowrap">
                            <span class="badge rounded-pill text-bg-<?= $sevColor[$sev] ?? 'secondary' ?> me-1"><?= htmlspecialchars(ucfirst($sev)) ?></span>
                            <span class="text-body-secondary"><?= htmlspecialchars($ctxLabel($ctx)) ?></span>
                        </td>
                        <td class="text-nowrap">
                            <?php if ($em !== ''): ?>
                                <?php if (AuthMiddleware::isAdmin()): ?>
                                <a href="/admin/user/<?= urlencode($em) ?>" hx-boost="false" class="fw-semibold text-decoration-none"><?= htmlspecialchars($name) ?></a>
                                <?php else: ?>
                                <span class="fw-semibold"><?= htmlspecialchars($name) ?></span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-body-secondary opacity-75">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            // Where it happened — shown compactly in the row, fully in the modal.
                            $whereFile = (string)($extra['file'] ?? '');
                            $whereLine = (int)($extra['line'] ?? 0);
                            $whereCls  = (string)($extra['class'] ?? '');
                            if ($whereFile === '' && !empty($extra['where'])) {
                                $wp = explode(':', (string)$extra['where']);
                                $whereFile = $wp[0] ?? '';
                                $whereLine = (int)($wp[1] ?? 0);
                            }
                            $shortFile = $whereFile;
                            if ($shortFile !== '' && strpos($shortFile, '/htdocs/') !== false) {
                                $shortFile = substr($shortFile, strpos($shortFile, '/htdocs/') + 8);
                            }
                            $shortWhere = $shortFile !== ''
                                ? $shortFile . ($whereLine > 0 ? ':' . $whereLine : '')
                                : '';
                            $payload = [
                                'ref'      => $ref,
                                'status'   => $status,
                                'severity' => $sev,
                                'context'  => $ctxLabel($ctx),
                                'message'  => $msg,
                                'raw'      => $detail,
                                'where'    => $shortWhere,
                                'class'    => $whereCls,
                                'trace'    => (string)($extra['trace'] ?? ''),
                                'url'      => (string)($row['url'] ?? ''),
                                'ip'       => (string)($row['ip'] ?? ''),
                                'user'     => $em !== '' ? ($name !== $em ? $name . ' (' . $em . ')' : $em) : '',
                                'when'     => $fmtWhen($row['created_at'] ?? null),
                                'hits'     => $hits,
                                'handled'  => !empty($row['handled_by'])
                                    ? $row['handled_by'] . ' · ' . $fmtWhen($row['handled_at'] ?? null)
                                    : '',
                                'extra'    => $extra,
                            ];
                            ?>
                            <div class="text-truncate" style="max-width:420px;" title="<?= htmlspecialchars($msg) ?>">
                                <?= htmlspecialchars($msg) ?>
                            </div>
                            <?php if ($shortWhere !== ''): ?>
                            <div class="small text-body-secondary text-truncate mt-1" style="max-width:420px;"
                                 title="<?= htmlspecialchars(($extra['file'] ?? '') . ($whereLine > 0 ? ':' . $whereLine : '')) ?>">
                                <i class='bx bx-error-circle me-1'></i><code class="err-src"><?= htmlspecialchars($shortWhere) ?></code><?php if ($whereCls !== ''): ?><span class="opacity-75"> · <?= htmlspecialchars($whereCls) ?></span><?php endif; ?>
                            </div>
                            <?php endif; ?>
                            <button type="button" class="btn btn-sm btn-outline-secondary border-secondary border-opacity-25 rounded-pill px-2 mt-2 err-open"
                                    id="<?= htmlspecialchars($rid) ?>-btn"
                                    data-payload="<?= htmlspecialchars($rid) ?>-payload"
                                    data-coreui-toggle="modal" data-coreui-target="#errDetailModal">
                                <i class='bx bx-expand-alt me-1'></i>Details
                            </button>
                            <script type="application/json" id="<?= htmlspecialchars($rid) ?>-payload"><?= json_encode($payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
                        </td>
                        <td class="text-center text-nowrap">
                            <span class="badge rounded-pill text-bg-secondary"><?= number_format($hits) ?></span>
                        </td>
                        <td class="text-nowrap">
                            <span class="badge rounded-pill text-bg-<?= $statusColor[$status] ?? 'secondary' ?>">
                                <?= htmlspecialchars($statuses[$status] ?? ucfirst($status)) ?>
                            </span>
                        </td>
                        <td class="pe-4 text-end text-nowrap">
                            <?php if ($status !== 'acknowledged'): ?>
                            <button type="button" class="btn btn-sm btn-outline-warning rounded-pill px-2 me-1 err-act" data-ref="<?= htmlspecialchars($ref) ?>" data-status="acknowledged">
                                <i class='bx bx-error-circle me-1'></i>Acknowledge
                            </button>
                            <?php endif; ?>
                            <?php if ($status !== 'resolved'): ?>
                            <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-2 me-1 err-act" data-ref="<?= htmlspecialchars($ref) ?>" data-status="resolved">
                                <i class='bx bx-check me-1'></i>Resolve
                            </button>
                            <?php else: ?>
                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-2 me-1 err-act" data-ref="<?= htmlspecialchars($ref) ?>" data-status="open">
                                <i class='bx bx-revision me-1'></i>Re-open
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ============================ Pagination ============================ -->
    <?php if ($pages > 1): $link = function (array $overrides) { return '/admin/errors?' . http_build_query($overrides); }; ?>
    <nav class="d-flex justify-content-between align-items-center small text-body-secondary" aria-label="Error pages">
        <div>
            Page <?= $page ?> of <?= number_format($pages) ?> · <?= number_format($count) ?> errors
        </div>
        <div class="d-flex gap-1 align-items-center">
            <?php if ($page > 1): ?>
                <a class="tx-page" href="<?= htmlspecialchars($link($buildQuery(['page' => $page - 1]))) ?>" hx-boost="false" aria-label="Previous page"><i class='bx bx-chevron-left'></i></a>
            <?php endif; ?>
            <?php
            $start = max(1, $page - 2);
            $end   = min($pages, $page + 2);
            if ($start > 1): ?>
                <a class="tx-page" href="<?= htmlspecialchars($link($buildQuery(['page' => 1]))) ?>" hx-boost="false">1</a>
                <?php if ($start > 2): ?><span class="px-1">…</span><?php endif; ?>
            <?php endif;
            for ($i = $start; $i <= $end; $i++): ?>
                <a class="tx-page <?= $i === $page ? 'active' : '' ?>" href="<?= htmlspecialchars($link($buildQuery(['page' => $i]))) ?>" hx-boost="false"><?= $i ?></a>
            <?php endfor;
            if ($end < $pages): ?>
                <?php if ($end < $pages - 1): ?><span class="px-1">…</span><?php endif; ?>
                <a class="tx-page" href="<?= htmlspecialchars($link($buildQuery(['page' => $pages]))) ?>" hx-boost="false"><?= $pages ?></a>
            <?php endif; ?>
            <?php if ($page < $pages): ?>
                <a class="tx-page" href="<?= htmlspecialchars($link($buildQuery(['page' => $page + 1]))) ?>" hx-boost="false" aria-label="Next page"><i class='bx bx-chevron-right'></i></a>
            <?php endif; ?>
        </div>
    </nav>
    <?php endif; ?>

    <div class="small text-body-secondary opacity-75 mt-3">
        <i class='bx bx-info-circle me-1'></i>
        Learners never see this text — they get a plain-English message and a reference code.
        Acknowledging marks an error as seen; Resolving files it away. Re-opening pulls it back into the queue.
    </div>
</div>

<!-- ============================== Detail modal ============================== -->
<div class="modal fade" id="errDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-bottom border-opacity-10 p-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="avatar rounded-circle d-flex align-items-center justify-content-center"
                         style="width:44px;height:44px;background:rgba(220,53,69,.15);">
                        <i class='bx bx-error-circle fs-4 text-danger'></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="errModalTitle">Error</h5>
                        <div class="small text-body-secondary" id="errModalSub"></div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-coreui-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-4" id="errModalBody"></div>

            <div class="modal-footer border-0 p-4 pt-0 d-flex flex-wrap gap-2 justify-content-end">
                <button type="button" class="btn btn-sm btn-outline-warning rounded-pill px-3 err-act" id="errModalAck" data-status="acknowledged" data-ref="" hidden>
                    <i class='bx bx-error-circle me-1'></i>Acknowledge
                </button>
                <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-3 err-act" id="errModalResolve" data-status="resolved" data-ref="" hidden>
                    <i class='bx bx-check me-1'></i>Resolve
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 err-act" id="errModalReopen" data-status="open" data-ref="" hidden>
                    <i class='bx bx-revision me-1'></i>Re-open
                </button>
                <button type="button" class="btn btn-sm btn-secondary rounded-pill px-4" data-coreui-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const CSRF = <?= json_encode(Session::csrfToken()) ?>;
    const notify = (m, t, ty) => { if (window.TomNotify) TomNotify.show(m, t, ty || 'success', 4000); };

    /* ------------------------------------------------ detail modal */
    const esc = v => String(v === null || v === undefined ? '' : v)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');

    const section = (label, body) =>
        '<div class="mb-3"><div class="err-sec-lbl">' + label + '</div>' + body + '</div>';

    const kv = rows => '<dl class="mb-0">' + rows.map(([k, v]) =>
        '<div class="err-kv"><dt>' + esc(k) + '</dt><dd>' + esc(v) + '</dd></div>').join('') + '</dl>';

    const statusColor = { open: 'danger', acknowledged: 'warning', resolved: 'success' };
    const sevColor    = { error: 'danger', warning: 'warning', info: 'info' };

    function renderErrorDetail(d) {
        let html = '';

        html += section('What happened',
            '<div class="fw-semibold mb-2">' + esc(d.message) + '</div>' +
            '<div class="d-flex flex-wrap gap-2">' +
            '<span class="badge rounded-pill text-bg-' + esc(sevColor[d.severity] || 'secondary') + '">' + esc((d.severity || 'error')) + '</span>' +
            '<span class="badge rounded-pill text-bg-' + esc(statusColor[d.status] || 'secondary') + '">' + esc(d.status || 'open') + '</span>' +
            '<span class="badge rounded-pill text-bg-secondary">' + esc(d.context) + '</span>' +
            (d.hits > 1 ? '<span class="badge rounded-pill text-bg-secondary">' + esc(d.hits) + ' hits</span>' : '') +
            '</div>');

        if (d.where) {
            html += section('Where',
                '<div class="small mb-1"><code class="err-src">' + esc(d.where) + '</code>' +
                (d.class ? ' <span class="opacity-75">· ' + esc(d.class) + '</span>' : '') + '</div>');
        }

        const meta = [];
        if (d.when)   meta.push(['First seen', d.when]);
        if (d.user)   meta.push(['User', d.user]);
        if (d.url)    meta.push(['Request', d.url]);
        if (d.ip)     meta.push(['Client IP', d.ip]);
        if (d.handled) meta.push(['Handled by', d.handled]);
        if (meta.length) html += section('Details', kv(meta));

        if (d.raw) {
            html += section('Raw error — admin only',
                '<div class="err-raw">' + esc(d.raw) + '</div>');
        }

        const extras = Object.keys(d.extra || {}).filter(k =>
            ['file', 'line', 'where', 'trace', 'class'].indexOf(k) === -1 && d.extra[k] !== '' && d.extra[k] !== null);
        if (extras.length) {
            html += section('Context', kv(extras.map(k => [k, typeof d.extra[k] === 'object' ? JSON.stringify(d.extra[k]) : d.extra[k]])));
        }

        if (d.trace) {
            html += section('Stack trace', '<div class="err-trace">' + esc(d.trace) + '</div>');
        }

        return html;
    }

    document.addEventListener('click', ev => {
        const btn = ev.target.closest('.err-open');
        if (!btn) return;
        const src = document.getElementById(btn.dataset.payload);
        if (!src) return;
        let data;
        try { data = JSON.parse(src.textContent); } catch (e) { return; }

        document.getElementById('errModalTitle').textContent = 'Error ' + data.ref;
        document.getElementById('errModalSub').textContent =
            (data.context || '') + ' · ' + (data.when || '') + (data.user ? ' · ' + data.user : '');
        document.getElementById('errModalBody').innerHTML = renderErrorDetail(data);

        const ack = document.getElementById('errModalAck');
        const res = document.getElementById('errModalResolve');
        const reo = document.getElementById('errModalReopen');
        [ack, res, reo].forEach(b => { b.hidden = true; b.dataset.ref = data.ref; });
        if (data.status !== 'acknowledged') ack.hidden = false;
        if (data.status !== 'resolved') res.hidden = false;
        else reo.hidden = false;
    });

    document.querySelectorAll('.err-act').forEach(btn => {
        btn.addEventListener('click', async () => {
            const ref    = btn.dataset.ref;
            const status = btn.dataset.status;
            btn.disabled = true;
            try {
                const res = await fetch('/api/admin/update_error', {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': CSRF, 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ ref, status })
                });
                const data = await res.json();
                if (data.status === 'success') {
                    notify(ref + ' → ' + status, 'Error updated', 'success');
                    setTimeout(() => location.reload(), 500);
                } else {
                    notify(data.error || 'Could not update that error.', 'Error', 'error');
                    btn.disabled = false;
                }
            } catch (e) {
                notify('Network error — try again.', 'Error', 'error');
                btn.disabled = false;
            }
        });
    });
})();
</script>
