<?php 
$sshKeys = Session::get('ssh_keys', []); 
$user = Session::getUser();
$username = $user?->getUsername() ?? 'Guest';
$email = $user?->getEmail() ?? 'guest@example.com';
$firstName = $user?->getFirstName() ?? '';
$lastName = $user?->getLastName() ?? '';
$avatar = Session::getAvatar();
$is2faEnabled = $user?->getTwoFactorEnabled() ?? false;
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
                            <img id="profileAvatarImg" src="<?= $avatar ?>" class="rounded-circle shadow-sm" width="80" height="80" style="object-fit: cover;">
                            <button type="button" class="btn btn-sm btn-primary rounded-circle position-absolute bottom-0 end-0 shadow" style="width:30px; height:30px; padding:0; display:flex; align-items:center; justify-content:center;" title="Change Profile Picture" onclick="document.getElementById('avatarUploadInput').click()">
                                <i class='bx bx-camera'></i>
                            </button>
                            <input type="file" id="avatarUploadInput" class="d-none" accept="image/png, image/jpeg, image/gif, image/webp">
                        </div>
                        <div>
                            <h6 class="fw-bold mb-1">Profile Picture</h6>
                            <p class="small text-secondary mb-0">JPG, GIF or PNG. Max size of 800K</p>
                        </div>
                    </div>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">First Name</label>
                            <input type="text" class="form-control" name="first_name" placeholder="First Name" value="<?= htmlspecialchars($firstName) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">Last Name</label>
                            <input type="text" class="form-control" name="last_name" placeholder="Last Name" value="<?= htmlspecialchars($lastName) ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Username</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($username) ?>" readonly>
                        <div class="form-text small opacity-75">Username cannot be changed.</div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-secondary">Email Address</label>
                        <input type="email" class="form-control" value="<?= htmlspecialchars($email) ?>" readonly>
                        <div class="form-text small opacity-75">Email Address cannot be changed.</div>
                    </div>
                    
                    <button type="submit" id="saveProfileBtn" class="btn btn-primary fw-bold px-4 rounded-pill shadow-sm">Save Changes</button>
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
                                <span class="fs-4 fw-bold" id="storage-used-text">Loading...</span>
                                <span class="text-secondary small">/ 2 GB Used</span>
                            </div>
                            <span class="badge bg-warning text-dark rounded-pill px-3 py-2 border border-warning border-opacity-50 shadow-sm" id="storage-percent-text">0%</span>
                        </div>
                        
                        <div class="progress mb-4 bg-secondary bg-opacity-25 shadow-inner" style="height: 10px; border-radius: 10px;">
                            <div class="progress-bar bg-warning rounded-pill shadow-sm" id="storage-progress-bar" style="width: 0%"></div>
                        </div>
                        
                        <h6 class="fw-bold small text-secondary mb-3 text-uppercase ls-1" style="font-size: 0.7rem;">Your Files</h6>
                        
                        <!-- Dynamic Gallery Grid -->
                        <div class="row g-2 mb-3" id="file-gallery-container">
                            <!-- Files will be injected here via JS -->
                            <div class="col-12 text-center text-secondary small py-3"><span class="spinner-border spinner-border-sm"></span> Loading files...</div>
                        </div>
                        
                        <div class="d-flex justify-content-end border-top border-secondary border-opacity-10 pt-3">
                            <button class="btn btn-outline-warning rounded-pill fw-bold btn-sm shadow-sm px-3" onclick="document.getElementById('genericUploadInput').click()">
                                <i class='bx bx-cloud-upload me-1'></i> Upload new
                            </button>
                            <input type="file" id="genericUploadInput" class="d-none">
                        </div>
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
                            <?php if ($is2faEnabled): ?>
                                <span class="badge bg-success text-white rounded-pill px-3 py-2 shadow-sm"><i class='bx bx-check-shield me-1'></i> Enabled</span>
                            <?php else: ?>
                                <button class="btn btn-sm btn-outline-secondary rounded-pill fw-bold px-3 transition-all" id="btn-enable-2fa">Enable 2FA</button>
                            <?php endif; ?>
                        </div>
                        
                        <!-- 2FA OTP Entry Section (Hidden initially) -->
                        <div id="section-2fa-otp" class="mt-3 p-3 border border-warning border-opacity-25 bg-warning bg-opacity-10 rounded-4 shadow-sm d-none animate__animated animate__fadeIn">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="fw-bold mb-0 text-warning"><i class='bx bx-envelope me-1'></i> Enter OTP</h6>
                                <span class="badge bg-danger text-white rounded-pill" id="timer-2fa">01:00</span>
                            </div>
                            <p class="small text-secondary mb-3" style="font-size:0.75rem;">A 6-digit code has been sent to your email. It expires in exactly 1 minute.</p>
                            <form id="form-2fa-verify" class="d-flex gap-2">
                                <input type="text" class="form-control form-control-sm text-center fw-bold fs-5 tracking-widest" name="otp" id="input-otp-2fa" maxlength="6" placeholder="------" required autocomplete="off" style="letter-spacing: 5px;">
                                <button type="submit" class="btn btn-sm btn-warning fw-bold px-3 text-dark rounded-pill" id="btn-verify-2fa">Verify</button>
                            </form>
                            <div id="msg-2fa-error" class="text-danger small mt-2 d-none fw-bold"></div>
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

<style>
.gallery-card {
    background: #111418;
    border-radius: 10px;
    padding: 8px;
    position: relative;
    border: 1px solid rgba(255, 255, 255, 0.05);
    transition: all 0.2s ease;
}
[data-coreui-theme="light"] .gallery-card {
    background: #f8f9fa;
    border: 1px solid rgba(0, 0, 0, 0.1);
}
.gallery-card:hover {
    border-color: rgba(255, 255, 255, 0.2);
}
[data-coreui-theme="light"] .gallery-card:hover {
    border-color: rgba(0, 0, 0, 0.2);
}
.gallery-card:hover .delete-overlay {
    opacity: 1;
}
.delete-overlay {
    position: absolute;
    top: 10px;
    right: 10px;
    opacity: 0;
    transition: opacity 0.2s;
    background: rgba(0,0,0,0.6);
    border-radius: 50%;
}
.gallery-thumb {
    width: 100%;
    height: 90px;
    border-radius: 6px;
    object-fit: cover;
    background: #1a1d21;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
}
</style>

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

    // AVATAR UPLOAD HANDLING
    document.getElementById('avatarUploadInput').addEventListener('change', async function(e) {
        if (!this.files || !this.files[0]) return;
        
        const file = this.files[0];
        if (file.size > 800 * 1024) {
            alert('File size exceeds 800KB limit.');
            this.value = ''; // clear
            return;
        }
        
        const formData = new FormData();
        formData.append('avatar', file);
        
        const imgElement = document.getElementById('profileAvatarImg');
        const originalSrc = imgElement.src;
        imgElement.style.opacity = '0.5';
        
        try {
            const response = await fetch('/api/account/update_avatar', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            
            if (result.status === 'success') {
                imgElement.src = result.avatar_url;
                imgElement.style.opacity = '1';
                // Optional: reload page to update the navbar avatar instantly
                // window.location.reload();
            } else {
                alert('Error: ' + result.error);
                imgElement.src = originalSrc;
                imgElement.style.opacity = '1';
            }
        } catch (error) {
            console.error('Upload failed:', error);
            alert('Failed to upload image.');
            imgElement.src = originalSrc;
            imgElement.style.opacity = '1';
        }
        
        // Clear the input
        this.value = '';
    });

    // PROFILE FORM HANDLING
    const profileForm = document.getElementById('profileUpdateForm');
    const saveProfileBtn = document.getElementById('saveProfileBtn');
    
    profileForm.onsubmit = async (e) => {
        e.preventDefault();
        const originalText = saveProfileBtn.innerText;
        saveProfileBtn.disabled = true;
        saveProfileBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Saving...';
        
        try {
            const formData = new FormData(profileForm);
            const response = await fetch('/api/account/update_profile', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            
            if (result.status === 'success') {
                saveProfileBtn.innerHTML = '<i class="bx bx-check me-1"></i> Saved!';
                saveProfileBtn.classList.replace('btn-primary', 'btn-success');
                setTimeout(() => {
                    saveProfileBtn.innerHTML = originalText;
                    saveProfileBtn.classList.replace('btn-success', 'btn-primary');
                    saveProfileBtn.disabled = false;
                }, 2000);
            } else {
                alert('Error: ' + result.error);
                saveProfileBtn.disabled = false;
                saveProfileBtn.innerText = originalText;
            }
        } catch (error) {
            console.error('Update failed:', error);
            alert('Failed to update profile.');
            saveProfileBtn.disabled = false;
            saveProfileBtn.innerText = originalText;
        }
    };

    // PROFESSIONAL FORM HANDLING
    // addForm and saveBtn are already declared at the top of the script

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
    
    // FILE GALLERY LOGIC
    fetchGallery();
    
    document.getElementById('genericUploadInput').addEventListener('change', async function(e) {
        if (!this.files || !this.files[0]) return;
        const file = this.files[0];
        if (file.size > 5 * 1024 * 1024) {
            alert('File size exceeds 5MB limit.');
            this.value = ''; return;
        }
        
        const formData = new FormData();
        formData.append('file', file);
        
        try {
            const response = await fetch('/api/account/upload_file', { method: 'POST', body: formData });
            const result = await response.json();
            if (result.status === 'success') {
                fetchGallery(); // Refresh gallery
            } else {
                alert('Upload Error: ' + result.error);
            }
        } catch (error) {
            alert('Upload failed.');
        }
        this.value = '';
    });

    // TWO-FACTOR AUTHENTICATION LOGIC
    const btnEnable2fa = document.getElementById('btn-enable-2fa');
    const section2fa = document.getElementById('section-2fa-otp');
    const timerDisplay = document.getElementById('timer-2fa');
    const form2fa = document.getElementById('form-2fa-verify');
    const btnVerify2fa = document.getElementById('btn-verify-2fa');
    const msg2faError = document.getElementById('msg-2fa-error');
    const inputOtp = document.getElementById('input-otp-2fa');

    let countdownInterval;

    if (btnEnable2fa) {
        btnEnable2fa.onclick = async () => {
            btnEnable2fa.disabled = true;
            btnEnable2fa.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Sending...';
            
            try {
                const res = await fetch('/api/account/send_2fa_otp');
                const result = await res.json();
                
                if (result.status === 'success') {
                    btnEnable2fa.classList.add('d-none');
                    section2fa.classList.remove('d-none');
                    inputOtp.focus();
                    
                    // Start 60 second timer
                    let timeLeft = 60;
                    timerDisplay.innerText = "01:00";
                    timerDisplay.classList.replace('bg-danger', 'bg-warning');
                    
                    clearInterval(countdownInterval);
                    countdownInterval = setInterval(() => {
                        timeLeft--;
                        let secs = timeLeft < 10 ? '0' + timeLeft : timeLeft;
                        timerDisplay.innerText = "00:" + secs;
                        
                        if (timeLeft <= 10) {
                            timerDisplay.classList.replace('bg-warning', 'bg-danger');
                        }
                        
                        if (timeLeft <= 0) {
                            clearInterval(countdownInterval);
                            inputOtp.disabled = true;
                            btnVerify2fa.disabled = true;
                            msg2faError.innerText = "OTP Expired! Refresh the page to try again.";
                            msg2faError.classList.remove('d-none');
                        }
                    }, 1000);
                } else {
                    alert('Error: ' + result.error);
                    btnEnable2fa.disabled = false;
                    btnEnable2fa.innerText = 'Enable 2FA';
                }
            } catch (err) {
                alert('Failed to send request.');
                btnEnable2fa.disabled = false;
                btnEnable2fa.innerText = 'Enable 2FA';
            }
        };
    }

    if (form2fa) {
        form2fa.onsubmit = async (e) => {
            e.preventDefault();
            btnVerify2fa.disabled = true;
            btnVerify2fa.innerText = 'Checking...';
            msg2faError.classList.add('d-none');
            
            const formData = new FormData(form2fa);
            try {
                const res = await fetch('/api/account/verify_2fa', { method: 'POST', body: formData });
                const result = await res.json();
                
                if (result.status === 'success') {
                    clearInterval(countdownInterval);
                    timerDisplay.innerText = "Verified!";
                    timerDisplay.classList.replace('bg-danger', 'bg-success');
                    timerDisplay.classList.replace('bg-warning', 'bg-success');
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    msg2faError.innerText = result.error;
                    msg2faError.classList.remove('d-none');
                    btnVerify2fa.disabled = false;
                    btnVerify2fa.innerText = 'Verify';
                }
            } catch (err) {
                msg2faError.innerText = "Server error occurred.";
                msg2faError.classList.remove('d-none');
                btnVerify2fa.disabled = false;
                btnVerify2fa.innerText = 'Verify';
            }
        };
    }
});

async function fetchGallery() {
    try {
        const res = await fetch('/api/account/list_files');
        const result = await res.json();
        if(result.status === 'success') {
            // Update Storage Counters
            document.getElementById('storage-used-text').innerText = result.storage.used_formatted;
            document.getElementById('storage-percent-text').innerText = result.storage.percent + '%';
            document.getElementById('storage-progress-bar').style.width = result.storage.percent + '%';
            
            // Render Cards
            const container = document.getElementById('file-gallery-container');
            container.innerHTML = '';
            
            if(result.files.length === 0) {
                container.innerHTML = '<div class="col-12 text-center text-secondary small py-4">No files uploaded yet.</div>';
                return;
            }
            
            result.files.forEach(file => {
                let thumbHtml = '';
                if(file.is_image) {
                    thumbHtml = `<img src="${file.url}" class="gallery-thumb" alt="${file.name}">`;
                } else {
                    thumbHtml = `<div class="gallery-thumb"><i class='bx bxs-file fs-1 text-secondary opacity-50'></i></div>`;
                }
                
                const card = `
                <div class="col-6 col-md-4">
                    <div class="gallery-card">
                        ${thumbHtml}
                        <button class="btn btn-sm text-danger delete-overlay p-1" onclick="deleteGalleryFile('${file.name}')" title="Delete File">
                            <i class='bx bx-x fs-5'></i>
                        </button>
                        <div class="fw-bold small text-truncate" style="max-width: 100%; font-size:0.75rem;" title="${file.name}">${file.name}</div>
                        <div class="d-flex align-items-center mt-1">
                            <span class="text-secondary" style="font-size:0.65rem;">${file.size_formatted}</span>
                            <div class="rounded-circle bg-success ms-2" style="width:6px; height:6px;"></div>
                        </div>
                    </div>
                </div>`;
                container.insertAdjacentHTML('beforeend', card);
            });
        } else {
            document.getElementById('storage-used-text').innerText = 'Error';
            document.getElementById('file-gallery-container').innerHTML = `<div class="col-12 text-center text-danger small py-4">Failed to load files: ${result.error}</div>`;
        }
    } catch(err) {
        console.error('Failed to load gallery', err);
        document.getElementById('storage-used-text').innerText = 'Error';
        document.getElementById('file-gallery-container').innerHTML = `<div class="col-12 text-center text-danger small py-4">Network error loading files.</div>`;
    }
}

async function deleteGalleryFile(filename) {
    if(!confirm('Are you sure you want to delete ' + filename + '?')) return;
    try {
        const res = await fetch('/api/account/delete_file', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({filename: filename})
        });
        const result = await res.json();
        if(result.status === 'success') {
            fetchGallery(); // Refresh UI
        } else {
            alert('Delete Error: ' + result.error);
        }
    } catch(err) {
        alert('Delete failed.');
    }
}

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