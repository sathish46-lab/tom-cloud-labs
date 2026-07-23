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
<style>
.admin-card { transition: all 0.2s; }
.admin-card:hover { transform: translateY(-2px); box-shadow: 0 8px 22px rgba(0,0,0,0.28); }
.lab-matrix-card { transition: all 0.2s; cursor: pointer; }
.lab-matrix-card:hover { transform: translateY(-3px); box-shadow: 0 8px 22px rgba(0,0,0,0.28); }
</style>

<div class="blur banner mb-3 rounded-0 border-bottom border-secondary border-opacity-10">
    <div class="card-body p-0" style="margin-left: 1rem; margin-right: 1rem;">
        <div class="container-fluid pt-3 pb-1">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="position-relative flex-shrink-0">
                        <div class="avatar lab-header-avatar">
                            <div class="avatar-img d-flex align-items-center justify-content-center bg-dark bg-opacity-25 rounded-circle p-2">
                                <i class='bx bx-crown'></i>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex flex-column gap-1">
                        <h3 class="fw-bold mb-0 ls-tight lab-header-title">Superuser Admin Panel</h3>
                        <div class="d-flex flex-wrap align-items-center gap-2 small">
                            <div class="d-flex align-items-center text-secondary">
                                <span class="me-1 opacity-75">Manage users and global feature flags</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        
            <!-- Navigation Tabs -->
            <?php include __DIR__ . '/admin_nav.php'; ?>
        </div>
    </div>
</div>

<div class="container-fluid px-4">

    <!-- Users Table -->
    <div class="card border-0 rounded-4 blur shadow-sm">
        <div class="card-header border-bottom border-body-secondary border-opacity-10 bg-transparent py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold"><i class='bx bx-user-circle text-primary me-2'></i>Registered Users</h5>
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-sm rounded-pill px-2 fw-semibold" id="refreshUsersBtn" onclick="loadUsers(true)" style="background: rgba(255,165,0,0.15); color: #ffa502; border: 1px solid rgba(255,165,0,0.3);">
                    <i class='bx bx-refresh'></i>
                </button>
                <div class="position-relative" style="max-width: 250px; width: 100%;">
                    <i class='bx bx-search position-absolute top-50 start-0 translate-middle-y ms-3 text-body-secondary'></i>
                    <input type="text" id="userSearchInput" class="form-control rounded-pill ps-5 bg-body-secondary bg-opacity-25 border-0 shadow-none" placeholder="Search users...">
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive" id="usersTableContainer" style="max-height: 500px; overflow-y: auto;">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-dark sticky-top" style="z-index: 1;">
                        <tr>
                            <th class="ps-4">User</th>
                            <th>Role</th>
                            <th>Created</th>
                            <th>Last Login</th>
                            <th class="text-end pe-4">Action</th>
                        </tr>
                    </thead>
                    <tbody class="border-top-0" id="usersTableBody">
                        <tr><td colspan="5" class="text-center text-body-secondary py-4">Click <i class='bx bx-refresh mx-1'></i> to load</td></tr>
                    </tbody>
                </table>
                <div id="usersLoadingSpinner" class="text-center py-4 text-body-secondary d-none">
                    <div class="spinner-border spinner-border-sm me-2" role="status"></div> Loading users...
                </div>
                <div id="usersEmptyState" class="text-center py-5 d-none">
                    <i class='bx bx-user-x fs-1 text-body-secondary opacity-50 mb-3'></i>
                    <h5 class="text-body-secondary fw-semibold">No users found</h5>
                </div>
            </div>
        </div>
    </div>
</div>


<script>
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
                        : `<span class="badge bg-body-secondary">User</span>`;
                        
                    tr.innerHTML = `
                        <td class="ps-4">
                            <div class="d-flex align-items-center">
                                <img src="${u.avatar}" class="rounded-circle me-3" style="width: 40px; height: 40px; object-fit: cover;">
                                <div>
                                    <h6 class="mb-0 fw-semibold">${escapeHtml(u.name)}</h6>
                                    <small class="text-body-secondary">${escapeHtml(u.email)}</small>
                                </div>
                            </div>
                        </td>
                        <td>${roleBadge}</td>
                        <td class="text-body-secondary small">${u.created_at}</td>
                        <td class="text-body-secondary small">${u.last_login}</td>
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

function escapeHtml(unsafe) {
    return (unsafe || '').toString()
         .replace(/&/g, "&amp;")
         .replace(/</g, "&lt;")
         .replace(/>/g, "&gt;")
         .replace(/"/g, "&quot;")
         .replace(/'/g, "&#039;");
}

document.addEventListener('DOMContentLoaded', () => {
    // No auto-load — click refresh to load
});

usersTableContainer.addEventListener('scroll', () => {
    if (usersTableContainer.scrollTop + usersTableContainer.clientHeight >= usersTableContainer.scrollHeight - 50) {
        loadUsers();
    }
});

let searchTimeout;
userSearchInput.addEventListener('input', (e) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        usersSearchQuery = e.target.value;
        loadUsers(true);
    }, 300);
});
</script>
