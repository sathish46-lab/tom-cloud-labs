<?php
require_once __DIR__ . '/../../../../src/load.php';
require_once __DIR__ . '/../../../../src/utils/currency.php';

/* ---------------------------------------------------------------- helpers */
if (!function_exists('tx_money')) {
    /** Render a ['zeal'=>int,'jolt'=>int] pair, omitting currencies the filter hid. */
    function tx_money(array $pair, bool $signed = false, string $direction = 'earned'): string
    {
        $parts = [];
        foreach (['zeal' => '🔥', 'jolt' => '⚡'] as $cur => $emoji) {
            $n = (int)($pair[$cur] ?? 0);
            if ($n === 0) continue;
            $parts[] = ($signed && $direction === 'spent' ? '−' : ($signed ? '+' : ''))
                     . number_format(abs($n)) . ' ' . $emoji;
        }
        if (!$parts) $parts[] = '0 🔥';
        return implode(' <span class="text-body-secondary">/</span> ', $parts);
    }
}

$fmtWhen = function ($v) {
    if ($v instanceof \MongoDB\BSON\UTCDateTime) return $v->toDateTime()->format('d M Y, H:i');
    if ($v instanceof DateTimeInterface) return $v->format('d M Y, H:i');
    if (is_int($v) || is_float($v)) return date('d M Y, H:i', (int)$v);
    if ($v === null || !is_scalar($v)) return '—';
    $t = strtotime((string)$v);
    return $t ? date('d M Y, H:i', $t) : '—';
};

/* ---------------------------------------------------------------- filters */
$q = [
    'user'      => trim((string)($_GET['user'] ?? '')),
    'type'      => trim((string)($_GET['type'] ?? '')),
    'direction' => trim((string)($_GET['direction'] ?? '')),
    'currency'  => trim((string)($_GET['currency'] ?? '')),
    'status'    => trim((string)($_GET['status'] ?? 'valid')),
    'from'      => trim((string)($_GET['from'] ?? '')),
    'to'        => trim((string)($_GET['to'] ?? '')),
];
if (!in_array($q['status'], ['all', 'valid', 'invalid'], true)) $q['status'] = 'valid';

$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;

$filter = currency_filter($q);
$rows   = currency_rows($filter, $page, $perPage);
$count  = currency_count($filter);
$pages  = max(1, (int)ceil($count / $perPage));

/* Summary cards ignore the user clause so the per-user card can stand alone. */
$globalQuery    = $q;
$globalQuery['user'] = '';
$totals         = currency_totals(currency_filter($globalQuery));
$userTotals     = $q['user'] !== '' ? currency_totals($filter) : null;

$selectedUser = null;
if ($q['user'] !== '') {
    try {
        $selectedUser = DatabaseConnection::getDefaultDatabase()->users->findOne(['email' => $q['user']])
            ?: DatabaseConnection::getDefaultDatabase()->users->findOne(['username' => $q['user']]);
    } catch (Throwable $e) { /* non-fatal */ }
}

/* ------------------------------------------------------------ option sets */
$types = currency_groups();

$userOptions = [];
try {
    foreach (DatabaseConnection::getDefaultDatabase()->users->find(
        [],
        ['projection' => ['email' => 1, 'username' => 1, 'first_name' => 1, 'last_name' => 1],
         'sort' => ['username' => 1], 'limit' => 2000]
    ) as $u) {
        $em = (string)($u['email'] ?? '');
        if ($em === '') continue;
        $name = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
        if ($name === '') $name = (string)($u['username'] ?? '');
        if ($name === '') $name = $em;
        $userOptions[] = ['value' => $em, 'label' => $name . ' (' . $em . ')'];
    }
} catch (Throwable $e) { /* non-fatal */ }

$groupColors = ['earning' => 'success', 'spending' => 'warning', 'system' => 'secondary'];
$groupLabels = ['earning' => 'Earnings', 'spending' => 'Spending', 'system' => 'System'];

$qs = $q;
$buildQuery = function (array $overrides) use ($qs) {
    $m = array_merge($qs, $overrides);
    $m['page'] = 1;
    return $m;
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
                                <i class='bx bx-transfer'></i>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex flex-column gap-1">
                        <h3 class="fw-bold mb-0 ls-tight lab-header-title">Transaction Monitor</h3>
                        <div class="d-flex flex-wrap align-items-center gap-2 small">
                            <span class="text-secondary opacity-75">Admin Panel / Platform / Transactions</span>
                            <span class="badge rounded-pill text-bg-secondary">Read-only</span>
                        </div>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
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
    <form class="tx-card blur p-3 mb-4" method="get" action="/admin/transactions" hx-boost="false" autocomplete="off">
        <div class="row g-3 align-items-end">
            <div class="col-lg-3 col-md-4 tx-filter">
                <label for="txUser">User</label>
                <input type="text" id="txUser" name="user" class="form-control form-control-sm bg-transparent border-secondary border-opacity-25"
                       list="txUserList" value="<?= htmlspecialchars($q['user']) ?>" placeholder="Type a name or email…">
                <datalist id="txUserList">
                    <?php foreach ($userOptions as $opt): ?>
                    <option value="<?= htmlspecialchars($opt['value']) ?>"><?= htmlspecialchars($opt['label']) ?></option>
                    <?php endforeach; ?>
                </datalist>
            </div>

            <div class="col-lg-2 col-md-3 tx-filter">
                <label for="txType">Type</label>
                <select id="txType" name="type" class="form-select form-select-sm bg-transparent border-secondary border-opacity-25">
                    <option value="">All types</option>
                    <?php foreach ($types as $group => $groupTypes): ?>
                    <optgroup label="<?= htmlspecialchars($groupLabels[$group] ?? $group) ?>">
                        <?php foreach (array_keys($groupTypes) as $type): ?>
                        <option value="<?= htmlspecialchars($type) ?>" <?= $q['type'] === $type ? 'selected' : '' ?>><?= htmlspecialchars($type) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-lg-2 col-md-3 tx-filter">
                <label for="txDirection">Direction</label>
                <select id="txDirection" name="direction" class="form-select form-select-sm bg-transparent border-secondary border-opacity-25">
                    <option value="">All</option>
                    <option value="earned" <?= $q['direction'] === 'earned' ? 'selected' : '' ?>>Earned</option>
                    <option value="spent"  <?= $q['direction'] === 'spent'  ? 'selected' : '' ?>>Spent</option>
                </select>
            </div>

            <div class="col-lg-2 col-md-3 tx-filter">
                <label for="txCurrency">Currency</label>
                <select id="txCurrency" name="currency" class="form-select form-select-sm bg-transparent border-secondary border-opacity-25">
                    <option value="">All</option>
                    <option value="zeal" <?= $q['currency'] === 'zeal' ? 'selected' : '' ?>>Zeal 🔥</option>
                    <option value="jolt" <?= $q['currency'] === 'jolt' ? 'selected' : '' ?>>Jolt ⚡</option>
                </select>
            </div>

            <div class="col-lg-3 col-md-4 tx-filter">
                <label for="txStatus">Status</label>
                <select id="txStatus" name="status" class="form-select form-select-sm bg-transparent border-secondary border-opacity-25">
                    <option value="valid"   <?= $q['status'] === 'valid'   ? 'selected' : '' ?>>Valid (default)</option>
                    <option value="all"     <?= $q['status'] === 'all'     ? 'selected' : '' ?>>All</option>
                    <option value="invalid" <?= $q['status'] === 'invalid' ? 'selected' : '' ?>>Invalid</option>
                </select>
            </div>

            <div class="col-lg-2 col-md-3 tx-filter">
                <label for="txFrom">From</label>
                <input type="date" id="txFrom" name="from" class="form-control form-control-sm bg-transparent border-secondary border-opacity-25"
                       value="<?= htmlspecialchars($q['from']) ?>">
            </div>

            <div class="col-lg-2 col-md-3 tx-filter">
                <label for="txTo">To</label>
                <input type="date" id="txTo" name="to" class="form-control form-control-sm bg-transparent border-secondary border-opacity-25"
                       value="<?= htmlspecialchars($q['to']) ?>">
            </div>

            <div class="col-lg-3 col-md-6 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary fw-semibold rounded-pill px-4">
                    <i class='bx bx-filter-alt me-1'></i>Apply
                </button>
                <a href="/admin/transactions" hx-boost="false" class="btn btn-sm btn-outline-secondary border-secondary border-opacity-25 rounded-pill px-3">
                    Clear
                </a>
            </div>
        </div>
    </form>

    <!-- ============================ Summary cards ============================ -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
            <div class="card border-0 blur shadow-sm tx-summary h-100">
                <div class="tx-sum-lbl mb-1">Total Earned</div>
                <div class="tx-sum-val text-success"><?= tx_money($totals['earned'] ?? []) ?></div>
                <div class="small text-body-secondary opacity-75 mt-1"><?= number_format((int)($totals['earned']['n'] ?? 0)) ?> transactions</div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border-0 blur shadow-sm tx-summary h-100">
                <div class="tx-sum-lbl mb-1">Total Spent</div>
                <div class="tx-sum-val text-danger"><?= tx_money($totals['spent'] ?? []) ?></div>
                <div class="small text-body-secondary opacity-75 mt-1"><?= number_format((int)($totals['spent']['n'] ?? 0)) ?> transactions</div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border-0 blur shadow-sm tx-summary h-100">
                <div class="tx-sum-lbl mb-1">Net</div>
                <?php $net = ['zeal' => (int)($totals['earned']['zeal'] ?? 0) - (int)($totals['spent']['zeal'] ?? 0),
                              'jolt' => (int)($totals['earned']['jolt'] ?? 0) - (int)($totals['spent']['jolt'] ?? 0)]; ?>
                <div class="tx-sum-val <?= $net['zeal'] + $net['jolt'] < 0 ? 'text-danger' : 'text-success' ?>">
                    <?= tx_money($net, true, ($net['zeal'] + $net['jolt']) < 0 ? 'spent' : 'earned') ?>
                </div>
                <div class="small text-body-secondary opacity-75 mt-1">Earned − spent</div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border-0 blur shadow-sm tx-summary h-100">
                <div class="tx-sum-lbl mb-1">Transactions</div>
                <div class="tx-sum-val"><?= number_format($count) ?></div>
                <div class="small text-body-secondary opacity-75 mt-1">
                    <?= $q['user'] !== '' ? 'matching this user + filters' : 'platform-wide for these filters' ?>
                </div>
            </div>
        </div>
    </div>

    <?php if ($userTotals !== null): ?>
    <div class="card border-0 blur shadow-sm tx-summary mb-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <div class="tx-sum-lbl mb-1">
                This user — <?= htmlspecialchars((string)($selectedUser['username'] ?? $q['user'])) ?>
            </div>
            <div class="fw-semibold small text-body-secondary text-break"><?= htmlspecialchars($q['user']) ?></div>
        </div>
        <div class="d-flex gap-4">
            <div>
                <div class="tx-sum-lbl mb-1">Earned</div>
                <div class="fw-bold text-success"><?= tx_money($userTotals['earned'] ?? []) ?></div>
            </div>
            <div>
                <div class="tx-sum-lbl mb-1">Spent</div>
                <div class="fw-bold text-danger"><?= tx_money($userTotals['spent'] ?? []) ?></div>
            </div>
            <div>
                <div class="tx-sum-lbl mb-1">Transactions</div>
                <div class="fw-bold"><?= number_format($count) ?></div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- =============================== Table =============================== -->
    <div class="tx-card blur overflow-hidden mb-3">
        <div class="table-responsive">
            <table class="table align-middle mb-0 tx-table small">
                <thead class="table-dark opacity-75">
                    <tr>
                        <th class="ps-4">Date</th>
                        <th>Direction</th>
                        <th>User</th>
                        <th class="text-end">Amount</th>
                        <th>Type</th>
                        <th>Description</th>
                        <th class="pe-4">Valid</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?>
                    <tr>
                        <td colspan="7" class="p-4">
                            <div class="tx-empty text-body-secondary">
                                <i class='bx bx-transfer fs-1 d-block mb-2 opacity-50'></i>
                                No transactions match these filters.
                                <div class="small mt-1 opacity-75">Adjust the filter, or widen the date range.</div>
                            </div>
                        </td>
                    </tr>
                    <?php else: foreach ($rows as $row):
                        $dir   = (string)($row['direction'] ?? '');
                        $cur   = (string)($row['currency'] ?? 'zeal');
                        $amt   = (int)($row['amount'] ?? 0);
                        $type  = (string)($row['type'] ?? '');
                        $grp   = (string)($row['group'] ?? currency_group_of($type));
                        $em    = (string)($row['user_email'] ?? '');
                        $name  = (string)($row['username'] ?? $em);
                        $valid = (bool)($row['valid'] ?? true);
                        $desc  = (string)($row['description'] ?? '');
                        $reason= (string)($row['reason'] ?? '');
                    ?>
                    <tr>
                        <td class="ps-4 text-nowrap text-body-secondary"><?= htmlspecialchars($fmtWhen($row['created_at'] ?? null)) ?></td>
                        <td class="text-nowrap">
                            <span class="badge rounded-pill text-bg-<?= $dir === 'earned' ? 'success' : 'danger' ?>">
                                <?= $dir === 'earned' ? 'Earned' : 'Spent' ?>
                            </span>
                        </td>
                        <td class="text-nowrap">
                            <a href="/admin/user/<?= urlencode($em) ?>" hx-boost="false" class="fw-semibold text-decoration-none"><?= htmlspecialchars($name) ?></a>
                            <?php if ($name !== $em): ?><div class="small text-body-secondary opacity-75 text-truncate" style="max-width:180px;"><?= htmlspecialchars($em) ?></div><?php endif; ?>
                        </td>
                        <td class="text-end text-nowrap fw-semibold <?= $dir === 'earned' ? 'text-success' : 'text-danger' ?>">
                            <?= $dir === 'earned' ? '+' : '−' ?><?= number_format($amt) ?> <?= currency_emoji($cur) ?>
                        </td>
                        <td class="text-nowrap">
                            <span class="badge rounded-pill text-bg-<?= $groupColors[$grp] ?? 'secondary' ?>" title="<?= htmlspecialchars($groupLabels[$grp] ?? ucfirst($grp)) ?>">
                                <?= htmlspecialchars($type) ?>
                            </span>
                        </td>
                        <td>
                            <?= htmlspecialchars($desc !== '' ? $desc : '—') ?>
                            <?php if ($reason !== '' && $reason !== $desc): ?>
                                <div class="small text-body-secondary opacity-75"><i class='bx bx-message-rounded-dots me-1'></i><?= htmlspecialchars($reason) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($row['actor'])): ?>
                                <div class="small text-body-secondary opacity-75"><i class='bx bx-user me-1'></i><?= htmlspecialchars((string)$row['actor']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="pe-4 text-center">
                            <?php if ($valid): ?>
                                <i class='bx bx-check-circle text-success' title="Valid"></i>
                            <?php else: ?>
                                <i class='bx bx-x-circle text-danger' title="Invalid"></i>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ============================ Pagination ============================ -->
    <?php if ($pages > 1): $link = function (array $overrides) { return '/admin/transactions?' . http_build_query($overrides); }; ?>
    <nav class="d-flex justify-content-between align-items-center small text-body-secondary" aria-label="Transaction pages">
        <div>
            Page <?= $page ?> of <?= number_format($pages) ?> · <?= number_format($count) ?> transactions
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
        Transactions are read-only — there is no button to top someone up, correct a balance or undo a transaction here.
        Adjustments are made from a user's page, and appear here with their reason.
    </div>
</div>
