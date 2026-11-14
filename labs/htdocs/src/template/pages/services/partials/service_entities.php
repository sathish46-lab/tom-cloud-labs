<?php
// Shared, config-driven entity management page (databases / vhosts).
// Expects: $service_slug, $service_meta (config array), $service_users,
// $selectedUsername, $service_entities, and for dynamic option lists
// (collations) $service_collations.
$m = $service_meta;
$p = $m['export_prefix'];
$uk = $m['users']['user_key'];
$c = $m['create'];
$extra = $c['extra'] ?? null;
?>
<div class="container-fluid px-2 bg-transparent">
    <!-- Entity Content -->
    <div class="row g-4" style="align-items: start;">

        <?php if (empty($service_users)): ?>
            <div class="col-12 text-center py-5">
                <i class='bx bx-user-x text-secondary' style="font-size: 4rem;"></i>
                <h4 class="mt-3 text-light">No <?= htmlspecialchars($m['title']) ?> Users Found</h4>
                <p class="text-secondary">You must create a <?= htmlspecialchars($m['title']) ?> User from the Dashboard before you can create <?= htmlspecialchars($c['empty_label']) ?>.</p>
                <a href="/services/<?= htmlspecialchars($m['slug']) ?>" class="btn btn-primary rounded-pill mt-3 px-4 shadow-sm" style="background: linear-gradient(135deg, #6366f1, #8b5cf6); border: none;">Go to Dashboard</a>
            </div>
        <?php else: ?>

            <!-- Left Content: Create Entity -->
        <div class="col-lg-3 col-xl-3 mb-4">
                <div class="card blur border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-header bg-transparent border-bottom-0 pt-4 pb-2">
                        <h6 class="card-title fw-bold text-white mb-0" style="font-size: 1rem;"><?= htmlspecialchars($c['title']) ?></h6>
                    </div>
                    <div class="card-body px-4 pb-4">
                        <div class="mb-3">
                            <label class="form-label text-secondary small fw-bold">Username</label>
                            <select id="select-db-user" class="form-select text-white shadow-none" style="background-color: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; cursor: pointer; color-scheme: dark;" onchange="switch<?= $p ?>User(this.value)">
                                <?php foreach ($service_users as $mu): ?>
                                    <option value="<?= htmlspecialchars($mu[$uk]) ?>" <?= ($selectedUsername === $mu[$uk]) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($mu[$uk]) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-secondary small fw-bold"><?= htmlspecialchars($c['name_label']) ?></label>
                            <div class="input-group" style="background: rgba(0,0,0,0.2); border-radius: 8px; border: 1px solid rgba(255,255,255,0.1);">
                                <span class="input-group-text text-secondary border-0 bg-transparent pe-1" id="db-prefix" style="border-radius: 8px 0 0 8px;"><?= htmlspecialchars($selectedUsername) ?>_</span>
                                <input type="text" id="new-db-name" class="form-control text-white border-0 bg-transparent ps-0 shadow-none" style="border-radius: 0 8px 8px 0;">
                            </div>
                        </div>
                        <?php if (!empty($extra)): ?>
                        <div class="mb-4">
                            <label class="form-label text-secondary small fw-bold"><?= htmlspecialchars($extra['label']) ?></label>
                            <select id="<?= htmlspecialchars($extra['id']) ?>" class="form-select text-white shadow-none" style="background-color: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; cursor: pointer; color-scheme: dark;">
                                <?php if (!empty($extra['dynamic'])): ?>
                                    <?php foreach (($service_collations ?? []) as $charset => $charsetCollations): ?>
                                        <optgroup label="<?= htmlspecialchars($charset) ?>">
                                            <?php foreach ($charsetCollations as $col): ?>
                                                <option value="<?= htmlspecialchars($col) ?>" <?= ($col === $extra['default']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($col) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <?php foreach ($extra['options'] as $opt): ?>
                                        <option value="<?= htmlspecialchars($opt[0]) ?>" <?= !empty($opt[2]) ? 'selected' : '' ?>><?= htmlspecialchars($opt[1]) ?></option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <button class="btn rounded-pill px-4 shadow-sm text-white" style="background-color: #6366f1; border: none; font-size: 0.85rem; font-weight: 600;" onclick="submitCreate<?= $p ?>DbInline()">
                            <?= htmlspecialchars($c['title']) ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Right Content: Entity List -->
        <div class="col-lg-9 col-xl-9">
                <div class="card blur border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-header bg-transparent border-bottom-0 pt-4 pb-2">
                        <h6 class="card-title fw-bold text-white mb-1" style="font-size: 1rem;"><?= htmlspecialchars($c['list_title']) ?></h6>
                        <p class="text-secondary small mb-0">Your <?= htmlspecialchars($c['empty_label']) ?> will be listed below</p>
                    </div>

                    <div class="card-body px-4 pb-4">
                        <div id="<?= htmlspecialchars($c['grid_id']) ?>" class="row row-cols-1 row-cols-md-3 g-3">

                            <?php if (empty($service_entities)): ?>
                                <div class="col-12 text-center py-4">
                                    <p class="text-secondary" style="font-size: 0.9rem;">No <?= htmlspecialchars($c['empty_label']) ?> found for this user.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($service_entities as $dbObj): ?>
                                    <?php $entName = htmlspecialchars($dbObj[$c['name_key']]); ?>
                                    <div class="col <?= htmlspecialchars($c['item_class']) ?>" id="<?= htmlspecialchars($c['item_id_prefix'] . $entName) ?>">
                                        <div class="card h-100 border-0" style="background-color: rgba(0,0,0,0.2); border-radius: 12px; border: 1px solid rgba(255,255,255,0.05) !important;">
                                            <div class="card-body">

                                                <div class="d-flex justify-content-between align-items-start mb-3">
                                                    <div>
                                                        <div class="text-secondary small fw-bold mb-1" style="font-size: 0.65rem; letter-spacing: 1px;">DATABASE</div>
                                                        <h6 class="card-title mb-0 text-white font-monospace"><?= $entName ?></h6>
                                                    </div>
                                                    <?php if (($c['badge'] ?? '') === 'collation'): ?>
                                                    <span class="badge bg-dark bg-opacity-50 text-light border border-light border-opacity-10 mysql-collation text-lowercase"><?= htmlspecialchars($dbObj['collation'] ?? $c['badge_default']) ?></span>
                                                    <?php else: ?>
                                                    <span class="badge bg-dark bg-opacity-50 text-light border border-light border-opacity-10 text-lowercase"><?= htmlspecialchars($c['badge']) ?></span>
                                                    <?php endif; ?>
                                                </div>

                                                <div class="d-grid">
                                                    <button class="btn btn-sm btn-outline-danger btn-delete" data-dbname="<?= $entName ?>" onclick="delete<?= $p ?>Db('<?= $entName ?>')">
                                                        <?= htmlspecialchars($c['drop_label']) ?>
                                                    </button>
                                                </div>

                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>

                        </div>
                    </div>
                </div>
            </div>

        <?php endif; ?>

    </div>
</div>

<style>
.cursor-pointer { cursor: pointer; }
.hover-white { transition: color 0.2s; }
.hover-white:hover { color: #ffffff !important; }
.active-user-item {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.2), rgba(139, 92, 246, 0.2)) !important;
    border-left: 3px solid #8b5cf6 !important;
    color: #fff !important;
}
.active-user-item:hover {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.3), rgba(139, 92, 246, 0.3)) !important;
}
</style>

<script>
(function () {
    function bootServiceManager() {
    ServiceManager.init(<?= json_encode($m['js']) ?>);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootServiceManager);
    } else {
        bootServiceManager();
    }
})();
</script>
