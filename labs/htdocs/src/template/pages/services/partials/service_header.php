<?php
// Shared, config-driven service header (banner + tabs).
// Expects: $service_slug (string) and $service_meta (config array for that slug).
$current_tab = $current_tab ?? 'dashboard';
$m = $service_meta;
$base = '/services/' . $m['slug'];
$addUserFn = 'openAdd' . $m['export_prefix'] . 'UserModal';
?>
<div class="blur banner mb-3 rounded-0 border-bottom border-secondary border-opacity-10">
    <div class="container-fluid px-4 py-3">
    <div class="row align-items-center">
        <div class="col-auto">
            <div class="bg-white p-2 rounded shadow-sm d-flex justify-content-center align-items-center" style="width: 72px; height: 72px;">
                <img src="<?= htmlspecialchars($m['icon']) ?>" alt="<?= htmlspecialchars($m['title']) ?>" class="img-fluid" style="width: 40px; height: 40px; object-fit: contain;">
            </div>
        </div>
        <div class="col">
            <div class="d-flex flex-column gap-1">
                <h3 class="fw-bold mb-0 ls-tight text-white"><?= htmlspecialchars($m['title']) ?></h3>
                <p class="small" style="max-width: 850px; line-height: 1.5; color: var(--glass-text-muted);">
                    <?= htmlspecialchars($m['description']) ?>
                </p>
                <div class="d-flex gap-2 mt-2">
                    <button class="btn btn-primary rounded-pill px-4" style="background: #6366f1; border-color: #6366f1; font-size: 0.85rem;" onclick="<?= $addUserFn ?>()">Add User</button>
                    <?php if (!empty($m['manage'])): ?>
                    <a href="<?= htmlspecialchars($m['manage']['href']) ?>" class="btn btn-outline-light rounded-pill px-4 text-decoration-none" style="border-color: rgba(255,255,255,0.2); font-size: 0.85rem;"><?= htmlspecialchars($m['manage']['label']) ?></a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="row m-0 p-0 mt-4">
        <ul class="nav nav-tabs">
            <?php foreach ($m['tabs'] as $tabKey => $tabLabel): ?>
                <?php $tabHref = ($tabKey === 'dashboard') ? $base : $base . '/' . $tabKey; ?>
            <li class="nav-item">
                <a class="nav-link <?= ($current_tab === $tabKey) ? 'active' : '' ?>" href="<?= $tabHref ?>"><?= htmlspecialchars($tabLabel) ?></a>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    </div>
</div>
