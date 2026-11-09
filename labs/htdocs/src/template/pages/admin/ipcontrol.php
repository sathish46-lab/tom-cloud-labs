<?php
/**
 * Admin → IP Control — WireGuard tunnel IP pool overview + cleanup.
 *
 * Shows the ip_registry split between available / reserved / dead IPs, who
 * each reserved IP belongs to, and lets an admin release dead or unused
 * reservations back to the pool (audited via AuditLog).
 */

$db = DatabaseConnection::getDefaultDatabase();
$instDb = DatabaseConnection::getClient()->selectDatabase('tom_labs_instances_db');

$esc = static fn ($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

/** Which live resource (if any) is currently pointed at each IP. */
$usageByIp = [];
try {
    foreach ($db->devices->find(['status' => ['$ne' => 'deleted']], ['projection' => ['assigned_ip' => 1]]) as $d) {
        if (!empty($d['assigned_ip'])) $usageByIp[$d['assigned_ip']] = 'Device';
    }
    foreach ($db->machine_labs->find([], ['projection' => ['deploy.internal_ip' => 1]]) as $lab) {
        $ip = $lab['deploy']['internal_ip'] ?? null;
        if ($ip) $usageByIp[$ip] = 'Machine Lab';
    }
    foreach ($instDb->instances->find([], ['projection' => ['deploy.internal_ip' => 1]]) as $inst) {
        $ip = $inst['deploy']['internal_ip'] ?? null;
        if ($ip) $usageByIp[$ip] = 'Instance';
    }
} catch (Throwable $e) { /* non-fatal */ }

/** Emails that still exist. */
$emailExists = [];
try {
    foreach ($db->users->find([], ['projection' => ['email' => 1]]) as $u) {
        $emailExists[(string)$u['email']] = true;
    }
} catch (Throwable $e) { /* non-fatal */ }

$total      = $db->ip_registry->countDocuments([]);
$available  = $db->ip_registry->countDocuments(['status' => 'available']);
$inUseCount = $deadCount = $systemCount = $neverUsedCount = $reservedTotal = 0;

/**
 * Tally every reserved doc and collect only the FIRST rows for initial paint
 * (IP rows load lazily as the card scrolls — see ipc_scroll/loadMore in JS).
 */
$initialRows = [];
foreach ($db->ip_registry->find(['status' => 'reserved'], ['sort' => ['ip_numeric' => 1]]) as $doc) {
    $reservedTotal++;
    $doc = (array)$doc + ['reserved_to' => '', 'email' => '', 'label' => '', 'service_type' => ''];
    $doc['usage'] = $usageByIp[$doc['ip_addr']] ?? '';
    $lu = $doc['last_usage'] ?? null;
    $doc['last_usage_at'] = is_array($lu) ? ($lu['at'] ?? null) : null;
    $doc['last_usage_label'] = is_array($lu) && ($lu['label'] ?? '') !== '' ? (string)$lu['label'] : '';
    if (($doc['reserved_to'] === 'server' || $doc['email'] === 'system@tomweb.in')) {
        $doc['state'] = 'system';
        $systemCount++;
    } else {
        $ownerAlive = $doc['email'] !== '' && isset($emailExists[$doc['email']]);
        $resAlive   = $doc['usage'] !== '';
        if ($ownerAlive || $resAlive) {
            $doc['state'] = 'in_use';
            $inUseCount++;
        } else {
            $doc['state'] = 'dead';
            $deadCount++;
            $doc['dead_reason'] = $ownerAlive ? 'No active device/lab on this IP' : 'Owner account does not exist';
        }
        if (empty($doc['last_usage_at'])) $neverUsedCount++;
    }
    if ($reservedTotal <= 15) $initialRows[] = $doc;
}

/** Next free IPs (network + gateway excluded via ip_numeric > 1). */
$nextFree = iterator_to_array($db->ip_registry->find(
    ['status' => 'available', 'ip_numeric' => ['$gt' => 1]],
    ['sort' => ['ip_numeric' => 1], 'limit' => 48, 'projection' => ['ip_addr' => 1]]
));
$nextFreeIp = $nextFree[0]['ip_addr'] ?? '—';

$csrf = $esc(Session::csrfToken());
$fmtDate = static function ($ts) {
    if (!$ts) return '—';
    if ($ts instanceof MongoDB\BSON\UTCDateTime) $ts = (int)floor($ts->toDateTime()->getTimestamp());
    if ($ts instanceof DateTimeInterface) $ts = $ts->getTimestamp();
    return date('d M Y, H:i', (int)$ts);
};
?>
<style>
.ipc-tile { transition: border-color .15s, transform .15s; }
.ipc-tile:hover { border-color: rgba(var(--cui-primary-rgb, 33,150,243), .4); transform: translateY(-2px); }
.ipc-tile .ipc-val { font-size: 1.5rem; font-weight: 700; line-height: 1.1; letter-spacing: -.5px; }
.ipc-table td, .ipc-table th { white-space: nowrap; }
.ipc-scroll { max-height: 460px; overflow-y: auto; }
.ipc-ip { font-family: var(--cui-font-monospace); font-weight: 600; }
.small-x-tiny { font-size: .68rem; }
.ipc-chip { font-family: var(--cui-font-monospace); font-size: .72rem; padding: .2rem .55rem; border: 1px solid rgba(var(--cui-body-color-rgb,255,255,255), .12); border-radius: 999px; background: rgba(var(--cui-body-color-rgb,255,255,255), .04); }
.ipc-dead-bar { height: 7px; border-radius: 999px; background: rgba(0,0,0,.35); overflow: hidden; }
.ipc-dead-bar > i { display: block; height: 100%; border-radius: 999px; background: linear-gradient(90deg,#ff5f6d,#ff8a5c); }
</style>

<div class="blur banner mb-3 rounded-0 border-bottom border-secondary border-opacity-10">
    <div class="container-fluid pt-3 pb-2 px-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h3 class="fw-bold mb-0 ls-tight lab-header-title"><i class='bx bx-vector me-2'></i>IP Control</h3>
                <span class="text-body-secondary small">WireGuard tunnel pool (<?= $esc($nextFreeIp === '—' ? '172.31.0.0/16' : '172.31.0.0/16') ?>) — registry health, ownership and cleanup.</span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="small text-body-secondary">Next free: <code class="ipc-ip"><?= $esc($nextFreeIp) ?></code></span>
                <?php if ($deadCount > 0): ?>
                <button type="button" id="ipcReleaseDead" class="btn btn-sm btn-danger rounded-pill fw-semibold">
                    <i class='bx bx-recycle me-1'></i>Release dead IPs (<?= $deadCount ?>)
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid px-4 py-4">

    <!-- ============================ Pool health tiles ============================ -->
    <div class="row g-3 mb-4">
        <?php
        $usedPct = $total > 0 ? round(($available / $total) * 100, 2) : 0;
        $tiles = [
            ['Total Pool',           number_format($total), 'bx-collections', 'text-body',     ''],
            ['Free (Available)',     number_format($available), 'bx-check-circle', 'text-success', "{$usedPct}% of pool"],
            ['In Use (Reserved)',    number_format($inUseCount), 'bx-lock-alt', 'text-warning',  'user / lab / instance'],
            ['Dead (Orphaned)',      number_format($deadCount), 'bx-recycle', 'text-danger',    $deadCount > 0 ? 'release them back' : 'nothing to clean'],
            ['System Reserved',      number_format($systemCount), 'bx-shield-quarter', 'text-info', 'WireGuard gateway'],
        ];
        foreach ($tiles as [$label, $value, $icon, $color, $hint]): ?>
        <div class="col-6 col-md-4 col-xl">
            <div class="card border-0 rounded-4 blur shadow-sm ipc-tile p-3 h-100 text-center">
                <div class="ipc-val <?= $color ?>" title="<?= $esc($label) ?>"><?= $value ?></div>
                <div class="small text-body-secondary mt-1"><i class='bx <?= $icon ?> me-1'></i><?= $label ?></div>
                <?php if ($hint): ?><div class="opacity-50" style="font-size:.68rem;"><?= $esc($hint) ?></div><?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php if ($deadCount > 0): ?>
    <div class="alert alert-danger border-0 rounded-4 small mb-4 d-flex align-items-start gap-3">
        <i class='bx bx-error-circle fs-4 mt-1'></i>
        <div class="flex-grow-1">
            <strong><?= $deadCount ?> dead reservation<?= $deadCount === 1 ? '' : 's' ?> found.</strong>
            These IPs stay locked even though nobody can use them (owner deleted or no live resource).
            <div class="ipc-dead-bar mt-2" style="max-width:280px;"><i style="width:<?= $esc(round(($deadCount / max(1, $inUseCount + $deadCount)) * 100)) ?>%"></i></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ============================ Reserve search + tabs ============================ -->
    <div class="card border-0 rounded-4 blur shadow-sm mb-4">
        <div class="card-header bg-transparent border-bottom border-body-secondary border-opacity-10 py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="fw-bold"><i class='bx bx-lock-alt me-2'></i>Reserved IPs — who owns what</span>
            <div class="d-flex gap-2 align-items-center">
                <input type="search" id="ipcSearch" class="form-control form-control-sm bg-transparent border-secondary border-opacity-25" placeholder="Search IP or email…" style="max-width:230px;">
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive ipc-scroll" id="ipcScrollBox">
                <table class="table align-middle mb-0 small ipc-table">
                    <thead class="table-dark opacity-75">
                        <tr>
                            <th class="ps-4">IP Address</th>
                            <th>State</th>
                            <th>Owner</th>
                            <th>Usage</th>
                            <th>Label</th>
                            <th>Reserved At</th>
                            <th>Last Usage</th>
                            <th class="pe-4 text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody id="ipcReservedBody" data-total="<?= (int)$reservedTotal ?>" data-loaded="<?= count($initialRows) ?>">
                        <?php $rows = $initialRows; if ($rows): ?>
                        <?php include __DIR__ . '/../../partials/_ip_control_rows.php'; ?>
                        <?php else: ?>
                        <tr><td colspan="8" class="text-center text-body-secondary py-4">No reserved IPs — the whole pool is free.</td></tr>
                        <?php endif; ?>
                        <tr id="ipcLoaderRow" class="d-none"><td colspan="8" class="text-center py-3"><span class="spinner-border spinner-border-sm me-2"></span>Loading more IPs…</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-2 small text-body-secondary border-top border-body-secondary border-opacity-10">
                <span id="ipcVisibleCount"></span>
                Search covers IP address, owner email and state (e.g. <code>dead</code>, <code>in_use</code>, <code>never_used</code>) — scroll inside the list to load more.
            </div>
        </div>
    </div>

    <!-- ============================ Next free IPs ============================ -->
    <div class="card border-0 rounded-4 blur shadow-sm">
        <div class="card-header bg-transparent border-bottom border-body-secondary border-opacity-10 py-3 d-flex justify-content-between align-items-center">
            <span class="fw-bold"><i class='bx bx-check-circle me-2 text-success'></i>Next available IPs</span>
            <span class="small text-body-secondary"><?= number_format($available) ?> free in total — showing the first <?= count($nextFree) ?> (lowest numeric)</span>
        </div>
        <div class="card-body py-3">
            <div class="d-flex flex-wrap gap-1">
                <?php foreach ($nextFree as $f): ?>
                <span class="ipc-chip"><?= $esc($f['ip_addr']) ?></span>
                <?php endforeach; ?>
                <?php if (!$nextFree): ?><span class="text-body-secondary small">Pool exhausted — no free IPs.</span><?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var CSRF = <?= json_encode(Session::csrfToken()) ?>;
    var notify = function (m, t, ty) { if (window.TomNotify) TomNotify.show(m, t, ty || 'success', 4000); };

    async function post(url, payload) {
        var res = await fetch(url, {
            method: 'POST',
            headers: { 'X-CSRF-Token': CSRF, 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        return res.json();
    }

    var ipcSearchDebounce = null;
    var ipcSearching      = false;

    function refreshSearch() {
        clearTimeout(ipcSearchDebounce);
        ipcSearchDebounce = setTimeout(_doRefreshSearch, 250);
    }

    function _doRefreshSearch() {
        var q = document.getElementById('ipcSearch').value.trim().toLowerCase();

        // Remove previous backend search results
        document.querySelectorAll('#ipcReservedBody tr.ipc-search-result').forEach(function (r) { r.remove(); });

        // Filter the rows already loaded in the DOM
        var rows = document.querySelectorAll('#ipcReservedBody tr[data-filter]:not(.ipc-search-result)');
        var visible = 0;
        rows.forEach(function (row) {
            var hay = row.getAttribute('data-filter') || '';
            var show = !q || hay.indexOf(q) !== -1;
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        if (q && visible === 0) {
            ipcSearchBackend(q);
            ipcSearching = true;
        } else {
            ipcSearching = false;
        }
        ipcUpdateCount();
    }

    async function ipcSearchBackend(q) {
        try {
            var res = await post('/api/admin/ip_control', { action: 'search', q: q, limit: 50 });
            if (res.status === 'success' && Array.isArray(res.rows)) {
                var tbody = document.getElementById('ipcReservedBody');
                var loaderRow = document.getElementById('ipcLoaderRow');
                var html = res.rows.map(ipcRenderRow).join('');
                var tmp = document.createElement('tbody');
                tmp.innerHTML = html;
                var inserted = [];
                while (tmp.firstChild) {
                    var tr = tbody.insertBefore(tmp.firstChild, loaderRow);
                    tr.classList.add('ipc-search-result');
                    inserted.push(tr);
                }
                inserted.forEach(function (tr) {
                    var btn = tr.querySelector('.ipc-release');
                    if (btn) bindRelease(btn);
                });
                ipcUpdateCount();
            } else {
                countEl.textContent = 'No matches found for "' + escHtml(q) + '"';
            }
        } catch (e) {
            notify('Search failed', 'Error', 'error');
        } finally {
            ipcSearching = false;
            ipcUpdateCount();
        }
    }

    function escHtml(v) {
        var d = document.createElement('div');
        d.textContent = v == null ? '' : v;
        return d.innerHTML;
    }

    document.getElementById('ipcSearch').addEventListener('input', refreshSearch);
    refreshSearch();

    // ---------- Infinite scroll inside the fixed-height reserved list ----------
    var tbody       = document.getElementById('ipcReservedBody');
    var scrollBox   = document.getElementById('ipcScrollBox');
    var loaderRow   = document.getElementById('ipcLoaderRow');
    var countEl     = document.getElementById('ipcVisibleCount');
    var ipcBusy     = false;

    function ipcLoadedCount() {
        return document.querySelectorAll('#ipcReservedBody tr[data-filter]').length;
    }

    function ipcUpdateCount() {
        var total   = parseInt(tbody.getAttribute('data-total'), 10) || 0;
        var loaded  = ipcLoadedCount();
        var shown   = document.querySelectorAll('#ipcReservedBody tr[data-filter]:not([style*="display: none"])').length;
        countEl.textContent = 'Showing ' + shown + ' of ' + loaded + ' loaded / ' + total + ' reserved IP(s)' +
            (loaded < total ? ' — scroll inside the list to load more' : '');
    }

    // Render ONE JSON row into <tr> markup (mirrors the PHP partial so the
    // server-painted first 15 rows and client-loaded batches look identical).
    function ipcRenderRow(r) {
        var esc = function (v) {
            var d = document.createElement('div');
            d.textContent = v == null ? '' : v;
            return d.innerHTML;
        };
        var fmt = function (ts) {
            if (!ts) return '—';
            try {
                var d = new Date((Number(ts) * 1000));
                var pad = function (n) { return String(n).padStart(2, '0'); };
                return pad(d.getDate()) + ' ' +
                    ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'][d.getMonth()] + ' ' +
                    d.getFullYear() + ', ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
            } catch (e) { return '—'; }
        };
        var usageBadge = function (u) {
            return badgeHtml(u, {
                'Device':      '<i class=\'bx bx-mobile-alt me-1\'></i>Device',
                'Machine Lab': '<i class=\'bx bx-server me-1\'></i>Machine Lab',
                'Instance':    '<i class=\'bx bx-cube me-1\'></i>Instance',
            }, 'bg-info text-dark', 'bg-body-secondary', '<i class=\'bx bx-x me-1\'></i>Unlinked');
        };
        var stateBadge = function (s, reason) {
            if (s === 'system') return '<span class="badge rounded-pill bg-info text-dark">System</span>';
            if (s === 'dead') return '<span class="badge rounded-pill bg-danger">Dead</span><div class="opacity-50 small-x-tiny">' + esc(reason || '') + '</div>';
            return '<span class="badge rounded-pill bg-success">In Use</span>';
        };
        var lastUsageCell = function () {
            if (!r.last_usage_at) {
                var acq = r.reserved_at ? '<div class="opacity-50 small-x-tiny mt-1">acquired ' + fmt(r.reserved_at) + '</div>' : '';
                return '<span class="badge rounded-pill bg-warning text-dark" title="Assigned but never used for a lab deploy"><i class=\'bx bx-pause me-1\'></i>Never used</span>' + acq;
            }
            var detail = (r.last_usage_label ? r.last_usage_label + ' · ' : '') + fmt(r.last_usage_at);
            return '<span class="badge rounded-pill bg-success" title="This IP has been used by a lab deploy"><i class=\'bx bx-rocket me-1\'></i>Used</span>' +
                '<div class="opacity-50 small-x-tiny mt-1">' + esc(detail) + '</div>';
        };
        var ownerCell = '<a class="text-decoration-none fw-semibold" href="/admin/user/' + encodeURIComponent(r.email || '') + '">' + esc(r.email || r.reserved_to || '') + '</a>';
        var releaseBtn = r.state === 'dead'
            ? '<button type="button" class="btn btn-sm btn-danger rounded-pill ipc-release" data-ip="' + esc(r.ip_addr) + '">Release dead IP</button>'
            : '<button type="button" class="btn btn-sm btn-outline-warning rounded-pill ipc-release" data-ip="' + esc(r.ip_addr) + '">Release</button>';

        return [
            '<tr data-filter="' + esc(r.data_filter || '') + '">',
            '<td class="ps-4 ipc-ip">' + esc(r.ip_addr) + '</td>',
            '<td>' + stateBadge(r.state, r.dead_reason) + '</td>',
            r.state === 'system' ? '<td class="text-body-secondary">' + esc(r.email || 'system') + '</td>' : '<td>' + ownerCell + '</td>',
            '<td>' + usageBadge(r.usage) + '</td>',
            '<td class="text-body-secondary">' + esc(r.label || r.service_type || '—') + '</td>',
            '<td class="text-body-secondary">' + fmt(r.reserved_at) + '</td>',
            '<td class="text-body-secondary">' + lastUsageCell() + '</td>',
            '<td class="pe-4 text-end">' + releaseBtn + '</td>',
            '</tr>'
        ].join('');
    }

    function badgeHtml(u, map, usedCls, unusedCls, unusedHtml) {
        var e = document.createElement('div');
        var cls = u && map[u] ? usedCls : unusedCls;
        var html = u && map[u] ? map[u] : unusedHtml;
        e.innerHTML = '<span class="badge rounded-pill ' + cls + '">' + html + '</span>';
        return e.innerHTML;
    }

    async function ipcLoadMore() {
        if (ipcBusy) return;
        var loaded  = ipcLoadedCount();
        var total   = parseInt(tbody.getAttribute('data-total'), 10);
        if (loaded >= total) return;

        ipcBusy = true;
        loaderRow.classList.remove('d-none');
        try {
            var res = await post('/api/admin/ip_control', { action: 'rows', offset: loaded, limit: 15 });
            if (res.status === 'success' && Array.isArray(res.rows)) {
                var html = res.rows.map(ipcRenderRow).join('');
                var tmp = document.createElement('tbody');
                tmp.innerHTML = html;
                var inserted = [];
                while (tmp.firstChild) inserted.push(tbody.insertBefore(tmp.firstChild, loaderRow));
                inserted.forEach(function (tr) {
                    var btn = tr.querySelector('.ipc-release');
                    if (btn) bindRelease(btn);
                });
                tbody.setAttribute('data-loaded', ipcLoadedCount());
                refreshSearch();
                ipcUpdateCount();
                if (ipcLoadedCount() >= total) {
                    notify('Loaded full IPs — all ' + total + ' reservations are visible.', 'IP Control');
                }
            } else {
                notify(res.error || 'Load failed', 'Error', 'error');
            }
        } catch (e) {
            notify('Network error', 'Error', 'error');
        } finally {
            ipcBusy = false;
            loaderRow.classList.add('d-none');
        }
    }

    scrollBox.addEventListener('scroll', function () {
        if (ipcBusy || ipcSearching) return;
        if (scrollBox.scrollTop + scrollBox.clientHeight >= scrollBox.scrollHeight - 40) ipcLoadMore();
    });

    var deadBtn = document.getElementById('ipcReleaseDead');
    if (deadBtn) {
        deadBtn.addEventListener('click', async function () {
            if (!window.confirm('Release every dead IP back to the pool?')) return;
            deadBtn.disabled = true;
            try {
                var res = await post('/api/admin/ip_control', { action: 'release_dead' });
                if (res.status === 'success') {
                    notify(res.message, 'Dead IPs released');
                    setTimeout(function () { location.reload(); }, 700);
                } else {
                    notify(res.error || 'Bulk release failed', 'Error', 'error');
                    deadBtn.disabled = false;
                }
            } catch (e) {
                notify('Network error', 'Error', 'error');
                deadBtn.disabled = false;
            }
        });
    }
})();
</script>
