<?php
// /src/template/partials/instances/manage/configuration.php
?>
<div class="card blur border-0 rounded-4 p-4 shadow-lg">
    <div class="d-flex align-items-center gap-2 mb-4">
        <i class='bx bx-cog text-secondary'></i>
        <span class="fw-bold theme-text">Configuration</span>
    </div>
    
    <div class="text-secondary text-uppercase fw-bold small mb-3" style="font-size: 0.7rem; letter-spacing: 0.08em;">General</div>
    
    <div class="mb-3 d-flex align-items-center">
        <div style="width: 130px;" class="text-secondary fw-bold small flex-shrink-0">Name</div>
        <input type="text" class="form-control config-input flex-grow-1" value="<?= htmlspecialchars($instance['name'] ?? '') ?>">
    </div>
    <div class="mb-3 d-flex align-items-start">
        <div style="width: 130px;" class="text-secondary fw-bold small pt-2 flex-shrink-0">Slug</div>
        <div class="flex-grow-1">
            <div class="d-flex align-items-center gap-2 mb-2">
                <input type="text" class="form-control config-input" value="<?= htmlspecialchars($instance['slug'] ?? '') ?>" style="max-width: 300px;" disabled>
                <?php
                $slugLen = strlen($instance['slug'] ?? '');
                ?>
                <span class="bg-secondary bg-opacity-25 text-secondary rounded-3 px-3 py-2 small"><?= $slugLen ?>/15</span>
                <button class="btn btn-outline-danger rounded-3 px-3 fw-bold small py-2 border-opacity-50">Save slug</button>
            </div>
            <div class="text-secondary opacity-75" style="font-size: 0.75rem; line-height: 1.5;">
                Friendly identifier (lowercase letters, digits, dashes) — it becomes the deployed lab id and the start of every copy's hostname, so machine slugs are capped at 15 characters. Must be unique — if taken, a short suffix is added automatically. <strong>Locked after the first deploy.</strong> The internal id stays <span class="text-info"><?= htmlspecialchars(substr($instance['_id'] ?? 'N/A', 0, 12)) ?></span>.
            </div>
        </div>
    </div>
    <div class="mb-3 d-flex align-items-center">
        <div style="width: 130px;" class="text-secondary fw-bold small flex-shrink-0">Description</div>
        <input type="text" class="form-control config-input flex-grow-1" value="<?= htmlspecialchars($instance['description'] ?? '') ?>">
    </div>
    <div class="mb-3 d-flex align-items-start">
        <div style="width: 130px;" class="text-secondary fw-bold small pt-2 flex-shrink-0">Stability</div>
        <div class="flex-grow-1">
            <input type="text" class="form-control config-input w-100 mb-2" value="<?= htmlspecialchars($instance['stability'] ?? 'alpha') ?>">
            <div class="text-secondary opacity-75" style="font-size: 0.75rem;">
                alpha = private work-in-progress — only you can see or deploy it. Move to <strong>beta</strong> or <strong>stable</strong> to share it with a workgroup or publish it to the catalog.
            </div>
        </div>
    </div>
    
    <hr class="border-secondary border-opacity-25 my-4">
    
    <div class="text-secondary text-uppercase fw-bold small mb-3" style="font-size: 0.7rem; letter-spacing: 0.08em;">Resources</div>
    
    <div class="mb-3 d-flex align-items-center">
        <div style="width: 130px;" class="text-secondary fw-bold small flex-shrink-0">CPU</div>
        <input type="text" class="form-control config-input" value="<?= htmlspecialchars($instance['cpu'] ?? '2') ?>" style="max-width: 200px;">
    </div>
    <div class="mb-3 d-flex align-items-start">
        <div style="width: 130px;" class="text-secondary fw-bold small pt-2 flex-shrink-0">Memory</div>
        <div class="flex-grow-1">
            <input type="text" class="form-control config-input mb-2" value="<?= htmlspecialchars($instance['memory'] ?? '512m') ?>" style="max-width: 300px;">
            <div class="text-secondary opacity-75" style="font-size: 0.75rem;">Docker memory string, e.g. <span class="text-info">512m, 2g</span>.</div>
        </div>
    </div>
    <div class="mb-3 d-flex align-items-start">
        <div style="width: 130px;" class="text-secondary fw-bold small pt-2 flex-shrink-0">Ports</div>
        <div class="flex-grow-1">
            <?php 
            $ports = is_array($instance['ports'] ?? null) ? implode(' ', $instance['ports']) : ($instance['ports'] ?? '');
            ?>
            <input type="text" class="form-control config-input mb-2" value="<?= htmlspecialchars($ports) ?>" style="max-width: 300px;">
            <div class="text-secondary opacity-75" style="font-size: 0.75rem;">Space-separated container ports, e.g. <span class="text-info">80</span> or <span class="text-info">22 8080</span>.</div>
        </div>
    </div>
    <div class="mb-3 d-flex align-items-start">
        <div style="width: 130px;" class="text-secondary fw-bold small pt-2 flex-shrink-0">Network</div>
        <div class="flex-grow-1">
            <input type="text" class="form-control config-input mb-2" value="<?= htmlspecialchars($instance['network'] ?? 'Default (wg0)') ?>" style="max-width: 300px;">
            <div class="text-secondary opacity-75" style="font-size: 0.75rem;">Internal-only = bridge IP + published port, no WireGuard peer. Default (wg0) joins the shared VPN. Team networks appear here once an admin grants your team access.</div>
        </div>
    </div>

    <hr class="border-secondary border-opacity-25 my-4">

    <div class="text-secondary text-uppercase fw-bold small mb-3" style="font-size: 0.7rem; letter-spacing: 0.08em;">User Management</div>
    
    <div class="form-check form-switch mb-3 d-flex align-items-center gap-3 ps-0">
        <div style="width: 130px;" class="text-secondary fw-bold small m-0 flex-shrink-0">SSH</div>
        <input class="form-check-input m-0 fs-5" type="checkbox" role="switch" <?= !empty($instance['ssh_enabled']) ? 'checked' : '' ?> style="background-color: #ff4b2b; border-color: #ff4b2b;">
        <label class="form-check-label text-white small m-0">Enable SSH access</label>
    </div>
    <div class="mb-3 d-flex align-items-start">
        <div style="width: 130px;" class="text-secondary fw-bold small pt-2 flex-shrink-0">Users</div>
        <div class="flex-grow-1">
            <?php 
            $users = $instance['users'] ?? [['username' => 'sibi', 'shell' => '/bin/bash', 'sudo' => true]];
            foreach ($users as $u): 
            ?>
            <div class="d-flex align-items-center gap-2 mb-2 p-2 rounded-3 border border-secondary border-opacity-25 bg-black bg-opacity-50">
                <div class="bg-secondary bg-opacity-25 p-2 rounded d-flex"><i class='bx bx-user text-secondary'></i></div>
                <span class="text-white fw-bold ms-2"><?= htmlspecialchars($u['username']) ?></span>
                <span class="text-secondary ms-4"><?= htmlspecialchars($u['shell'] ?? '/bin/bash') ?></span>
                <div class="ms-auto d-flex align-items-center gap-3 pe-3">
                    <div class="form-check form-switch m-0 d-flex align-items-center gap-2">
                        <input class="form-check-input m-0" type="checkbox" role="switch" <?= !empty($u['sudo']) ? 'checked' : '' ?>>
                        <label class="form-check-label text-secondary small">sudo</label>
                    </div>
                    <i class='bx bx-trash text-danger pointer'></i>
                </div>
            </div>
            <?php endforeach; ?>
            <button class="btn btn-outline-danger btn-sm rounded-pill fw-bold border-opacity-50 mt-2 px-3"><i class='bx bx-plus'></i> Add user</button>
            <div class="text-secondary opacity-75 mt-3" style="font-size: 0.75rem;">Authoring intent — each becomes a <span class="text-info">useradd</span> line in the Dockerfile (the app-provisioned uid-1000 user is managed separately, do not add it here).</div>
        </div>
    </div>

    <div class="mt-5 text-end">
        <button class="btn btn-primary rounded-pill px-5 py-2 fw-bold" style="background-color: #4a5568; border:none; color: white;">Save configuration</button>
    </div>
</div>
