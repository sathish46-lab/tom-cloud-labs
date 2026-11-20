<?php
/**
 * Admin → Sessions & Rate Limits — Clear all PHP sessions and manage rate limit counters.
 *
 * Deletes all sess_* files, admin-only, CSRF-protected.
 * Also lists and clears active rate limit counters stored under
 * /dev/shm/ratelimit and /dev/shm/ratelimit_actions.
 */

$sessionPath = rtrim(session_save_path() ?: sys_get_temp_dir(), '/') . '/';
$sessionFiles = is_dir($sessionPath) ? glob($sessionPath . 'sess_*') : [];
$sessionCount = is_array($sessionFiles) ? count($sessionFiles) : 0;

// ---- Rate limit counters ----
$rlCounters = [];
$rlTotal = 0;
$rlError = '';

try {
    $storageDirs = [];
    $globalDir = is_dir('/dev/shm') && is_writable('/dev/shm') ? '/dev/shm/ratelimit' : '/tmp/ratelimit';
    $actionDir = is_dir('/dev/shm') && is_writable('/dev/shm') ? '/dev/shm/ratelimit_actions' : '/tmp/ratelimit_actions';
    $storageDirs[] = $globalDir;
    $storageDirs[] = $actionDir;

    $now = time();

    foreach ($storageDirs as $dir) {
        if (!is_dir($dir)) {
            continue;
        }
        foreach (glob($dir . '/*.count') as $file) {
            $basename = basename($file);
            $parts = explode('_', $basename);
            $windowPart = array_pop($parts);
            $windowNum = (int) str_replace('.count', '', $windowPart);
            $keyName = implode('_', $parts);

            $content = @file_get_contents($file);
            $count = (int) $content;
            $mtime = @filemtime($file);

            $type = 'action';
            $label = 'Action: ' . $keyName;

            if (str_starts_with($keyName, 'em_')) {
                $type = 'email';
                $label = 'Email: ' . substr($keyName, 3);
            } elseif (str_starts_with($keyName, 'ip_')) {
                $type = 'ip';
                $label = 'IP: ' . substr($keyName, 3);
            } elseif (str_starts_with($keyName, 'usr_')) {
                $type = 'user';
                $label = 'User: ' . substr($keyName, 4);
            }

            $estimatedWindow = ($type === 'action') ? 900 : 60;
            $resetTime = ($windowNum + 1) * $estimatedWindow;
            $remaining = max(0, $resetTime - $now);

            $rlCounters[] = [
                'file'   => $basename,
                'type'   => $type,
                'label'  => $label,
                'count'  => $count,
                'window' => $estimatedWindow,
                'reset_in' => $remaining,
                'mtime'  => $mtime,
            ];
            $rlTotal++;
        }
    }

    usort($rlCounters, function ($a, $b) {
        return ($b['mtime'] ?? 0) - ($a['mtime'] ?? 0);
    });
} catch (Throwable $e) {
    $rlError = $e->getMessage();
}

$esc = static fn ($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

$typeBadge = function ($t) {
    return match ($t) {
        'email' => 'bg-primary text-white',
        'ip'    => 'bg-info text-dark',
        'user'  => 'bg-secondary text-white',
        'action'=> 'bg-warning text-dark',
        default => 'bg-secondary text-white',
    };
};

$RL_PER_PAGE = 10;
$rlShowPage  = (int) ($_GET['rl_page'] ?? 1);
if ($rlShowPage < 1) $rlShowPage = 1;
$rlTotalPages = max(1, ceil($rlTotal / $RL_PER_PAGE));
if ($rlShowPage > $rlTotalPages) $rlShowPage = $rlTotalPages;
$rlOffset = ($rlShowPage - 1) * $RL_PER_PAGE;
$rlPageCounters = array_slice($rlCounters, $rlOffset, $RL_PER_PAGE);

?>
<style>
/* Consistent outer spacing to match page UI */
.rl-section { padding-top: 0; }

.rl-card {
    border: 0;
    border-radius: 0.5rem;
    box-shadow: 0 0.125rem 0.25rem rgba(var(--cui-body-color-rgb, 0,0,0), 0.075);
}

/* Compact stat cards inside */
.rl-stat-card {
    border: 0;
    border-radius: 0.375rem;
    box-shadow: none;
}

/* Table styling */
.rl-table { font-size: 0.8rem; }
.rl-table th {
    position: sticky;
    top: 0;
    background: rgba(var(--cui-body-bg-rgb, 255,255,255), 0.9);
    backdrop-filter: blur(6px);
    z-index: 2;
    font-weight: 700;
    text-transform: uppercase;
    font-size: 0.65rem;
    letter-spacing: 0.05em;
    color: var(--cui-secondary-color, #6c757d);
    border-bottom: 1px solid rgba(var(--cui-body-color-rgb, 0,0,0), 0.08) !important;
}
.rl-table td {
    border-color: rgba(var(--cui-body-color-rgb, 0,0,0), 0.04) !important;
    padding: 6px 8px;
    vertical-align: middle;
    font-size: 0.8rem;
}
.rl-table tr:hover td { background: rgba(var(--cui-body-color-rgb, 0,0,0), 0.03); }

.btn-rl-clear { font-size: 0.68rem; padding: 3px 8px; }
.btn-rl-load { font-size: 0.72rem; padding: 5px 12px; }

/* Small pills */
.rl-badge { font-size: 0.68rem; }

/* Pager alignment */
.rl-pager { margin-top: 1rem; }
</style>

<!-- ===== SESSIONS CARD ===== -->
<div class="card rl-card shadow-sm mb-4">
    <div class="card-header bg-transparent border-bottom border-body-secondary border-opacity-10 py-3 d-flex align-items-center justify-content-between gap-2">
        <span class="fw-bold"><i class='bx bx-log-out me-2'></i>Clear all sessions</span>
    </div>
    <div class="card-body p-4">
        <p class="mb-3">Forces every signed-in user — including you — to sign in again.</p>

        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="rl-stat-card shadow-sm">
                    <div class="card-body text-center py-4">
                        <span class="d-block small text-muted text-uppercase fw-bold letter-spacing-1 mb-1">Active PHP sessions</span>
                        <span class="d-block fw-bold fs-5" id="scSessionCount"><?= (int)$sessionCount ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="rl-stat-card shadow-sm">
                    <div class="card-body text-center py-4">
                        <span class="d-block small text-muted text-uppercase fw-bold letter-spacing-1 mb-1">Session save path</span>
                        <span class="d-block text-muted small"><?= $esc($sessionPath) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="alert alert-warning border-0 rounded-3 mb-4">
            <div class="d-flex align-items-start gap-3">
                <i class='bx bx-warning-circle text-warning flex-shrink-0 mt-1'></i>
                <div>
                    <strong>This signs out everyone.</strong>
                    <p class="mb-0 mt-2">
                        All <span id="scSessionCount2"><?= (int)$sessionCount ?></span> session files are deleted
                        immediately. Every user — including your own admin session — must sign in again.
                        There is no undo.
                    </p>
                </div>
            </div>
        </div>

        <div class="d-flex gap-3 align-items-center">
            <button id="scClearBtn" type="button" class="btn btn-danger">
                <i class='bx bx-trash me-2'></i> Clear all sessions now
            </button>
            <span id="scResult" class="ms-3 fw-semibold"></span>
        </div>
    </div>
</div>

<!-- ===== RATE LIMITS CARD ===== -->
<div class="card rl-card shadow-sm mb-4">
    <div class="card-header bg-transparent border-bottom border-body-secondary border-opacity-10 py-3 d-flex align-items-center justify-content-between gap-2">
        <span class="fw-bold"><i class='bx bx-speedometer me-2'></i>Rate Limits</span>
    </div>
    <div class="card-body p-4">
        <p class="mb-3">View and clear active rate limit counters. These track login, signup, resend-verification, and other per-IP/per-email/per-user limits to prevent abuse.</p>

        <?php if ($rlError): ?>
            <div class="alert alert-danger small py-2 mb-3"><?= $esc($rlError) ?></div>
        <?php endif; ?>

        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <div class="rl-stat-card shadow-sm">
                    <div class="card-body text-center py-3">
                        <span class="d-block small text-muted text-uppercase fw-bold letter-spacing-1 mb-1">Active rate limit counters</span>
                        <span class="d-block fw-bold fs-5" id="rlCount"><?= (int)$rlTotal ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="rl-stat-card shadow-sm">
                    <div class="card-body text-center py-3">
                        <span class="d-block small text-muted text-uppercase fw-bold letter-spacing-1 mb-1">Storage location</span>
                        <span class="d-block text-muted small">/dev/shm/ratelimit*</span>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($rlTotal > 0): ?>
            <div class="table-responsive mb-3">
                <table class="table table-hover rl-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width: 26%;">Type / Label</th>
                            <th style="width: 12%;">Count</th>
                            <th style="width: 12%;">Window</th>
                            <th style="width: 16%;">Resets in</th>
                            <th style="width: 18%;">Last hit</th>
                            <th style="width: 16%;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="rlTableBody">
                    <?php foreach ($rlPageCounters as $c): ?>
                        <tr>
                            <td>
                                <span class="badge rl-badge <?= $typeBadge($c['type']) ?> rounded-pill me-2"><?= $esc(ucfirst($c['type'])) ?></span>
                                <?= $esc($c['label']) ?>
                            </td>
                            <td class="fw-bold"><?= (int)$c['count'] ?></td>
                            <td><?= (int)($c['window'] / 60) ?> min</td>
                            <td><?= (int)($c['reset_in'] / 60) ?> min</td>
                            <td><?= date('H:i:s', $c['mtime'] ?? 0) ?></td>
                            <td>
                                <button type="button" class="btn btn-rl-clear btn-outline-danger" data-file="<?= $esc($c['file']) ?>" title="Clear this counter">
                                    <i class='bx bx-trash'></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($rlTotal > 0): ?>
            <div class="rl-pager">
                <?php if ($rlShowPage > 1): ?>
                    <a href="/admin/sessions?rl_page=<?= $rlShowPage - 1 ?>" class="btn btn-outline-secondary btn-sm">← Older</a>
                <?php endif; ?>
                <span class="badge bg-light text-dark border">Page <?= $rlShowPage ?> of <?= $rlTotalPages ?></span>
                <?php if ($rlShowPage < $rlTotalPages): ?>
                    <a href="/admin/sessions?rl_page=<?= $rlShowPage + 1 ?>" class="btn btn-outline-secondary btn-sm">Newer →</a>
                <?php endif; ?>
                <small class="text-muted ms-2">Showing <?= min($rlOffset + 1, $rlTotal) ?>–<?= min($rlOffset + count($rlPageCounters), $rlTotal) ?> of <?= $rlTotal ?></small>
            </div>
        <?php else: ?>
            <div class="text-center py-4">
                <i class='bx bx-check-circle text-success display-4 mb-3'></i>
                <p class="text-muted mb-0">No active rate limit counters.</p>
            </div>
        <?php endif; ?>

        <?php if ($rlTotal > 0): ?>
            <div class="alert alert-warning border-0 rounded-3 mb-3">
                <div class="d-flex align-items-start gap-3">
                    <i class='bx bx-warning-circle text-warning flex-shrink-0 mt-1'></i>
                    <div>
                        <strong>Clearing rate limits</strong>
                        <p class="mb-0 mt-2">
                            Removes all (or selected) rate limit counters so affected IPs, emails, or users
                            can immediately retry actions that were previously limited.
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="d-flex gap-3 align-items-center">
            <button id="rlClearAllBtn" type="button" class="btn btn-danger<?= $rlTotal === 0 ? ' disabled' : '' ?>">
                <i class='bx bx-trash me-2'></i> Clear all rate limits
            </button>
            <span id="rlResult" class="ms-3 fw-semibold"></span>
        </div>
    </div>
</div>

<script>
var CSRF = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

// ===== CLEAR ALL SESSIONS =====
document.getElementById('scClearBtn').addEventListener('click', function () {
    if (!window.confirm('This will delete every PHP session and sign out all users, including you. Everyone must sign in again. Continue?')) {
        return;
    }
    var btn = document.getElementById('scClearBtn');
    var result = document.getElementById('scResult');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span> Clearing…';
    result.className = ''; result.textContent = '';
    fetch('/api/admin/clear_sessions.php', {
        method: 'POST',
        headers: { 'X-CSRF-Token': CSRF }
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
        btn.disabled = false;
        btn.innerHTML = "<i class='bx bx-trash me-2'></i> Clear all sessions now";
        if (data.status === 'success') {
            result.className = 'text-success';
            result.textContent = 'Done — all sessions cleared.';
            if (window.TomNotify) TomNotify.show(data.message || 'All sessions cleared.', 'Success', 'success', 3000);
            setTimeout(function () { window.location.href = '/signin'; }, 1200);
        } else {
            result.className = 'text-danger';
            result.textContent = 'Failed: ' + (data.error || 'unknown error');
            if (window.TomNotify) TomNotify.show(data.error || 'Clear failed', 'Error', 'error', 4000);
        }
    })
    .catch(function () {
        btn.disabled = false;
        btn.innerHTML = "<i class='bx bx-trash me-2'></i> Clear all sessions now";
        result.className = 'text-danger';
        result.textContent = 'Network error.';
        if (window.TomNotify) TomNotify.show('Network error', 'Error', 'error', 4000);
    });
});

// ===== CLEAR RATE LIMITS =====
var rlResult = document.getElementById('rlResult');
var rlClearAllBtn = document.getElementById('rlClearAllBtn');

function rlShowResult(msg, kind) {
    rlResult.className = 'ms-3 fw-semibold ' + (kind === 'success' ? 'text-success' : 'text-danger');
    rlResult.textContent = msg;
}

// Clear a single counter (uses event delegation so it works on all pages)
document.getElementById('rlTableBody').addEventListener('click', function (e) {
    var btn = e.target.closest('.btn-rl-clear');
    if (!btn) return;
    var file = btn.getAttribute('data-file');
    if (!window.confirm('Clear rate limit counter "' + file + '"?')) return;
    btn.disabled = true;
    var orig = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    fetch('/api/admin/ratelimits.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-CSRF-Token': CSRF,
        },
        body: 'action=clear_one&file=' + encodeURIComponent(file),
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
        btn.disabled = false;
        btn.innerHTML = orig;
        if (data.status === 'success') {
            rlShowResult('Cleared: ' + data.message, 'success');
            if (window.TomNotify) TomNotify.show(data.message, 'Success', 'success', 3000);
            setTimeout(function () { window.location.reload(); }, 800);
        } else {
            rlShowResult('Failed: ' + (data.error || 'unknown'), 'error');
        }
    })
    .catch(function () {
        btn.disabled = false;
        btn.innerHTML = orig;
        rlShowResult('Network error.', 'error');
    });
});

// Clear all rate limits
rlClearAllBtn.addEventListener('click', function () {
    if (rlClearAllBtn.disabled) return;
    if (!window.confirm('This will delete ALL rate limit counters. Affected users/IPs can immediately retry limited actions. Continue?')) return;
    rlClearAllBtn.disabled = true;
    rlClearAllBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span> Clearing…';
    rlResult.textContent = '';
    fetch('/api/admin/ratelimits.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-CSRF-Token': CSRF,
        },
        body: 'action=clear_all',
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
        rlClearAllBtn.disabled = false;
        rlClearAllBtn.innerHTML = "<i class='bx bx-trash me-2'></i> Clear all rate limits";
        if (data.status === 'success') {
            rlShowResult('Done — ' + data.removed + ' counter(s) cleared.', 'success');
            if (window.TomNotify) TomNotify.show(data.message, 'Success', 'success', 3000);
            setTimeout(function () { window.location.reload(); }, 800);
        } else {
            rlShowResult('Failed: ' + (data.error || 'unknown error'), 'error');
        }
    })
    .catch(function () {
        rlClearAllBtn.disabled = false;
        rlClearAllBtn.innerHTML = "<i class='bx bx-trash me-2'></i> Clear all rate limits";
        rlShowResult('Network error.', 'error');
    });
});
</script>
