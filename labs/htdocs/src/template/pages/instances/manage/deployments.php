<?php
// /src/template/partials/instances/manage/deployments.php
?>
<div class="card blur border-0 rounded-4 p-4 shadow-lg">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h5 class="fw-bold theme-text m-0 d-flex align-items-center gap-2"><i class='bx bx-rocket fs-4'></i> Deploy & run</h5>
        <button class="btn btn-primary rounded-pill px-4 fw-bold" style="background-color: #ff4b2b; border-color: #ff4b2b;"><i class='bx bx-play'></i> Deploy</button>
    </div>
    
    <div class="alert alert-dark border border-secondary border-opacity-25 bg-black bg-opacity-25 text-secondary mb-4 rounded-4 py-3">
        <i class='bx bx-info-circle me-2'></i> Build this template first — only built templates can be deployed.
    </div>
    
    <div class="d-flex align-items-center justify-content-between border-bottom border-secondary border-opacity-25 pb-2 mb-4">
        <span class="text-secondary fw-bold small text-uppercase">DEPLOYMENTS <span class="instance-badge text-bg-soft-primary ms-2">running <?= count($instance['deployments'] ?? []) ?></span></span>
        <i class='bx bx-refresh text-secondary pointer'></i>
    </div>
    
    <div class="text-center py-5">
        <div class="text-secondary opacity-50 mb-2"><i class='bx bx-layer fs-1'></i></div>
        <div class="text-secondary small">No deployments yet.</div>
    </div>
</div>
