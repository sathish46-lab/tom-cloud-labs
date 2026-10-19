<?php
require_once __DIR__ . '/../../../../src/load.php';

use TomLabs\Services\ServiceConfig;
use TomLabs\Services\ServiceFlags;

$flags = ServiceFlags::all();
$services = ServiceConfig::all();

$driverTypeLabels = [
    'database'  => 'Users + Databases',
    'user_only' => 'Users only',
    'vhost'     => 'Users + Vhosts',
];
?>
<style>
.admin-card { transition: all 0.2s; }
.admin-card:hover { transform: translateY(-2px); box-shadow: 0 8px 22px rgba(0,0,0,0.28); }
.service-off { opacity: 0.55; }
</style>

<div class="blur banner mb-3 rounded-0 border-bottom border-secondary border-opacity-10">
    <div class="card-body p-0" style="margin-left: 1rem; margin-right: 1rem;">
        <div class="container-fluid pt-3 pb-1">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="position-relative flex-shrink-0">
                        <div class="avatar lab-header-avatar">
                            <div class="avatar-img d-flex align-items-center justify-content-center bg-dark bg-opacity-25 rounded-circle p-2">
                                <i class='bx bx-desktop'></i>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex flex-column gap-1">
                        <h3 class="fw-bold mb-0 ls-tight lab-header-title">Services</h3>
                        <div class="d-flex flex-wrap align-items-center gap-2 small">
                            <div class="d-flex align-items-center text-secondary">
                                <span class="me-1 opacity-75">Switch a service off to block its student pages and APIs for everyone. Takes effect immediately.</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <a href="/services" data-no-boost="true"
                       class="btn btn-sm btn-outline-secondary border-secondary border-opacity-25 rounded-pill px-3 text-nowrap">
                        <i class='bx bx-left-arrow-circle me-1'></i>Back to Labs
                    </a>
                </div>
            </div>

        </div>
    </div>
</div>

<div class="container-fluid px-4 pt-3">
    <div class="row" data-service-toggles>
        <?php foreach ($services as $slug => $cfg): ?>
        <?php $enabled = !empty($flags[$slug]); ?>
        <div class="col-md-6 col-xl-4 mb-4">
            <div class="card border-0 rounded-4 blur shadow-sm admin-card h-100 <?= $enabled ? '' : 'service-off' ?>" data-service-card="<?= htmlspecialchars($slug) ?>">
                <div class="card-header border-bottom border-body-secondary border-opacity-10 bg-transparent py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold">
                        <i class='bx bx-data me-2 text-info'></i><?= htmlspecialchars($cfg['label']) ?>
                    </h5>
                    <div class="form-check form-switch fs-4 mb-0">
                        <input class="form-check-input pointer service-toggle" type="checkbox" role="switch"
                               data-service="<?= htmlspecialchars($slug) ?>" data-label="<?= htmlspecialchars($cfg['label']) ?>"
                               <?= $enabled ? 'checked' : '' ?>>
                    </div>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2 small">
                        <span class="text-body-secondary">Slug</span>
                        <span class="font-monospace"><?= htmlspecialchars($slug) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2 small">
                        <span class="text-body-secondary">Operations</span>
                        <span><?= htmlspecialchars($driverTypeLabels[$cfg['driver_type']] ?? $cfg['driver_type']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2 small">
                        <span class="text-body-secondary">User limit</span>
                        <span><?= (int)$cfg['max_users'] ?> per user</span>
                    </div>
                    <div class="d-flex justify-content-between small">
                        <span class="text-body-secondary">Status</span>
                        <span class="fw-semibold service-status-label" data-state="<?= $enabled ? 'on' : 'off' ?>">
                            <?= $enabled ? 'Enabled' : 'Disabled' ?>
                        </span>
                    </div>
                    <p class="text-body-secondary small mt-3 mb-0">
                        When off: student service pages show a notice and all service APIs return
                        <span class="font-monospace">code 1010</span>.
                    </p>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
document.querySelectorAll('.service-toggle').forEach(toggle => {
    toggle.addEventListener('change', async function() {
        const service = this.getAttribute('data-service');
        const label = this.getAttribute('data-label');
        const state = this.checked;
        const originalState = !state;
        const card = document.querySelector('[data-service-card="' + service + '"]');
        const statusLabel = card ? card.querySelector('.service-status-label') : null;

        try {
            const formData = new FormData();
            formData.append('service', service);
            formData.append('state', state);
            const response = await fetch('/api/admin/service_toggle', {
                method: 'POST',
                body: formData
            });
            const res = await response.json();
            if (res.status === 'success') {
                if (card) card.classList.toggle('service-off', !state);
                if (statusLabel) {
                    statusLabel.textContent = state ? 'Enabled' : 'Disabled';
                    statusLabel.setAttribute('data-state', state ? 'on' : 'off');
                }
                TomNotify.show(`${label} is now ${state ? 'ENABLED' : 'DISABLED'}`, 'Saved', 'success', 3000);
            } else {
                TomNotify.show(res.error || 'Failed to update', 'Error', 'error', 4000);
                toggle.checked = originalState;
            }
        } catch(e) {
            TomNotify.show('Network error', 'Error', 'error', 4000);
            toggle.checked = originalState;
        }
    });
});
</script>
