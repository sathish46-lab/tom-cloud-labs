<?php 
$sshKeys = Session::get('ssh_keys', []); 
$user = Session::getUser();
$username = $user?->getUsername() ?? 'Guest';
$email = $user?->getEmail() ?? 'guest@example.com';
$avatar = Session::getAvatar();
?>

<div class="lab-header-section mb-4">
    <h1 class="fw-bold theme-text m-0">Account Settings</h1>
    <p class="text-secondary opacity-75 small">Manage your profile, security, storage, and SSH keys.</p>
</div>

<div class="row g-4">
    <!-- Profile Settings Card -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm glass-card rounded-4 h-100">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-4 d-flex align-items-center">
                    <i class='bx bx-user-circle fs-4 me-2 text-primary'></i> Profile Settings
                </h5>
                <form id="profileUpdateForm">
                    <div class="d-flex align-items-center mb-4 pb-3 border-bottom border-secondary border-opacity-10">
                        <div class="position-relative me-4">
                            <img src="<?= $avatar ?>" class="rounded-circle shadow-sm" width="80" height="80" style="object-fit: cover;">
                            <button type="button" class="btn btn-sm btn-primary rounded-circle position-absolute bottom-0 end-0 shadow" style="width:30px; height:30px; padding:0; display:flex; align-items:center; justify-content:center;" title="Change Profile Picture">
                                <i class='bx bx-camera'></i>
                            </button>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-1">Profile Picture</h6>
                            <p class="small text-secondary mb-0">JPG, GIF or PNG. Max size of 800K</p>
                        </div>
                    </div>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">First Name</label>
                            <input type="text" class="form-control" placeholder="First Name">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">Last Name</label>
                            <input type="text" class="form-control" placeholder="Last Name">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Username</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($username) ?>" readonly>
                        <div class="form-text small opacity-75">Username cannot be changed.</div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-secondary">Email Address</label>
                        <input type="email" class="form-control" value="<?= htmlspecialchars($email) ?>">
                    </div>
                    
                    <button type="button" class="btn btn-primary fw-bold px-4 rounded-pill shadow-sm" onclick="alert('Profile update backend integration pending.')">Save Changes</button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Storage & Security Column -->
    <div class="col-lg-6">
        <div class="row g-4">
            
            <!-- Storage Card -->
            <div class="col-12">
                <div class="card border-0 shadow-sm glass-card rounded-4">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-4 d-flex align-items-center">
                            <i class='bx bx-server fs-4 me-2 text-warning'></i> Storage & Files
                        </h5>
                        
                        <div class="d-flex justify-content-between align-items-end mb-2">
                            <div>
                                <span class="fs-4 fw-bold">4.5 GB</span>
                                <span class="text-secondary small">/ 10 GB Used</span>
                            </div>
                            <span class="badge bg-warning text-dark rounded-pill px-3 py-2 border border-warning border-opacity-50 shadow-sm">45%</span>
                        </div>
                        
                        <div class="progress mb-4 bg-secondary bg-opacity-25 shadow-inner" style="height: 10px; border-radius: 10px;">
                            <div class="progress-bar bg-warning rounded-pill shadow-sm" style="width: 45%"></div>
                        </div>
                        
                        <h6 class="fw-bold small text-secondary mb-3 text-uppercase ls-1" style="font-size: 0.7rem;">Recent Files</h6>
                        <div class="list-group list-group-flush border-0">
                            <div class="list-group-item bg-transparent px-0 py-2 d-flex justify-content-between align-items-center border-secondary border-opacity-10">
                                <div class="d-flex align-items-center">
                                    <i class='bx bxs-file-archive text-danger fs-3 me-3 opacity-75'></i>
                                    <div>
                                        <div class="fw-bold small">project_backup.zip</div>
                                        <div class="text-secondary" style="font-size: 0.7rem;">1.2 GB • Today, 10:30 AM</div>
                                    </div>
                                </div>
                                <button class="btn btn-sm btn-link text-secondary p-0 hover-theme-text transition-all"><i class='bx bx-download fs-5'></i></button>
                            </div>
                            <div class="list-group-item bg-transparent px-0 py-2 d-flex justify-content-between align-items-center border-secondary border-opacity-10">
                                <div class="d-flex align-items-center">
                                    <i class='bx bxs-file-blank text-info fs-3 me-3 opacity-75'></i>
                                    <div>
                                        <div class="fw-bold small">sys_logs_2023.log</div>
                                        <div class="text-secondary" style="font-size: 0.7rem;">45 MB • Yesterday</div>
                                    </div>
                                </div>
                                <button class="btn btn-sm btn-link text-secondary p-0 hover-theme-text transition-all"><i class='bx bx-download fs-5'></i></button>
                            </div>
                        </div>
                        
                        <button class="btn btn-outline-primary btn-sm rounded-pill w-100 mt-3 fw-bold transition-all border-opacity-50">Manage All Files</button>
                    </div>
                </div>
            </div>
            
            <!-- Account Verification Card -->
            <div class="col-12">
                <div class="card border-0 shadow-sm glass-card rounded-4">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-4 d-flex align-items-center">
                            <i class='bx bx-check-shield fs-4 me-2 text-success'></i> Security & Verification
                        </h5>
                        <div class="d-flex align-items-center justify-content-between mb-3 p-3 rounded-4 bg-success bg-opacity-10 border border-success border-opacity-25 shadow-sm">
                            <div class="d-flex align-items-center">
                                <i class='bx bxs-badge-check text-success fs-1 me-3'></i>
                                <div>
                                    <div class="fw-bold text-success mb-1">Account Verified</div>
                                    <div class="small text-success text-opacity-75" style="line-height: 1.2;">Your email address has been successfully verified.</div>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-4 pt-2">
                            <div>
                                <div class="fw-bold small mb-1">Two-Factor Authentication</div>
                                <div class="small text-secondary" style="font-size: 0.75rem;">Add an extra layer of security</div>
                            </div>
                            <button class="btn btn-sm btn-outline-secondary rounded-pill fw-bold px-3 transition-all">Enable 2FA</button>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
    
    <!-- SSH Keys Card (Full Width at Bottom) -->
    <div class="col-12">
        <div class="card border-0 shadow-sm glass-card rounded-4 overflow-hidden">
            <div class="card-header bg-transparent border-bottom border-secondary border-opacity-10 p-4 d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="fw-bold mb-1 d-flex align-items-center">
                        <i class='bx bx-key fs-4 me-2 text-info'></i> SSH Keys
                    </h5>
                    <p class="text-secondary small mb-0">Manage authorized keys for accessing your Labs environments via SSH.</p>
                </div>
                <button class="btn btn-primary btn-sm fw-bold px-4 py-2 rounded-pill shadow-sm transition-all" id="show-add-key-btn">
                    <i class='bx bx-plus me-1'></i> Add New Key
                </button>
            </div>
            <div class="card-body p-4 pt-4">
                
                <!-- Add Key Form Section (Hidden by default) -->
                <div id="add-key-section" class="mb-4 d-none animate__animated animate__fadeIn border border-info border-opacity-25 rounded-4 p-4 bg-info bg-opacity-10 shadow-sm">
                    <h6 class="fw-bold mb-4 d-flex align-items-center">
                        <i class='bx bx-lock-open-alt text-info me-2'></i> Register New Public Key
                    </h6>
                    <form id="sshAddForm">
                        <div class="row g-4 mb-4">
                            <div class="col-md-6">
                                <label class="fw-bold text-secondary small mb-2">Key Label</label>
                                <input type="text" class="form-control" name="title" required placeholder="e.g. Work MacBook Pro">
                            </div>
                            <div class="col-md-6">
                                <label class="fw-bold text-secondary small mb-2">Expiration</label>
                                <input type="date" class="form-control" id="ssh-expiration" name="expiration_date">
                            </div>
                            <div class="col-12">
                                <label class="fw-bold text-secondary small mb-2">Public Key Content</label>
                                <textarea class="form-control font-monospace" name="key" rows="4" required placeholder="Begins with 'ssh-rsa' or 'ssh-ed25519'"></textarea>
                            </div>
                        </div>
                        <div class="d-flex gap-2 justify-content-end pt-2 border-top border-info border-opacity-10">
                            <button type="button" class="btn btn-secondary px-4 rounded-pill"
                                onclick="document.getElementById('add-key-section').classList.add('d-none')">Cancel</button>
                            <button type="submit" id="save-key-btn" class="btn btn-warning fw-bold px-4 text-dark rounded-pill shadow-sm">
                                Verify and Save
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Keys Table -->
                <div class="table-responsive rounded-4 border border-secondary border-opacity-10 shadow-sm">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-dark border-0">
                            <tr>
                                <th class="ps-4 py-3 text-uppercase ls-1" style="font-size: 0.75rem;">Label</th>
                                <th class="py-3 text-uppercase ls-1" style="font-size: 0.75rem;">Fingerprint (SHA256)</th>
                                <th class="py-3 text-uppercase ls-1" style="font-size: 0.75rem;">Created</th>
                                <th class="py-3 text-uppercase ls-1" style="font-size: 0.75rem;">Expires</th>
                                <th class="text-end pe-4 py-3 text-uppercase ls-1" style="font-size: 0.75rem;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($sshKeys)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-5 text-secondary">
                                    <i class='bx bx-key fs-1 opacity-25 mb-3 d-block'></i>
                                    No SSH keys found. Add one to access your Labs via SSH.
                                </td>
                            </tr>
                            <?php else: foreach ($sshKeys as $key): ?>
                            <tr>
                                <td class="ps-4 fw-bold"><?= htmlspecialchars($key['title']) ?></td>
                                <td><code class="small text-info px-2 py-1 bg-info bg-opacity-10 rounded"><?= $key['fingerprint'] ?></code></td>
                                <td class="small"><?= date('d M Y', $key['created_at']) ?></td>
                                <td class="small fw-semibold <?= $key['expires_at'] && $key['expires_at'] < time() ? 'text-danger' : 'text-warning' ?>">
                                    <?= $key['expires_at'] ? date('d M Y', $key['expires_at']) : 'Never' ?>
                                </td>
                                <td class="text-end pe-4">
                                    <button class="btn btn-sm btn-outline-danger border-0 rounded-circle transition-all shadow-sm" style="width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center;"
                                        onclick="deleteKey('<?= (string)$key['_id'] ?>')" title="Revoke Key">
                                        <i class='bx bx-trash'></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
                
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const showBtn = document.getElementById('show-add-key-btn');
    const section = document.getElementById('add-key-section');
    const expInput = document.getElementById('ssh-expiration');
    const addForm = document.getElementById('sshAddForm');
    const saveBtn = document.getElementById('save-key-btn');

    showBtn.onclick = () => {
        section.classList.toggle('d-none');
        if (!section.classList.contains('d-none')) {
            const expiry = new Date();
            expiry.setMonth(expiry.getMonth() + 6);
            expInput.value = expiry.toISOString().split('T')[0];
            // Scroll to the section smoothly
            section.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    };

    // PROFESSIONAL FORM HANDLING
    addForm.onsubmit = async (e) => {
        e.preventDefault(); // Stop standard page redirect

        // 1. Show Loading state on button
        const originalText = saveBtn.innerText;
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Verifying...';

        try {
            const formData = new FormData(addForm);
            // Submit to API in the background
            const response = await fetch('/api/account/ssh_add', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.status === 'success') {
                // Success: Reload page normally to see the new key in the table
                window.location.reload();
            } else {
                alert('Error: ' + result.error);
                saveBtn.disabled = false;
                saveBtn.innerText = originalText;
            }
        } catch (error) {
            console.error('Submission failed:', error);
            alert('Server error occurred.');
            saveBtn.disabled = false;
            saveBtn.innerText = originalText;
        }
    };
});

async function deleteKey(id) {
    if (!confirm("Revoke this SSH key? Your labs will be synchronized automatically.")) return;


    const res = await fetch('/api/account/ssh_delete', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            id: id
        })
    });
    const result = await res.json();
    if (result.status === 'success') window.location.reload();
}
</script>