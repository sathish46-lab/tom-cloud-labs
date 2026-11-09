<?php
/**
 * Admin → Sessions — Clear all PHP sessions.
 *
 * Deletes all sess_* files, admin-only, CSRF-protected.
 * Admin's own session is destroyed too — everyone signs in again.
 */

$sessionPath = rtrim(session_save_path() ?: sys_get_temp_dir(), '/') . '/';
$sessionFiles = is_dir($sessionPath) ? glob($sessionPath . 'sess_*') : [];
$sessionCount = is_array($sessionFiles) ? count($sessionFiles) : 0;

$esc = static fn ($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>

<div class="card border-0 rounded-4 shadow-sm mb-4">
    <div class="card-header bg-transparent border-bottom border-body-secondary border-opacity-10 py-3 d-flex align-items-center justify-content-between gap-2">
        <span class="fw-bold"><i class='bx bx-log-out me-2'></i>Clear all sessions</span>
    </div>
    <div class="card-body p-4">
        <p class="mb-3">Forces every signed-in user — including you — to sign in again.</p>

        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="card border-0 rounded-3 shadow-sm">
                    <div class="card-body text-center py-4">
                        <span class="d-block small text-muted text-uppercase fw-bold letter-spacing-1 mb-1">Active PHP sessions</span>
                        <span class="d-block fw-bold fs-4" id="scSessionCount"><?= (int)$sessionCount ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-0 rounded-3 shadow-sm">
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

<script>
var CSRF = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
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
</script>
