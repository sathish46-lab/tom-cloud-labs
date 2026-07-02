<?php
require_once __DIR__ . '/../../../../src/load.php';
$db = DatabaseConnection::getDefaultDatabase();

// Fetch all users
$usersCursor = $db->users->find([], ['sort' => ['last_login' => -1]]);
$users = iterator_to_array($usersCursor);

// Fetch global settings
$globalDoc = $db->global_settings->findOne(['_id' => 'lab_features']);
$globalSettings = ($globalDoc && is_object($globalDoc) && method_exists($globalDoc, 'getArrayCopy')) ? $globalDoc->getArrayCopy() : ((array)$globalDoc ?: []);

$masterDoc = $db->global_settings->findOne(['_id' => 'master_switches']);
$masterSwitches = ($masterDoc && is_object($masterDoc) && method_exists($masterDoc, 'getArrayCopy')) ? $masterDoc->getArrayCopy() : ((array)$masterDoc ?: []);

$matrixDoc = $db->global_settings->findOne(['_id' => 'lab_feature_matrix']);
$labMatrix = ($matrixDoc && is_object($matrixDoc) && method_exists($matrixDoc, 'getArrayCopy')) ? $matrixDoc->getArrayCopy() : ((array)$matrixDoc ?: []);

// Define lab types for the matrix
$knownLabs = [
    'essentials' => 'Essentials',
    'minio' => 'MinIO',
    'n8n' => 'n8n',
    'docker_lab' => 'Docker Lab'
];
$featuresList = [
    'always_on' => 'Always On',
    'http_proxies' => 'HTTP Proxies',
    'startup_script' => 'Startup Script',
    'expose_web' => 'Expose Web'
];
?>
<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0 text-white"><i class='bx bx-crown text-warning me-2'></i>Superuser Admin Panel</h2>
            <p class="text-secondary mb-0 mt-1">Manage users and global feature flags.</p>
        </div>
    </div>

    <div class="row">
        <!-- Master Kill Switches Control -->
        <div class="col-xl-6 mb-4">
            <div class="card bg-dark border-danger border-opacity-50 h-100 shadow-lg" style="border-radius: 15px;">
                <div class="card-header border-bottom border-danger border-opacity-50 bg-danger bg-opacity-10 py-3">
                    <h5 class="mb-0 fw-bold text-danger"><i class='bx bx-power-off me-2'></i>Master Kill Switches</h5>
                    <small class="text-danger opacity-75">Disabling overrides everything else.</small>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($featuresList as $key => $label): ?>
                        <div class="col-md-6 mb-3">
                            <div class="d-flex justify-content-between align-items-center p-3 rounded h-100" style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05);">
                                <div>
                                    <h6 class="mb-1 text-white"><?= $label ?></h6>
                                </div>
                                <div class="form-check form-switch fs-4 mb-0">
                                    <input class="form-check-input pointer master-feature-toggle" type="checkbox" role="switch" data-feature="<?= $key ?>" <?= (!isset($masterSwitches[$key]) || $masterSwitches[$key] !== false) ? 'checked' : '' ?>>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Global Features Control -->
        <div class="col-xl-6 mb-4">
            <div class="card bg-dark border-secondary border-opacity-25 h-100 shadow-lg" style="border-radius: 15px;">
                <div class="card-header border-bottom border-secondary border-opacity-25 bg-transparent py-3">
                    <h5 class="mb-0 fw-bold"><i class='bx bx-globe text-info me-2'></i>Global Force-Enable</h5>
                    <small class="text-secondary">Forces the feature ON for all labs and users.</small>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($featuresList as $key => $label): ?>
                        <div class="col-md-6 mb-3">
                            <div class="d-flex justify-content-between align-items-center p-3 rounded h-100" style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05);">
                                <div>
                                    <h6 class="mb-1 text-white"><?= $label ?></h6>
                                </div>
                                <div class="form-check form-switch fs-4 mb-0">
                                    <input class="form-check-input pointer global-feature-toggle" type="checkbox" role="switch" data-feature="<?= $key ?>" <?= !empty($globalSettings[$key]) ? 'checked' : '' ?>>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
    .lab-matrix-card:hover {
        transform: translateY(-3px);
        background-color: rgba(255,255,255,0.05) !important;
    }
    </style>

    <!-- Per-Lab Feature Matrix Cards -->
    <div class="card bg-dark border-secondary border-opacity-25 mb-4 shadow-lg" style="border-radius: 15px;">
        <div class="card-header border-bottom border-secondary border-opacity-25 bg-transparent py-3">
            <h5 class="mb-0 fw-bold"><i class='bx bx-grid-alt text-primary me-2'></i>Per-Lab Default Features</h5>
            <small class="text-secondary">Click a lab to configure its default available features.</small>
        </div>
        <div class="card-body p-4">
            <div class="row g-4">
                <?php foreach ($knownLabs as $labKey => $labName): 
                    $adminIconMap = [
                        'essentials' => 'bxl-tux',
                        'docker_lab' => 'bxl-docker',
                        'n8n' => 'bx-git-repo-forked'
                    ];
                    $bxClass = $adminIconMap[$labKey] ?? 'bx-cube';
                ?>
                <div class="col-md-3">
                    <div class="card bg-dark border border-secondary border-opacity-25 pointer h-100 lab-matrix-card" data-coreui-toggle="modal" data-coreui-target="#matrixModal-<?= $labKey ?>" style="border-radius: 12px; transition: all 0.2s ease;">
                        <div class="card-body text-center p-4 d-flex flex-column justify-content-center align-items-center">
                            <div class="mb-3 rounded-circle d-flex align-items-center justify-content-center bg-primary bg-opacity-10" style="width: 60px; height: 60px;">
                                <?php if ($labKey === 'minio'): ?>
                                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" height="32" width="32" class="text-primary">
                                        <path d="M13.2072 0.006c-0.6216 -0.0478 -1.2 0.1943 -1.6211 0.582a2.15 2.15 0 0 0 -0.0938 3.0352l3.4082 3.5507a3.042 3.042 0 0 1 -0.664 4.6875l-0.463 0.2383V7.2853a15.4198 15.4198 0 0 0 -8.0174 10.4862v0.0176l6.5487 -3.3281v7.621L13.7794 24V13.6817l0.8965 -0.4629a4.4432 4.4432 0 0 0 1.2207 -7.0292l-3.371 -3.5254a0.7489 0.7489 0 0 1 0.037 -1.0547 0.7522 0.7522 0 0 1 1.0567 0.0371l0.4668 0.4863 -0.006 0.0059 4.0704 4.2441a0.0566 0.0566 0 0 0 0.082 0 0.06 0.06 0 0 0 0 -0.0703l-3.1406 -5.1425 -0.1484 0.1425 0.1484 -0.1445C14.4945 0.3926 13.8287 0.0538 13.2072 0.006Zm-0.9024 9.8652v2.9941l-4.1523 2.1484a13.9787 13.9787 0 0 1 2.7676 -3.9277 14.1784 14.1784 0 0 1 1.3847 -1.2148z" fill="currentColor"></path>
                                    </svg>
                                <?php else: ?>
                                    <i class='bx <?= $bxClass ?> text-primary' style="font-size: 2rem;"></i>
                                <?php endif; ?>
                            </div>
                            <h5 class="fw-bold text-white mb-1"><?= $labName ?></h5>
                            <small class="text-secondary font-monospace"><?= $labKey ?></small>
                        </div>
                    </div>
                </div>


                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Users Table -->
    <div class="card shadow-sm border-secondary border-opacity-10" style="border-radius: 15px;">
        <div class="card-header border-bottom border-secondary border-opacity-10 bg-transparent py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold"><i class='bx bx-user-circle text-primary me-2'></i>Registered Users</h5>
            <div class="position-relative" style="max-width: 250px; width: 100%;">
                <i class='bx bx-search position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary'></i>
                <input type="text" id="userSearchInput" class="form-control rounded-pill ps-5 bg-body-tertiary border-0 shadow-none" placeholder="Search users...">
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive" id="usersTableContainer" style="max-height: 500px; overflow-y: auto;">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light sticky-top shadow-sm" style="z-index: 1;">
                        <tr>
                            <th class="ps-4">User</th>
                            <th>Role</th>
                            <th>Created</th>
                            <th>Last Login</th>
                            <th class="text-end pe-4">Action</th>
                        </tr>
                    </thead>
                    <tbody class="border-top-0" id="usersTableBody">
                        <!-- Loaded via JS -->
                    </tbody>
                </table>
                <div id="usersLoadingSpinner" class="text-center py-4 text-secondary">
                    <div class="spinner-border spinner-border-sm me-2" role="status"></div> Loading users...
                </div>
                <div id="usersEmptyState" class="text-center py-5 d-none">
                    <i class='bx bx-user-x fs-1 text-secondary opacity-50 mb-3'></i>
                    <h5 class="text-secondary fw-semibold">No users found</h5>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Per-Lab Feature Matrix Modals (Moved outside main layout) -->
<?php foreach ($knownLabs as $labKey => $labName): ?>
<div class="modal fade" id="matrixModal-<?= $labKey ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 rounded-4 shadow-lg" style="background: rgba(11, 30, 54, 0.95); backdrop-filter: blur(32px); -webkit-backdrop-filter: blur(32px); border: 1px solid rgba(255, 255, 255, 0.1) !important;">
            <div class="modal-header border-0 pt-4 px-4 pb-0">
                <button type="button" class="btn-close btn-close-white" data-coreui-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <?php
                    $labDescriptions = [
                        'essentials' => 'The essentials lab provides basic Linux tools and utilities for standard tasks.',
                        'docker_lab' => 'A complete Docker environment for building, running, and managing containers.',
                        'n8n'        => 'Workflow automation lab with n8n to connect your apps and automate tasks.',
                        'minio'      => 'S3 compatible object storage server for storing unstructured data.'
                    ];
                    $lDesc = $labDescriptions[$labKey] ?? 'Configure default features for this lab environment.';
                    
                    $adminIconMap = [
                        'essentials' => 'bxl-tux',
                        'docker_lab' => 'bxl-docker',
                        'n8n' => 'bx-git-repo-forked'
                    ];
                    $bxClass = $adminIconMap[$labKey] ?? 'bx-cube';
                ?>
                <div class="d-flex align-items-center mb-4 p-4 rounded-4" style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05);">
                    <div class="rounded-circle d-flex align-items-center justify-content-center me-4 bg-primary bg-opacity-10" style="width: 72px; height: 72px; flex-shrink: 0;">
                        <?php if ($labKey === 'minio'): ?>
                            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" height="40" width="40" class="text-primary">
                                <path d="M13.2072 0.006c-0.6216 -0.0478 -1.2 0.1943 -1.6211 0.582a2.15 2.15 0 0 0 -0.0938 3.0352l3.4082 3.5507a3.042 3.042 0 0 1 -0.664 4.6875l-0.463 0.2383V7.2853a15.4198 15.4198 0 0 0 -8.0174 10.4862v0.0176l6.5487 -3.3281v7.621L13.7794 24V13.6817l0.8965 -0.4629a4.4432 4.4432 0 0 0 1.2207 -7.0292l-3.371 -3.5254a0.7489 0.7489 0 0 1 0.037 -1.0547 0.7522 0.7522 0 0 1 1.0567 0.0371l0.4668 0.4863 -0.006 0.0059 4.0704 4.2441a0.0566 0.0566 0 0 0 0.082 0 0.06 0.06 0 0 0 0 -0.0703l-3.1406 -5.1425 -0.1484 0.1425 0.1484 -0.1445C14.4945 0.3926 13.8287 0.0538 13.2072 0.006Zm-0.9024 9.8652v2.9941l-4.1523 2.1484a13.9787 13.9787 0 0 1 2.7676 -3.9277 14.1784 14.1784 0 0 1 1.3847 -1.2148z" fill="currentColor"></path>
                            </svg>
                        <?php else: ?>
                            <i class='bx <?= $bxClass ?> text-primary' style="font-size: 2.5rem;"></i>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h4 class="fw-bold text-white mb-1"><?= $labName ?> Features</h4>
                        <p class="text-secondary small mb-0"><?= $lDesc ?></p>
                    </div>
                </div>
                
                <div class="row g-3">
                <?php 
                    $applicableFeatures = [
                        'essentials' => ['always_on', 'http_proxies', 'startup_script', 'expose_web'],
                        'docker_lab' => ['always_on', 'http_proxies', 'startup_script', 'expose_web'],
                        'minio'      => ['always_on', 'startup_script'], // MinIO is public by default, doesn't need expose_web or custom http proxies
                        'n8n'        => ['always_on', 'startup_script']  // n8n is public by default
                    ];
                    
                    foreach ($featuresList as $featKey => $featLabel): 
                        $currentLabSupported = \TomLabs\Labs\LabFeatures::getSupportedFeatures($labKey);
                        $isChecked = in_array($featKey, $currentLabSupported, true);
                        $isApplicable = in_array($featKey, $applicableFeatures[$labKey] ?? []);
                        
                        $featureDetails = [
                            'always_on' => [
                                'icon' => 'bx-power-off',
                                'desc' => 'Keep the lab running indefinitely without automatic shutdown.'
                            ],
                            'http_proxies' => [
                                'icon' => 'bx-globe',
                                'desc' => 'Allow custom HTTP port proxying for web services.'
                            ],
                            'startup_script' => [
                                'icon' => 'bx-code-block',
                                'desc' => 'Execute custom bash scripts on lab initialization.'
                            ],
                            'expose_web' => [
                                'icon' => 'bx-server',
                                'desc' => 'Make standard web ports publicly accessible over the internet.'
                            ]
                        ];
                        $fIcon = $featureDetails[$featKey]['icon'] ?? 'bx-cog';
                        $fDesc = $featureDetails[$featKey]['desc'] ?? 'Configure this feature for the lab.';
                ?>
                
                <?php if ($isApplicable): ?>
                <div class="col-md-6">
                    <div class="d-flex justify-content-between align-items-center p-3 h-100 rounded-4" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05);">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width: 42px; height: 42px; background: rgba(255,255,255,0.05);">
                                <i class='bx <?= $fIcon ?> fs-4 text-primary'></i>
                            </div>
                            <div>
                                <h6 class="mb-1 text-white fw-semibold" style="font-size: 0.9rem;"><?= $featLabel ?></h6>
                                <div class="text-secondary opacity-75 small" style="font-size: 0.7rem; line-height: 1.2;"><?= $fDesc ?></div>
                            </div>
                        </div>
                        <div class="form-check form-switch fs-4 mb-0 ms-2 flex-shrink-0">
                            <input class="form-check-input pointer matrix-feature-toggle" type="checkbox" role="switch" 
                                   data-lab="<?= $labKey ?>" data-feature="<?= $featKey ?>" <?= $isChecked ? 'checked' : '' ?>>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="col-md-6">
                    <div class="d-flex justify-content-between align-items-center p-3 h-100 rounded-4 opacity-50" style="background: rgba(255,255,255,0.01); border: 1px dashed rgba(255,255,255,0.05);">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width: 42px; height: 42px; background: rgba(255,255,255,0.02);">
                                <i class='bx <?= $fIcon ?> fs-4 text-secondary opacity-50'></i>
                            </div>
                            <div>
                                <h6 class="mb-1 text-secondary" style="font-size: 0.9rem;"><del><?= $featLabel ?></del></h6>
                                <div class="text-secondary opacity-50 small" style="font-size: 0.7rem; line-height: 1.2;"><?= $fDesc ?></div>
                            </div>
                        </div>
                        <div class="ms-2 flex-shrink-0">
                            <span class="badge bg-dark border border-secondary border-opacity-50 text-secondary" style="font-size: 0.65rem; font-weight: normal;">N/A</span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php endforeach; ?>
                </div>
            </div>
            <div class="modal-footer border-0 px-4 pb-4 pt-0">
                <button type="button" class="btn btn-secondary rounded-pill px-4 fw-semibold" data-coreui-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>

<script>
async function toggleFeatureAPI(formData, toggleElement, successMsg) {
    const originalState = !toggleElement.checked;
    try {
        const response = await fetch('/api/admin/toggle_feature', {
            method: 'POST',
            body: formData
        });
        const res = await response.json();
        if (res.status === 'success') {
            TomNotify.show(successMsg, 'Saved', 'success', 3000);
        } else {
            TomNotify.show(res.error || 'Failed to update', 'Error', 'error', 4000);
            toggleElement.checked = originalState; // Revert
        }
    } catch(e) {
        TomNotify.show('Network error', 'Error', 'error', 4000);
        toggleElement.checked = originalState;
    }
}

document.querySelectorAll('.global-feature-toggle').forEach(toggle => {
    toggle.addEventListener('change', function() {
        const feature = this.getAttribute('data-feature');
        const state = this.checked;
        const formData = new FormData();
        formData.append('scope', 'global');
        formData.append('feature', feature);
        formData.append('state', state);
        toggleFeatureAPI(formData, this, `Global Override for ${feature} is now ${state ? 'ENABLED' : 'DISABLED'}`);
    });
});

document.querySelectorAll('.master-feature-toggle').forEach(toggle => {
    toggle.addEventListener('change', function() {
        const feature = this.getAttribute('data-feature');
        const state = this.checked;
        const formData = new FormData();
        formData.append('scope', 'master');
        formData.append('feature', feature);
        formData.append('state', state);
        toggleFeatureAPI(formData, this, `Master Switch for ${feature} is now ${state ? 'ON' : 'KILLED'}`);
    });
});

document.querySelectorAll('.matrix-feature-toggle').forEach(toggle => {
    toggle.addEventListener('change', function() {
        const lab = this.getAttribute('data-lab');
        const feature = this.getAttribute('data-feature');
        const state = this.checked;
        const formData = new FormData();
        formData.append('scope', 'matrix');
        formData.append('lab', lab);
        formData.append('feature', feature);
        formData.append('state', state);
        toggleFeatureAPI(formData, this, `${feature} for ${lab} is now ${state ? 'ENABLED' : 'DISABLED'}`);
    });
});

// Dynamic Users Loading with Search and Infinite Scroll
let usersSkip = 0;
const usersLimit = 20;
let usersHasMore = true;
let usersIsLoading = false;
let usersSearchQuery = '';

const usersTableBody = document.getElementById('usersTableBody');
const usersLoadingSpinner = document.getElementById('usersLoadingSpinner');
const usersEmptyState = document.getElementById('usersEmptyState');
const usersTableContainer = document.getElementById('usersTableContainer');
const userSearchInput = document.getElementById('userSearchInput');

async function loadUsers(reset = false) {
    if (usersIsLoading) return;
    if (!reset && !usersHasMore) return;
    
    if (reset) {
        usersSkip = 0;
        usersHasMore = true;
        usersTableBody.innerHTML = '';
        usersEmptyState.classList.add('d-none');
    }
    
    usersIsLoading = true;
    usersLoadingSpinner.classList.remove('d-none');
    
    try {
        const response = await fetch(`/api/admin/get_users?skip=${usersSkip}&limit=${usersLimit}&search=${encodeURIComponent(usersSearchQuery)}`);
        const result = await response.json();
        
        if (result.status === 'success') {
            const users = result.data;
            usersHasMore = result.has_more;
            usersSkip += users.length;
            
            if (reset && users.length === 0) {
                usersEmptyState.classList.remove('d-none');
            } else {
                users.forEach(u => {
                    const tr = document.createElement('tr');
                    
                    const roleBadge = u.role === 'superuser' 
                        ? `<span class="badge bg-warning text-dark"><i class='bx bx-crown me-1'></i>Superuser</span>`
                        : `<span class="badge bg-secondary border border-secondary border-opacity-25">User</span>`;
                        
                    tr.innerHTML = `
                        <td class="ps-4">
                            <div class="d-flex align-items-center">
                                <img src="${u.avatar}" class="rounded-circle me-3" style="width: 40px; height: 40px; object-fit: cover;">
                                <div>
                                    <h6 class="mb-0 fw-semibold text-body">${escapeHtml(u.name)}</h6>
                                    <small class="text-secondary">${escapeHtml(u.email)}</small>
                                </div>
                            </div>
                        </td>
                        <td>${roleBadge}</td>
                        <td class="text-secondary small">${u.created_at}</td>
                        <td class="text-secondary small">${u.last_login}</td>
                        <td class="text-end pe-4">
                            <a href="/admin/user/${encodeURIComponent(u.email)}" class="btn btn-sm btn-outline-primary rounded-pill px-3">View Profile</a>
                        </td>
                    `;
                    usersTableBody.appendChild(tr);
                });
            }
        }
    } catch (e) {
        console.error('Error fetching users:', e);
    } finally {
        usersIsLoading = false;
        usersLoadingSpinner.classList.add('d-none');
    }
}

// Utility to escape HTML
function escapeHtml(unsafe) {
    return (unsafe || '').toString()
         .replace(/&/g, "&amp;")
         .replace(/</g, "&lt;")
         .replace(/>/g, "&gt;")
         .replace(/"/g, "&quot;")
         .replace(/'/g, "&#039;");
}

// Initial Load
document.addEventListener('DOMContentLoaded', () => {
    loadUsers(true);
});

// Infinite Scroll
usersTableContainer.addEventListener('scroll', () => {
    if (usersTableContainer.scrollTop + usersTableContainer.clientHeight >= usersTableContainer.scrollHeight - 50) {
        loadUsers();
    }
});

// Search with Debounce
let searchTimeout;
userSearchInput.addEventListener('input', (e) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        usersSearchQuery = e.target.value;
        loadUsers(true);
    }, 300);
});
</script>
