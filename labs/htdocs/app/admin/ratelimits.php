<?php
/**
 * Admin → Rate Limits — View and clear active rate limit counters.
 *
 * Shows all active rate limit counters (global + action-specific) stored
 * under /dev/shm/ratelimit and /dev/shm/ratelimit_actions, with per-item
 * clear buttons and a "Clear all" button. Admin-only, CSRF-protected.
 */

require_once __DIR__ . '/../../../../src/load.php';

$admin = AuthMiddleware::requireAdmin();
AuthMiddleware::requireCsrf();

$counters = [];
$total = 0;
$error = '';

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
            // Parse: <kind>_<md5>_<window>.count  or  <actionkey>_<window>.count
            $parts = explode('_', $basename);
            $windowPart = array_pop($parts); // <window>.count
            $windowNum = (int) str_replace('.count', '', $windowPart);
            $keyName = implode('_', $parts);

            $content = @file_get_contents($file);
            $count = (int) $content;
            $mtime = @filemtime($file);

            // Determine type from key prefix
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

            // Compute reset time: window is in seconds (e.g. 900 = 15 min)
            // $windowNum is the floor(time()/window) value
            // reset = ($windowNum + 1) * actual_window_seconds
            // But we don't store actual_window, so estimate from mtime + reasonable window
            // Default window: 900s (15 min) for actions, 60s for global
            $estimatedWindow = ($type === 'action') ? 900 : 60;
            $resetTime = ($windowNum + 1) * $estimatedWindow;
            $remaining = max(0, $resetTime - $now);

            $counters[] = [
                'file'   => $basename,
                'type'   => $type,
                'label'  => $label,
                'count'  => $count,
                'window' => $estimatedWindow,
                'reset_in' => $remaining,
                'mtime'  => $mtime,
            ];
            $total++;
        }
    }

    // Sort: most recent first
    usort($counters, function ($a, $b) {
        return ($b['mtime'] ?? 0) - ($a['mtime'] ?? 0);
    });
} catch (Throwable $e) {
    $error = $e->getMessage();
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

?>
<style>
.rl-table { font-size: 0.85rem; }
.rl-table th { position: sticky; top: 0; background: rgba(var(--cui-body-bg-rgb, 255,255,255), 0.95); backdrop-filter: blur(8px); z-index: 2; font-weight: 700; text-transform: uppercase; font-size: 0.65rem; letter-spacing: 0.05em; color: var(--cui-secondary-color, #6c757d); border-bottom: 1px solid rgba(var(--cui-body-color-rgb, 0,0,0), 0.08) !important; }
.rl-table td { border-color: rgba(var(--cui-body-color-rgb, 0,0,0), 0.04) !important; padding: 8px 10px; vertical-align: middle; }
.rl-table tr:hover td { background: rgba(var(--cui-body-color-rgb, 0,0,0), 0.03); }
.btn-rl-clear { font-size: 0.7rem; padding: 4px 10px; }
</style>

<div class="card border-0 rounded-4 shadow-sm mb-4">
    <div class="card-header bg-transparent border-bottom border-body-secondary border-opacity-10 py-3 d-flex align-items-center justify-content-between gap-2">
        <span class="fw-bold"><i class='bx bx-speedometer me-2'></i>Rate Limits</span>
    </div>
    <div class="card-body p-4">
        <p class="mb-3">View and clear active rate limit counters. These track login, signup, resend-verification, and other per-IP/per-email/per-user limits to prevent abuse.</p>

        <?php if ($error): ?>
            <div class="alert alert-danger small py-2 mb-4"><?= $esc($error) ?></div>
        <?php endif; ?>

        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="card border-0 rounded-3 shadow-sm">
                    <div class="card-body text-center py-4">
                        <span class="d-block small text-muted text-uppercase fw-bold letter-spacing-1 mb-1">Active rate limit counters</span>
                        <span class="d-block fw-bold fs-4" id="rlCount"><?= (int)$total ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-0 rounded-3 shadow-sm">
                    <div class="card-body text-center py-4">
                        <span class="d-block small text-muted text-uppercase fw-bold letter-spacing-1 mb-1">Storage location</span>
                        <span class="d-block text-muted small">/dev/shm/ratelimit*</span>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($total > 0): ?>
            <div class="table-responsive mb-4">
                <table class="table table-hover rl-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width: 24%;">Type / Label</th>
                            <th style="width: 13%;">Count</th>
                            <th style="width: 14%;">Window</th>
                            <th style="width: 16%;">Resets in</th>
                            <th style="width: 17%;">Last hit</th>
                            <th style="width: 16%;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($counters as $c): ?>
                        <tr>
                            <td>
                                <span class="badge <?= $typeBadge($c['type']) ?> rounded-pill me-2"><?= $esc(ucfirst($c['type'])) ?></span>
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
        <?php else: ?>
            <div class="text-center py-4">
                <i class='bx bx-check-circle text-success display-4 mb-3'></i>
                <p class="text-muted mb-0">No active rate limit counters.</p>
            </div>
        <?php endif; ?>

        <div class="alert alert-warning border-0 rounded-3 mb-4">
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

        <div class="d-flex gap-3 align-items-center">
            <button id="rlClearAllBtn" type="button" class="btn btn-danger<?= $total === 0 ? ' disabled' : '' ?>">
                <i class='bx bx-trash me-2'></i> Clear all rate limits
            </button>
            <span id="rlResult" class="ms-3 fw-semibold"></span>
        </div>
    </div>
</div>

<script>
var CSRF = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
var rlResult = document.getElementById('rlResult');
var rlClearAllBtn = document.getElementById('rlClearAllBtn');

function showResult(msg, kind) {
    rlResult.className = 'ms-3 fw-semibold ' + (kind === 'success' ? 'text-success' : 'text-danger');
    rlResult.textContent = msg;
}

// Clear a single counter
document.querySelectorAll('.btn-rl-clear').forEach(function (btn) {
    btn.addEventListener('click', function () {
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
                showResult('Cleared: ' + data.message, 'success');
                if (window.TomNotify) TomNotify.show(data.message, 'Success', 'success', 3000);
                setTimeout(function () { window.location.reload(); }, 800);
            } else {
                showResult('Failed: ' + (data.error || 'unknown'), 'error');
            }
        })
        .catch(function () {
            btn.disabled = false;
            btn.innerHTML = orig;
            showResult('Network error.', 'error');
        });
    });
});

// Clear all
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
            showResult('Done — ' + data.removed + ' counter(s) cleared.', 'success');
            if (window.TomNotify) TomNotify.show(data.message, 'Success', 'success', 3000);
            setTimeout(function () { window.location.reload(); }, 800);
        } else {
            showResult('Failed: ' + (data.error || 'unknown error'), 'error');
        }
    })
    .catch(function () {
        rlClearAllBtn.disabled = false;
        rlClearAllBtn.innerHTML = "<i class='bx bx-trash me-2'></i> Clear all rate limits";
        showResult('Network error.', 'error');
    });
});
</script>
