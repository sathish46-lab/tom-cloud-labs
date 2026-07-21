<?php
$user = Session::getUser();
$userId = (int)$user->getUserId();

$db = DatabaseConnection::getClient()->selectDatabase('tom_labs_db');
$instances = iterator_to_array($db->instances->find(['user_id' => $userId]));
$deployedLabs = iterator_to_array($db->deployed_labs->find(['user_id' => $userId]));

if (!function_exists('dashboard_relative_time')) {
    function dashboard_relative_time($dt) {
        if (!$dt instanceof MongoDB\BSON\UTCDateTime) return 'recently';
        $ts = $dt->toDateTime()->getTimestamp();
        $diff = time() - $ts;
        if ($diff < 60) return 'just now';
        if ($diff < 3600) return floor($diff / 60) . 'm ago';
        if ($diff < 86400) return floor($diff / 3600) . 'h ago';
        if ($diff < 604800) return floor($diff / 86400) . 'd ago';
        return date('M j', $ts);
    }
}
function dashboard_fork_source($db, $forkedFrom) {
    if (empty($forkedFrom)) return '';
    try {
        $oid = new MongoDB\BSON\ObjectId($forkedFrom);
    } catch (Exception $e) {
        return '';
    }
    $src = $db->instances->findOne(['_id' => $oid]);
    if (!$src) {
        $src = $db->deployed_labs->findOne(['_id' => $oid]);
    }
    if (!$src) return '';
    $name = $src['name'] ?? '';
    if (empty($name)) {
        $name = $src['lab_type'] ?? ($src['instance_hash'] ?? 'source');
    }
    return $name;
}
?>
<div class="blur mb-3 rounded-0">
    <div class="container-fluid px-3 py-3">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-body-secondary theme-text p-2 rounded-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                    <i class='bx bx-cube-alt fs-3'></i>
                </div>
                <div>
                    <h2 class="fw-bold theme-text m-0 d-flex align-items-center gap-2">
                        Instances <span class="text-secondary opacity-50 fw-light">—— Developer Area</span>
                    </h2>
                    <p class="text-secondary opacity-75 mt-1 mb-0 fs-6">
                        Author labs, build them into images, deploy running copies, share with your team.
                    </p>
                </div>
            </div>
            
            <div class="d-flex align-items-center gap-2">
                <button class="btn instance-action-btn instance-action-primary d-flex align-items-center gap-2 rounded-pill px-4 fw-bold">
                    <i class='bx bx-plus-circle'></i> Create template
                </button>
                <button class="btn instance-action-btn d-flex align-items-center gap-2 rounded-pill px-4" data-coreui-toggle="modal" data-coreui-target="#forkLabModal">
                    <i class='bx bx-git-repo-forked text-danger'></i> Fork a lab
                </button>
                <button class="btn instance-action-btn d-flex align-items-center gap-2 rounded-pill px-4">
                    <i class='bx bx-import text-danger'></i> Import
                </button>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid px-4 py-3">

    
    <!-- Your Templates Section -->
    <div class="mb-5">
        <div class="d-flex align-items-center gap-2 mb-4">
            <i class='bx bx-cube theme-text'></i>
            <h5 class="fw-bold theme-text m-0">Your templates</h5>
            <span class="instance-badge text-bg-soft-secondary"><?= count($instances) ?></span>
        </div>
        
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 row-cols-xl-3 g-3" id="templatesGrid">
            
            <?php if (empty($instances)): ?>
            <div class="col-12">
                <div class="text-center py-5">
                    <div class="text-secondary opacity-50 mb-2"><i class='bx bx-layer fs-1'></i></div>
                    <div class="text-secondary small">No templates yet. Create your first lab template!</div>
                </div>
            </div>
            <?php else: ?>
                <?php foreach ($instances as $instance): ?>
                <?php
                    $slug = $instance['slug'] ?? 'unknown';
                    $name = $instance['name'] ?? 'Unnamed Lab';
                    $type = $instance['type'] ?? 'machine';
                    $status = $instance['status'] ?? 'draft';
                    $visibility = $instance['visibility'] ?? 'private';
                    $image = $instance['image'] ?? 'ubuntu:24.04';
                    $bgColor = $instance['color'] ?? 'rgba(0,0,0,0.5)';
                    // Full-card background = template avatar from CDN (correct per template type).
                    $tplKey = $instance['template'] ?? $type;
                    $avatarMap = [
                        'essentials'   => 'essentials_avatar.png',
                        'minio'        => 'minio_avatar.png',
                        'docker_lab'   => 'docker_avatar.png',
                        'docker'       => 'docker_avatar.png',
                        'zephyr'       => 'zephyr_avatar.png',
                        'kali'         => 'kali-background.png',
                        'n8n'          => 'essentials_avatar.png',
                    ];
                    $avatarFile = $avatarMap[$tplKey] ?? 'essentials_avatar.png';
                    $cover = Session::cdn3('labassets/avatar/' . $avatarFile);
                    $forkSource = dashboard_fork_source($db, $instance['forked_from'] ?? null);
                    $updatedLabel = dashboard_relative_time($instance['updated_at'] ?? null);
                ?>
                <div class="col">
                    <div class="card border-0 shadow-sm rounded-3 overflow-hidden instance-template-card">
                        <div class="instance-template-card-bg" style="background-image: url('<?= htmlspecialchars($cover) ?>'), linear-gradient(135deg, <?= htmlspecialchars($bgColor) ?> 0%, rgba(0,0,0,0.35) 100%);"></div>
                        <div class="instance-template-card-overlay"></div>

                        <div class="position-relative d-flex flex-column justify-content-end p-3 h-100" style="z-index: 2;">
                            <div class="d-flex align-items-center gap-2 mb-5">
                                <?php if ($visibility === 'public'): ?>
                                <span class="instance-badge text-bg-soft-info small">public</span>
                                <?php else: ?>
                                <span class="instance-badge text-bg-soft-secondary small">private</span>
                                <?php endif; ?>
                                <span class="instance-badge text-bg-soft-light small">
                                    <?= htmlspecialchars($type) ?>
                                </span>
                                <span class="instance-badge text-bg-soft-<?= ($status === 'built' || $status === 'running') ? 'success' : 'warning' ?> small">
                                    <?= htmlspecialchars($status) ?>
                                </span>
                            </div>

                            <h6 class="fw-bold theme-text mb-0">
                                <?= htmlspecialchars($name) ?><?= !empty($instance['forked_from']) ? ' (fork)' : '' ?>
                            </h6>

                            <div class="d-flex align-items-center justify-content-between gap-2 text-secondary small mb-1 mt-3">
                                <div class="d-flex align-items-center gap-2">
                                    <span><?= htmlspecialchars($image) ?></span>
                                    <span><?= htmlspecialchars($tplKey) ?></span>
                                </div>
                                <span class="text-nowrap">updated <?= htmlspecialchars($updatedLabel) ?></span>
                            </div>

                            <div class="d-flex gap-2">
                                <a href="/instances/<?= htmlspecialchars($slug) ?>/configuration" class="btn instance-action-btn btn-sm rounded-pill px-2 fw-bold flex-fill text-center">
                                    <i class='bx bx-cog'></i> Config
                                </a>
                                <a href="/instances/<?= htmlspecialchars($slug) ?>/files" class="btn instance-action-btn btn-sm rounded-pill px-2 fw-bold flex-fill text-center">
                                    <i class='bx bx-folder'></i> Files
                                </a>
                                <a href="/instances/<?= htmlspecialchars($slug) ?>/deployments" class="btn instance-action-btn instance-action-primary btn-sm rounded-pill px-2 fw-bold flex-fill text-center">
                                    <i class='bx bx-rocket'></i> Deploy
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
        </div>
    </div>
    
    
    <!-- Footer Section similar to screenshots -->
    <div class="row g-4 mt-4">
        <div class="col-12 col-md-6">
            <div class="card blur border-0 shadow-sm rounded-4 p-4 d-flex flex-row align-items-center justify-content-between cursor-pointer">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-secondary bg-opacity-10 p-2 rounded-circle">
                        <i class='bx bx-compass theme-text fs-4'></i>
                    </div>
                    <div>
                        <h6 class="theme-text fw-bold m-0">Explore catalog</h6>
                        <span class="text-secondary small">Public + team-shared templates</span>
                    </div>
                </div>
                <i class='bx bx-right-arrow-alt text-secondary fs-4'></i>
            </div>
        </div>
        <div class="col-12 col-md-6">
            <div class="card blur border-0 shadow-sm rounded-4 p-4 d-flex flex-row align-items-center justify-content-between cursor-pointer">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-secondary bg-opacity-10 p-2 rounded-circle">
                        <i class='bx bx-group theme-text fs-4'></i>
                    </div>
                    <div>
                        <h6 class="theme-text fw-bold m-0">Workgroups</h6>
                        <span class="text-secondary small">Teams you share to</span>
                    </div>
                </div>
                <i class='bx bx-right-arrow-alt text-secondary fs-4'></i>
            </div>
        </div>
    </div>

</div>

<!-- Fork Lab Modal -->
<div class="modal fade" id="forkLabModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content bg-dark border border-secondary border-opacity-25 rounded-4 overflow-hidden shadow-lg" style="box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5) !important;" id="forkLabForm">
            
            <!-- Red gradient top border effect -->
            <div style="height: 4px; background: linear-gradient(90deg, #ff416c 0%, #ff4b2b 100%); width: 100%;"></div>
            
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <h4 class="modal-title fw-bold text-white">Fork an existing lab</h4>
            </div>
            
            <div class="modal-body px-4 py-4">
                <div class="mb-4">
                    <label class="form-label text-secondary small fw-bold mb-2">Lab to fork</label>
                    <select name="source_id" class="form-select bg-dark border-secondary border-opacity-50 text-white rounded-pill px-3 py-2 shadow-none focus-border-primary" style="background-image: url('data:image/svg+xml,%3csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 16 16\'%3e%3cpath fill=\'none\' stroke=\'%236c757d\' stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'2\' d=\'M2 5l6 6 6-6\'/%3e%3c/svg%3e');" required>
                        <?php if(!empty($deployedLabs)): foreach($deployedLabs as $lab): ?>
                            <option value="<?= htmlspecialchars($lab['instance_hash']) ?>"><?= htmlspecialchars(ucfirst($lab['lab_type'] ?? 'lab')) ?> (<?= htmlspecialchars($lab['instance_hash']) ?>)</option>
                        <?php endforeach; else: ?>
                            <option value="">No labs available to fork</option>
                        <?php endif; ?>
                    </select>
                    <div class="form-text text-secondary opacity-75 mt-2" style="font-size: 0.75rem;">A new editable template is created from this lab — you become its owner.</div>
                </div>
                
                <div class="mb-4">
                    <label class="form-label text-secondary small fw-bold mb-2">Name (optional)</label>
                    <input type="text" name="name" class="form-control bg-dark border-secondary border-opacity-50 text-white rounded-pill px-3 py-2 shadow-none focus-border-primary" placeholder="Defaults to '<source> (fork)'">
                </div>
                
                <div class="mb-2">
                    <label class="form-label text-secondary small fw-bold mb-2">Visibility</label>
                    <select name="visibility" class="form-select bg-dark border-secondary border-opacity-50 text-white rounded-pill px-3 py-2 shadow-none focus-border-primary" style="background-image: url('data:image/svg+xml,%3csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 16 16\'%3e%3cpath fill=\'none\' stroke=\'%236c757d\' stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'2\' d=\'M2 5l6 6 6-6\'/%3e%3c/svg%3e');">
                        <option value="private">Private</option>
                        <option value="public">Public</option>
                    </select>
                    <div class="form-text text-secondary opacity-75 mt-2" style="font-size: 0.75rem;">Visibility of the NEW forked template.</div>
                </div>
            </div>
            
            <div class="modal-footer border-0 px-4 pb-4 pt-0 d-flex justify-content-end gap-2">
                <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold" style="background-color: #ff4b2b; border-color: #ff4b2b;">Fork</button>
                <button type="button" class="btn btn-dark border border-secondary border-opacity-50 text-white rounded-pill px-4 fw-bold hover-bg-secondary" data-coreui-dismiss="modal">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Create Lab Modal -->
<div class="modal fade" id="createLabModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content bg-dark border border-secondary border-opacity-25 rounded-4 overflow-hidden shadow-lg" style="box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5) !important;" id="createLabForm">
            
            <!-- Purple gradient top border effect -->
            <div style="height: 4px; background: linear-gradient(90deg, #8b5cf6 0%, #a78bfa 100%); width: 100%;"></div>
            
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <h4 class="modal-title fw-bold text-white">Create new template</h4>
            </div>
            
            <div class="modal-body px-4 py-4">
                <div class="mb-4">
                    <label class="form-label text-secondary small fw-bold mb-2">Template Name *</label>
                    <input type="text" name="name" class="form-control bg-dark border-secondary border-opacity-50 text-white rounded-pill px-3 py-2 shadow-none focus-border-primary" placeholder="e.g., Python Data Science" required>
                </div>
                
                <div class="mb-4">
                    <label class="form-label text-secondary small fw-bold mb-2">Type</label>
                    <select name="type" class="form-select bg-dark border-secondary border-opacity-50 text-white rounded-pill px-3 py-2 shadow-none focus-border-primary" style="background-image: url('data:image/svg+xml,%3csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 16 16\'%3e%3cpath fill=\'none\' stroke=\'%236c757d\' stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'2\' d=\'M2 5l6 6 6-6\'/%3e%3c/svg%3e');">
                        <option value="machine">Machine</option>
                        <option value="challenge">Challenge</option>
                    </select>
                </div>
                
                <div class="mb-2">
                    <label class="form-label text-secondary small fw-bold mb-2">Visibility</label>
                    <select name="visibility" class="form-select bg-dark border-secondary border-opacity-50 text-white rounded-pill px-3 py-2 shadow-none focus-border-primary" style="background-image: url('data:image/svg+xml,%3csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 16 16\'%3e%3cpath fill=\'none\' stroke=\'%236c757d\' stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'2\' d=\'M2 5l6 6 6-6\'/%3e%3c/svg%3e');">
                        <option value="private">Private</option>
                        <option value="public">Public</option>
                    </select>
                </div>
            </div>
            
            <div class="modal-footer border-0 px-4 pb-4 pt-0 d-flex justify-content-end gap-2">
                <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold" style="background-color: #8b5cf6; border-color: #8b5cf6;">Create</button>
                <button type="button" class="btn btn-dark border border-secondary border-opacity-50 text-white rounded-pill px-4 fw-bold hover-bg-secondary" data-coreui-dismiss="modal">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Add data-coreui-target to create button
    const createBtn = document.querySelector('button.btn-primary[style*="8b5cf6"]');
    if (createBtn) {
        createBtn.setAttribute('data-coreui-toggle', 'modal');
        createBtn.setAttribute('data-coreui-target', '#createLabModal');
    }

    // Helper for AJAX form submission
    const handleFormSubmit = async (formId, endpoint, modalId) => {
        const form = document.getElementById(formId);
        if (!form) return;
        
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = form.querySelector('button[type="submit"]');
            const originalText = btn.innerHTML;
            btn.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading...`;
            btn.disabled = true;

            try {
                const formData = new FormData(form);
                const response = await fetch(endpoint, {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.status === 'success') {
                    // Close modal
                    const modalEl = document.getElementById(modalId);
                    const modal = coreui.Modal.getInstance(modalEl);
                    if (modal) modal.hide();
                    
                    // Reset form
                    form.reset();
                    
                    // Inject HTML into the grid (remove "no templates" if present)
                    const grid = document.getElementById('templatesGrid');
                    if (grid) {
                        const noTemplates = grid.querySelector('.col-12');
                        if (noTemplates) noTemplates.remove();
                        grid.insertAdjacentHTML('afterbegin', data.html);
                    }
                } else {
                    alert('Error: ' + (data.error || 'Unknown error'));
                }
            } catch (err) {
                console.error(err);
                alert('A network error occurred.');
            } finally {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        });
    };

    handleFormSubmit('forkLabForm', '/api/instances/fork', 'forkLabModal');
    handleFormSubmit('createLabForm', '/api/instances/create', 'createLabModal');
});
</script>

<style>
/* Add hover effects for cards */
.hover-border-primary:hover {
    border-color: var(--cui-primary) !important;
}
.hover-bg-secondary:hover {
    background-color: rgba(255,255,255,0.05) !important;
}
.group:hover .group-hover-text-primary {
    color: var(--cui-primary) !important;
}
.focus-border-primary:focus {
    border-color: var(--cui-primary) !important;
    box-shadow: 0 0 0 0.25rem rgba(var(--cui-primary-rgb), 0.25) !important;
}
</style>
