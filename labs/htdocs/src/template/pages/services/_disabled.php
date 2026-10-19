<?php
// Rendered by SessionRenderer::generatePageBody() when a service is disabled.
// $slug = service slug (e.g. 'mysql'); label resolved from ServiceConfig.

$serviceLabel = $slug;
try {
    $serviceLabel = \TomLabs\Services\ServiceConfig::get($slug)['label'];
} catch (\Throwable $e) {
    // keep raw slug as fallback
}
?>

<div class="blur banner mb-3 rounded-0 border-bottom border-secondary border-opacity-10">
    <div class="container-fluid px-4">
        <div class="row align-items-center py-3">
            <div class="col">
                <h1 class="fw-bold theme-text m-0" style="font-size: 1.8rem; letter-spacing: -0.5px;"><?= htmlspecialchars($serviceLabel) ?></h1>
                <p class="text-secondary opacity-75 mt-2 mb-0" style="font-size: 0.85rem; line-height: 1.7; letter-spacing: 0.2px;">
                    Service temporarily unavailable
                </p>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid px-4 py-5">
    <div class="row justify-content-center">
        <div class="col-lg-6 text-center">
            <div class="card border-0 rounded-4 blur shadow-sm p-5">
                <div class="mb-3">
                    <i class="bx bx-error-circle display-4 text-warning"></i>
                </div>
                <h4 class="fw-bold mb-2">This service is disabled</h4>
                <p class="text-secondary mb-4">
                    An administrator has disabled <strong><?= htmlspecialchars($serviceLabel) ?></strong>.
                    It cannot be used right now — check back later or contact an administrator.
                </p>
                <a href="/services" class="glass-btn glass-btn-primary d-inline-flex align-items-center justify-content-center gap-2">
                    <i class="bx bx-chevron-left"></i> Back to Services
                </a>
            </div>
        </div>
    </div>
</div>
