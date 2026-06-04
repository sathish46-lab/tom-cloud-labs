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
        <div class="card border-0 shadow-sm glass-card rounded-4">
            <div class="card-body p-3">
                <h6 class="fw-bold mb-3 d-flex align-items-center">
                    <i class='bx bx-user-circle fs-5 me-2 text-primary'></i> Profile Settings
                </h6>
                <form id="profileUpdateForm">
                    <div class="d-flex align-items-center mb-3 pb-2 border-bottom border-secondary border-opacity-10">
                        <div class="position-relative me-3 dropdown">
                            <img id="profileAvatarImg" src="<?= $avatar ?>" class="rounded-circle shadow-sm" width="60" height="60" style="object-fit: cover;">
                            <button type="button" class="btn btn-sm btn-primary rounded-circle position-absolute bottom-0 end-0 shadow dropdown-toggle no-caret" style="width:24px; height:24px; padding:0; display:flex; align-items:center; justify-content:center;" title="Change Profile Picture" data-coreui-toggle="dropdown" aria-expanded="false">
                                <i class='bx bx-camera' style="font-size: 0.75rem;"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-dark shadow-sm border border-secondary border-opacity-10" style="font-size: 0.75rem; background: #191c24; min-width: 150px;">
                                <li><a class="dropdown-item py-2" href="#" onclick="document.getElementById('avatarUploadInput').click(); return false;"><i class="bx bx-upload me-2 text-primary"></i> Upload Device</a></li>
                                <li><a class="dropdown-item py-2" href="#" onclick="openChooseAvatarModal(); return false;"><i class="bx bx-image me-2 text-warning"></i> Choose Uploads</a></li>
                            </ul>
                            <input type="file" id="avatarUploadInput" class="d-none" accept="image/png, image/jpeg, image/gif, image/webp">
                        </div>
                        <div>
                            <h6 class="fw-bold mb-0" style="font-size: 0.9rem;">Profile Picture</h6>
                            <p class="small text-secondary mb-0" style="font-size: 0.75rem;">JPG, GIF or PNG. Max size of 800K</p>
                        </div>
                    </div>
                    
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary mb-1" style="font-size: 0.75rem;">First Name</label>
                            <input type="text" class="form-control form-control-sm" name="first_name" placeholder="First Name" value="<?= htmlspecialchars($firstName) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary mb-1" style="font-size: 0.75rem;">Last Name</label>
                            <input type="text" class="form-control form-control-sm" name="last_name" placeholder="Last Name" value="<?= htmlspecialchars($lastName) ?>">
                        </div>
                    </div>
                    
                    <div class="mb-2">
                        <label class="form-label small fw-bold text-secondary mb-1" style="font-size: 0.75rem;">Username</label>
                        <input type="text" class="form-control form-control-sm" value="<?= htmlspecialchars($username) ?>" readonly>
                        <div class="form-text small opacity-75" style="font-size: 0.7rem;">Username cannot be changed.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary mb-1" style="font-size: 0.75rem;">Email Address</label>
                        <input type="email" class="form-control form-control-sm" value="<?= htmlspecialchars($email) ?>" readonly>
                        <div class="form-text small opacity-75" style="font-size: 0.7rem;">Email Address cannot be changed.</div>
                    </div>
                    
                    <button type="submit" id="saveProfileBtn" class="btn btn-sm btn-primary fw-bold px-3 rounded-pill shadow-sm">Save Changes</button>
                </form>
            </div>
        </div>

        <!-- SSH Keys Card -->
        <div class="card border-0 shadow-sm glass-card rounded-4 overflow-hidden mt-4">
            <div class="card-header bg-transparent border-bottom border-secondary border-opacity-10 p-3 d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="fw-bold mb-0 d-flex align-items-center">
                        <i class='bx bx-key fs-5 me-2 text-info'></i> SSH Keys
                    </h6>
                    <p class="text-secondary small mb-0" style="font-size: 0.7rem;">Manage authorized keys for accessing environments.</p>
                </div>
                <button class="btn btn-primary btn-sm fw-bold px-3 py-1 rounded-pill shadow-sm transition-all" style="font-size: 0.75rem;" id="show-add-key-btn">
                    <i class='bx bx-plus me-1'></i> Add
                </button>
            </div>
            <div class="card-body p-3">
                
                <!-- Add Key Form Section -->
                <div id="add-key-section" class="mb-3 d-none animate__animated animate__fadeIn border border-info border-opacity-25 rounded-4 p-3 bg-info bg-opacity-10 shadow-sm">
                    <h6 class="fw-bold mb-3 d-flex align-items-center" style="font-size: 0.8rem;">
                        <i class='bx bx-lock-open-alt text-info me-2'></i> Register Key
                    </h6>
                    <form id="sshAddForm">
                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="fw-bold text-secondary small mb-1" style="font-size: 0.7rem;">Key Label</label>
                                <input type="text" class="form-control form-control-sm" name="title" required placeholder="e.g. MacBook">
                            </div>
                            <div class="col-md-6">
                                <label class="fw-bold text-secondary small mb-1" style="font-size: 0.7rem;">Expiration</label>
                                <input type="date" class="form-control form-control-sm" id="ssh-expiration" name="expiration_date">
                            </div>
                            <div class="col-12">
                                <label class="fw-bold text-secondary small mb-1" style="font-size: 0.7rem;">Key Content</label>
                                <textarea class="form-control form-control-sm font-monospace" name="key" rows="3" required placeholder="ssh-rsa ..."></textarea>
                            </div>
                        </div>
                        <div class="d-flex gap-2 justify-content-end pt-2 border-top border-info border-opacity-10">
                            <button type="button" class="btn btn-sm btn-secondary px-3 rounded-pill" style="font-size: 0.75rem;"
                                onclick="document.getElementById('add-key-section').classList.add('d-none')">Cancel</button>
                            <button type="submit" id="save-key-btn" class="btn btn-sm btn-warning fw-bold px-3 text-dark rounded-pill shadow-sm" style="font-size: 0.75rem;">
                                Save Key
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Keys Table -->
                <div class="table-responsive rounded-3 border border-secondary border-opacity-10 shadow-sm">
                    <table class="table table-hover align-middle mb-0 table-sm" style="font-size: 0.75rem;">
                        <thead class="table-dark">
                            <tr>
                                <th class="ps-2 py-2">Label</th>
                                <th class="py-2">Fingerprint</th>
                                <th class="py-2">Expires</th>
                                <th class="text-end pe-2 py-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($sshKeys)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-4 text-secondary">
                                    <i class='bx bx-key fs-3 opacity-25 mb-2 d-block'></i>
                                    No SSH keys found.
                                </td>
                            </tr>
                            <?php else: foreach ($sshKeys as $key): ?>
                            <tr>
                                <td class="ps-2 fw-bold text-truncate" style="max-width: 80px;" title="<?= htmlspecialchars($key['title']) ?>"><?= htmlspecialchars($key['title']) ?></td>
                                <td><code class="small text-info px-1 py-0.5 bg-info bg-opacity-10 rounded" style="font-size: 0.65rem;" title="<?= $key['fingerprint'] ?>"><?= substr($key['fingerprint'], 0, 10) ?>...</code></td>
                                <td class="fw-semibold <?= $key['expires_at'] && $key['expires_at'] < time() ? 'text-danger' : 'text-warning' ?>" style="font-size: 0.7rem;">
                                    <?= $key['expires_at'] ? date('d M Y', $key['expires_at']) : 'Never' ?>
                                </td>
                                <td class="text-end pe-2">
                                    <button class="btn btn-link text-danger p-0 border-0" onclick="deleteKey('<?= (string)$key['_id'] ?>')" title="Revoke Key">
                                        <i class='bx bx-trash fs-6'></i>
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
    
    <!-- Storage & Security Column -->
    <div class="col-lg-6">
        <div class="row g-4">
            
            <!-- Storage Card -->
            <div class="col-12">
                <div class="card border-0 shadow-sm glass-card rounded-4">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="fw-bold m-0 d-flex align-items-center">
                                <i class='bx bx-server fs-4 me-2 text-warning'></i> Storage & Files
                            </h5>
                            <!-- Circular progress on the right -->
                            <div class="d-flex align-items-center gap-2">
                                <div class="text-end">
                                    <div class="fw-bold text-white" style="font-size: 0.75rem;"><span id="storage-used-text">Loading...</span></div>
                                    <div class="text-secondary" style="font-size: 0.65rem;">of 2 GB used</div>
                                </div>
                                <div class="position-relative d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                    <svg width="36" height="36" viewBox="0 0 36 36" class="transform-rotate-n90">
                                        <circle cx="18" cy="18" r="15" fill="none" stroke="rgba(255, 255, 255, 0.05)" stroke-width="3"></circle>
                                        <circle id="storage-circle-progress" cx="18" cy="18" r="15" fill="none" stroke="#ffc107" stroke-width="3" stroke-dasharray="94.2" stroke-dashoffset="94.2" stroke-linecap="round" style="transition: stroke-dashoffset 0.3s ease;"></circle>
                                    </svg>
                                    <div class="position-absolute" style="font-size: 0.65rem; font-weight: bold; color: #ffc107;" id="storage-percent-text">0%</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- File Manager Toolbar -->
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="d-flex gap-1" id="file-tabs">
                                <button type="button" class="btn btn-xs btn-outline-warning active rounded-pill px-2 py-0.5" style="font-size: 0.7rem;" onclick="switchFilter('all')" id="tab-all">All 0</button>
                                <button type="button" class="btn btn-xs btn-outline-warning rounded-pill px-2 py-0.5" style="font-size: 0.7rem;" onclick="switchFilter('images')" id="tab-images">Images 0</button>
                                <button type="button" class="btn btn-xs btn-outline-warning rounded-pill px-2 py-0.5" style="font-size: 0.7rem;" onclick="switchFilter('others')" id="tab-others">Others 0</button>
                            </div>
                            <div class="btn-group" role="group">
                                <button type="button" class="btn btn-xs btn-outline-secondary py-0.5 px-2" id="btn-view-grid" onclick="switchView('grid')" title="Grid View">
                                    <i class='bx bx-grid-alt'></i>
                                </button>
                                <button type="button" class="btn btn-xs btn-outline-secondary py-0.5 px-2" id="btn-view-list" onclick="switchView('list')" title="List View">
                                    <i class='bx bx-list-ul'></i>
                                </button>
                            </div>
                        </div>

                        <!-- Main Content Explorer Area -->
                        <div id="file-explorer-container" class="mb-3">
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
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-success text-white rounded-pill px-3 py-2 shadow-sm"><i class='bx bx-check-shield me-1'></i> Enabled</span>
                                    <button class="btn btn-sm btn-outline-danger rounded-pill fw-bold px-3 transition-all" id="btn-disable-2fa">Disable</button>
                                </div>
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
    

<!-- Choose Existing Avatar Modal -->
<div class="modal fade" id="chooseAvatarModal" tabindex="-1" aria-labelledby="chooseAvatarModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content border-0 shadow glass-card rounded-4 text-white" style="background: rgba(25, 28, 36, 0.95); backdrop-filter: blur(10px);">
            <div class="modal-header border-bottom border-secondary border-opacity-10">
                <h5 class="modal-title fw-bold" id="chooseAvatarModalLabel" style="font-size: 1rem;">Choose from uploads</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <div class="row g-2 overflow-y-auto px-1" id="choose-avatar-grid" style="max-height: 300px; scrollbar-width: thin;">
                    <!-- Injected dynamically via JS -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Avatar Crop Modal -->
<div class="modal fade" id="avatarCropModal" tabindex="-1" aria-labelledby="avatarCropModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow glass-card rounded-4 text-white" style="background: rgba(25, 28, 36, 0.95); backdrop-filter: blur(10px);">
            <div class="modal-header border-bottom border-secondary border-opacity-10">
                <h5 class="modal-title fw-bold" id="avatarCropModalLabel">Position and size your new avatar</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="avatar-crop-container position-relative overflow-hidden rounded-3 mb-4" style="height: 350px; background: #000; cursor: move; user-select: none; touch-action: none;">
                    <!-- Grid background overlay (checkerboard) -->
                    <div class="avatar-crop-grid position-absolute w-100 h-100 opacity-25"></div>
                    <!-- Image to crop -->
                    <img id="avatarCropPreviewImg" class="position-absolute" style="max-width: none; transform-origin: 0 0; transition: transform 0.05s ease-out;" src="" alt="Preview">
                    <!-- Visible Blue boundary crop box -->
                    <div class="avatar-crop-box position-absolute start-50 top-50 translate-middle border border-2 border-primary rounded-1 shadow" style="width: 220px; height: 220px; pointer-events: none; box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.6) !important;"></div>
                </div>
                
                <!-- Zoom controls -->
                <div class="d-flex align-items-center justify-content-center gap-3 mb-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-circle d-flex align-items-center justify-content-center" id="btnCropZoomOut" style="width: 36px; height: 36px;">
                        <i class='bx bx-zoom-out fs-5'></i>
                    </button>
                    <input type="range" class="form-range flex-grow-1" id="cropZoomSlider" min="0.1" max="3" step="0.01" value="1">
                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-circle d-flex align-items-center justify-content-center" id="btnCropZoomIn" style="width: 36px; height: 36px;">
                        <i class='bx bx-zoom-in fs-5'></i>
                    </button>
                </div>
            </div>
            <div class="modal-footer border-top border-secondary border-opacity-10 justify-content-center pb-4">
                <button type="button" class="btn btn-primary fw-bold px-4 py-2 rounded-pill shadow-sm" id="btnSaveCroppedAvatar">Set new profile picture</button>
            </div>
        </div>
    </div>
</div>

<style>
.no-caret::after {
    display: none !important;
}
.transform-rotate-n90 {
    transform: rotate(-90deg);
}
.gallery-card {
    background: #111418;
    border-radius: 8px;
    padding: 6px;
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
    height: 60px;
    border-radius: 5px;
    object-fit: cover;
    background: #1a1d21;
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.avatar-crop-grid {
    background-image: linear-gradient(45deg, #222 25%, transparent 25%), 
                      linear-gradient(-45deg, #222 25%, transparent 25%), 
                      linear-gradient(45deg, transparent 75%, #222 75%), 
                      linear-gradient(-45deg, transparent 75%, #222 75%);
    background-size: 20px 20px;
    background-position: 0 0, 0 10px, 10px -10px, -10px 0px;
}
.avatar-crop-container {
    box-shadow: inset 0 0 15px rgba(0,0,0,0.9);
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

    // AVATAR UPLOAD & CROP HANDLING
    const cropModalEl = document.getElementById('avatarCropModal');
    const cropModal = coreui.Modal.getOrCreateInstance(cropModalEl);
    const cropPreviewImg = document.getElementById('avatarCropPreviewImg');
    const zoomSlider = document.getElementById('cropZoomSlider');
    const zoomInBtn = document.getElementById('btnCropZoomIn');
    const zoomOutBtn = document.getElementById('btnCropZoomOut');
    const saveCropBtn = document.getElementById('btnSaveCroppedAvatar');
    const avatarInput = document.getElementById('avatarUploadInput');
    const cropContainer = document.querySelector('.avatar-crop-container');
    
    let currentScale = 1;
    let minScale = 0.1;
    let posX = 0;
    let posY = 0;
    let isDragging = false;
    let startX = 0;
    let startY = 0;
    let originalImageWidth = 0;
    let originalImageHeight = 0;
    let selectedFile = null;

    avatarInput.addEventListener('change', function(e) {
        if (!this.files || !this.files[0]) return;
        selectedFile = this.files[0];
        
        if (selectedFile.size > 800 * 1024) {
            alert('File size exceeds 800KB limit.');
            this.value = '';
            return;
        }

        const reader = new FileReader();
        reader.onload = function(event) {
            cropPreviewImg.src = event.target.result;
            cropPreviewImg.onload = function() {
                originalImageWidth = cropPreviewImg.naturalWidth;
                originalImageHeight = cropPreviewImg.naturalHeight;
                
                const scaleX = 220 / originalImageWidth;
                const scaleY = 220 / originalImageHeight;
                minScale = Math.max(scaleX, scaleY);
                currentScale = minScale;
                
                zoomSlider.min = minScale;
                zoomSlider.max = minScale * 4;
                zoomSlider.value = currentScale;
                
                const containerWidth = cropContainer.clientWidth || 466;
                const containerHeight = 350;
                
                posX = (containerWidth - originalImageWidth * currentScale) / 2;
                posY = (containerHeight - originalImageHeight * currentScale) / 2;
                
                updateImageTransform();
                cropModal.show();
            };
        };
        reader.readAsDataURL(selectedFile);
    });

    function updateImageTransform() {
        cropPreviewImg.style.transform = `translate(${posX}px, ${posY}px) scale(${currentScale})`;
    }

    function startDrag(e) {
        isDragging = true;
        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;
        startX = clientX - posX;
        startY = clientY - posY;
        e.preventDefault();
    }
    
    function drag(e) {
        if (!isDragging) return;
        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;
        
        posX = clientX - startX;
        posY = clientY - startY;
        
        const containerWidth = cropContainer.clientWidth || 466;
        const containerHeight = 350;
        const cropBoxSize = 220;
        
        const boxLeft = (containerWidth - cropBoxSize) / 2;
        const boxTop = (containerHeight - cropBoxSize) / 2;
        const boxRight = boxLeft + cropBoxSize;
        const boxBottom = boxTop + cropBoxSize;
        
        const scaledWidth = originalImageWidth * currentScale;
        const scaledHeight = originalImageHeight * currentScale;
        
        if (posX > boxLeft) posX = boxLeft;
        if (posX + scaledWidth < boxRight) posX = boxRight - scaledWidth;
        if (posY > boxTop) posY = boxTop;
        if (posY + scaledHeight < boxBottom) posY = boxBottom - scaledHeight;
        
        updateImageTransform();
    }
    
    function stopDrag() {
        isDragging = false;
    }
    
    cropContainer.addEventListener('mousedown', startDrag);
    cropContainer.addEventListener('mousemove', drag);
    window.addEventListener('mouseup', stopDrag);
    
    cropContainer.addEventListener('touchstart', startDrag, { passive: false });
    cropContainer.addEventListener('touchmove', drag, { passive: false });
    window.addEventListener('touchend', stopDrag);

    function handleZoom(newScale) {
        const containerWidth = cropContainer.clientWidth || 466;
        const containerHeight = 350;
        
        const centerX = containerWidth / 2;
        const centerY = containerHeight / 2;
        
        const imgCenterX = (centerX - posX) / currentScale;
        const imgCenterY = (centerY - posY) / currentScale;
        
        currentScale = Math.max(minScale, Math.min(zoomSlider.max, newScale));
        zoomSlider.value = currentScale;
        
        posX = centerX - imgCenterX * currentScale;
        posY = centerY - imgCenterY * currentScale;
        
        const cropBoxSize = 220;
        const boxLeft = (containerWidth - cropBoxSize) / 2;
        const boxTop = (containerHeight - cropBoxSize) / 2;
        const boxRight = boxLeft + cropBoxSize;
        const boxBottom = boxTop + cropBoxSize;
        const scaledWidth = originalImageWidth * currentScale;
        const scaledHeight = originalImageHeight * currentScale;
        
        if (posX > boxLeft) posX = boxLeft;
        if (posX + scaledWidth < boxRight) posX = boxRight - scaledWidth;
        if (posY > boxTop) posY = boxTop;
        if (posY + scaledHeight < boxBottom) posY = boxBottom - scaledHeight;
        
        updateImageTransform();
    }
    
    zoomSlider.addEventListener('input', function() {
        handleZoom(parseFloat(this.value));
    });
    
    zoomInBtn.addEventListener('click', function() {
        handleZoom(currentScale + 0.1);
    });
    
    zoomOutBtn.addEventListener('click', function() {
        handleZoom(currentScale - 0.1);
    });

    saveCropBtn.addEventListener('click', async function() {
        const containerWidth = cropContainer.clientWidth || 466;
        const containerHeight = 350;
        const cropBoxSize = 220;
        const boxLeft = (containerWidth - cropBoxSize) / 2;
        const boxTop = (containerHeight - cropBoxSize) / 2;
        
        const canvas = document.createElement('canvas');
        canvas.width = cropBoxSize;
        canvas.height = cropBoxSize;
        const ctx = canvas.getContext('2d');
        
        const srcX = (boxLeft - posX) / currentScale;
        const srcY = (boxTop - posY) / currentScale;
        const srcW = cropBoxSize / currentScale;
        const srcH = cropBoxSize / currentScale;
        
        ctx.drawImage(cropPreviewImg, srcX, srcY, srcW, srcH, 0, 0, cropBoxSize, cropBoxSize);
        
        const originalText = saveCropBtn.innerText;
        saveCropBtn.disabled = true;
        saveCropBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Saving...';
        
        canvas.toBlob(async function(blob) {
            if (!blob) {
                alert('Failed to crop image.');
                saveCropBtn.disabled = false;
                saveCropBtn.innerText = originalText;
                return;
            }
            
            const formData = new FormData();
            formData.append('avatar', blob, 'avatar.png');
            
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
                    cropModal.hide();
                    avatarInput.value = '';
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
            } finally {
                saveCropBtn.disabled = false;
                saveCropBtn.innerText = originalText;
            }
        }, 'image/png');
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
    const btnDisable2fa = document.getElementById('btn-disable-2fa');
    const section2fa = document.getElementById('section-2fa-otp');
    const timerDisplay = document.getElementById('timer-2fa');
    const form2fa = document.getElementById('form-2fa-verify');
    const btnVerify2fa = document.getElementById('btn-verify-2fa');
    const msg2faError = document.getElementById('msg-2fa-error');
    const inputOtp = document.getElementById('input-otp-2fa');

    let countdownInterval;
    let isDisabling = false;

    if (btnDisable2fa) {
        btnDisable2fa.onclick = async () => {
            if (confirm("Are you sure you want to disable Two-Factor Authentication? This will make your account less secure. We will send an OTP to verify it's you.")) {
                btnDisable2fa.disabled = true;
                btnDisable2fa.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
                isDisabling = true;
                try {
                    const res = await fetch('/api/account/send_2fa_otp?action=disable');
                    const result = await res.json();
                    if (result.status === 'success') {
                        btnDisable2fa.classList.add('d-none');
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
                        btnDisable2fa.disabled = false;
                        btnDisable2fa.innerText = 'Disable';
                    }
                } catch (err) {
                    alert('Failed to send OTP.');
                    btnDisable2fa.disabled = false;
                    btnDisable2fa.innerText = 'Disable';
                }
            }
        };
    }

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
            const endpoint = isDisabling ? '/api/account/disable_2fa' : '/api/account/verify_2fa';
            try {
                const res = await fetch(endpoint, { method: 'POST', body: formData });
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

let allFiles = [];
let currentFilter = 'all'; // 'all', 'images', 'others'
let currentView = 'grid'; // 'grid', 'list'
let selectedFileIndex = 0;

async function fetchGallery() {
    try {
        const res = await fetch('/api/account/list_files');
        const result = await res.json();
        if(result.status === 'success') {
            allFiles = result.files;
            
            // Update Storage Counters
            document.getElementById('storage-used-text').innerText = result.storage.used_formatted;
            document.getElementById('storage-percent-text').innerText = result.storage.percent + '%';
            
            const circle = document.getElementById('storage-circle-progress');
            if (circle) {
                const percent = result.storage.percent;
                const offset = 94.2 - (percent / 100) * 94.2;
                circle.style.strokeDashoffset = offset;
            }
            
            renderFileExplorer();
        } else {
            document.getElementById('storage-used-text').innerText = 'Error';
            document.getElementById('file-explorer-container').innerHTML = `<div class="col-12 text-center text-danger small py-4">Failed to load files: ${result.error}</div>`;
        }
    } catch(err) {
        console.error('Failed to load gallery', err);
        document.getElementById('storage-used-text').innerText = 'Error';
        document.getElementById('file-explorer-container').innerHTML = `<div class="col-12 text-center text-danger small py-4">Network error loading files.</div>`;
    }
}

function getFilteredFiles() {
    if (currentFilter === 'images') {
        return allFiles.filter(f => f.is_image);
    } else if (currentFilter === 'others') {
        return allFiles.filter(f => !f.is_image);
    }
    return allFiles;
}

function renderFileExplorer() {
    const imgCount = allFiles.filter(f => f.is_image).length;
    const otherCount = allFiles.filter(f => !f.is_image).length;
    document.getElementById('tab-all').innerText = `All ${allFiles.length}`;
    document.getElementById('tab-images').innerText = `Images ${imgCount}`;
    document.getElementById('tab-others').innerText = `Others ${otherCount}`;
    
    ['all', 'images', 'others'].forEach(t => {
        const btn = document.getElementById(`tab-${t}`);
        if (t === currentFilter) {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });
    
    if (currentView === 'grid') {
        document.getElementById('btn-view-grid').classList.add('active');
        document.getElementById('btn-view-list').classList.remove('active');
    } else {
        document.getElementById('btn-view-grid').classList.remove('active');
        document.getElementById('btn-view-list').classList.add('active');
    }
    
    const filtered = getFilteredFiles();
    const container = document.getElementById('file-explorer-container');
    container.innerHTML = '';
    
    if (filtered.length === 0) {
        container.innerHTML = '<div class="col-12 text-center text-secondary small py-4">No files found.</div>';
        return;
    }
    
    if (currentView === 'grid') {
        let gridHtml = '<div class="row g-2 px-1" style="max-height: 220px; overflow-y: auto; overflow-x: hidden; scrollbar-width: thin;">';
        filtered.forEach((file, index) => {
            let thumbHtml = '';
            if(file.is_image) {
                thumbHtml = `<img src="${file.url}" class="gallery-thumb" alt="${file.name}">`;
            } else {
                thumbHtml = `<div class="gallery-thumb"><i class='bx bxs-file fs-1 text-secondary opacity-50'></i></div>`;
            }
            
            gridHtml += `
            <div class="col-4">
                <div class="gallery-card cursor-pointer" onclick="selectGridFile('${file.name}')">
                    ${thumbHtml}
                    <div class="fw-bold small text-truncate" style="max-width: 100%; font-size:0.75rem;" title="${file.name}">${file.name}</div>
                    <div class="d-flex align-items-center justify-content-between mt-1">
                        <span class="text-secondary" style="font-size:0.65rem;">${file.size_formatted}</span>
                        <div class="rounded-circle bg-success" style="width:6px; height:6px;"></div>
                    </div>
                </div>
            </div>`;
        });
        gridHtml += '</div>';
        container.innerHTML = gridHtml;
    } else {
        if (selectedFileIndex >= filtered.length) {
            selectedFileIndex = 0;
        }
        const selectedFile = filtered[selectedFileIndex];
        
        let listHtml = `<div class="row g-3">
            <div class="col-md-7 col-lg-8 border-end border-secondary border-opacity-10 pe-md-3">
                <div class="table-responsive rounded-3 border border-secondary border-opacity-10 shadow-sm" style="max-height: 340px; overflow-y: auto; scrollbar-width: thin;">
                    <table class="table table-hover align-middle mb-0 table-sm" style="font-size: 0.8rem; background: transparent;">
                        <thead class="table-dark">
                            <tr>
                                <th class="ps-2">Name</th>
                                <th>Size</th>
                                <th>Uploaded</th>
                                <th class="text-end pe-2"></th>
                            </tr>
                        </thead>
                        <tbody>`;
        
        filtered.forEach((file, index) => {
            const isSelected = index === selectedFileIndex ? 'table-active fw-bold' : '';
            const formatTime = new Date(file.modified * 1000).toLocaleString('en-US', {
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                hour12: false
            });
            
            let fileIcon = file.is_image ? `<img src="${file.url}" width="20" height="20" class="rounded me-2" style="object-fit: cover;">` : `<i class='bx bxs-file text-secondary fs-5 me-2'></i>`;
            
            listHtml += `
            <tr class="${isSelected} cursor-pointer" onclick="selectListFile(${index})">
                <td class="ps-2">
                    <div class="d-flex align-items-center text-truncate" style="max-width: 140px;" title="${file.name}">
                        ${fileIcon}
                        <span class="text-truncate">${file.name}</span>
                    </div>
                </td>
                <td>${file.size_formatted}</td>
                <td class="small opacity-75">${formatTime}</td>
                <td class="text-end pe-2">
                    <button class="btn btn-link text-danger p-0 border-0" onclick="event.stopPropagation(); deleteGalleryFile('${file.name}')" title="Delete">
                        <i class='bx bx-x fs-5'></i>
                    </button>
                </td>
            </tr>`;
        });
        
        listHtml += `</tbody></table></div></div>`;
        
        if (selectedFile) {
            const uploadDate = new Date(selectedFile.modified * 1000).toLocaleString();
            let detailPreview = selectedFile.is_image ? 
                `<img src="${selectedFile.url}" class="img-fluid rounded mb-3 border border-secondary border-opacity-25" style="max-height: 100px; object-fit: cover; width: 100%;">` : 
                `<div class="d-flex align-items-center justify-content-center bg-dark bg-opacity-20 rounded mb-3 border border-secondary border-opacity-25" style="height: 100px; width: 100%;">
                    <i class='bx bxs-file-blank fs-1 text-secondary opacity-50'></i>
                 </div>`;
            
            listHtml += `
            <div class="col-md-5 col-lg-4 ps-md-3">
                <div class="card bg-transparent border-0">
                    <div class="card-body p-0">
                        ${detailPreview}
                        <div class="mb-2">
                            <div class="text-secondary small fw-bold text-uppercase" style="font-size: 0.65rem;">Filename</div>
                            <div class="text-truncate fw-bold" id="detail-name" style="font-size: 0.75rem;" title="${selectedFile.name}">${selectedFile.name}</div>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <div class="text-secondary small fw-bold text-uppercase" style="font-size: 0.65rem;">Size</div>
                                <div class="fw-semibold" style="font-size: 0.7rem;">${selectedFile.size_formatted}</div>
                            </div>
                            <div class="col-6">
                                <div class="text-secondary small fw-bold text-uppercase" style="font-size: 0.65rem;">Type</div>
                                <div class="fw-semibold text-uppercase" style="font-size: 0.7rem;">${selectedFile.ext || 'File'}</div>
                            </div>
                            <div class="col-12 mt-1">
                                <div class="text-secondary small fw-bold text-uppercase" style="font-size: 0.65rem;">Uploaded</div>
                                <div class="fw-semibold opacity-75" style="font-size: 0.7rem;">${uploadDate}</div>
                            </div>
                        </div>
                        
                        <div class="d-flex flex-wrap gap-1 mt-2 pt-2 border-top border-secondary border-opacity-10">
                            <button class="btn btn-xs btn-outline-warning rounded-pill fw-bold" style="font-size: 0.65rem; padding: 2px 8px;" onclick="renameFilePrompt('${selectedFile.name}')">Rename</button>
                            <button class="btn btn-xs btn-outline-danger rounded-pill fw-bold" style="font-size: 0.65rem; padding: 2px 8px;" onclick="deleteGalleryFile('${selectedFile.name}')">Delete</button>
                            <button class="btn btn-xs btn-outline-info rounded-pill fw-bold" style="font-size: 0.65rem; padding: 2px 8px;" onclick="copyFileUrl('${selectedFile.url}', this)">Copy URL</button>
                        </div>
                    </div>
                </div>
            </div>`;
        }
        
        listHtml += `</div>`;
        container.innerHTML = listHtml;
    }
}

window.switchFilter = function(filter) {
    currentFilter = filter;
    selectedFileIndex = 0;
    renderFileExplorer();
};

window.switchView = function(view) {
    currentView = view;
    renderFileExplorer();
};

window.selectListFile = function(index) {
    selectedFileIndex = index;
    renderFileExplorer();
};

window.selectGridFile = function(filename) {
    const filtered = getFilteredFiles();
    const idx = filtered.findIndex(f => f.name === filename);
    if (idx !== -1) {
        selectedFileIndex = idx;
        currentView = 'list';
        renderFileExplorer();
    }
};

window.renameFilePrompt = async function(oldName) {
    const newName = prompt("Enter new filename:", oldName);
    if (!newName || newName === oldName) return;
    
    try {
        const response = await fetch('/api/account/rename_file', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ old_name: oldName, new_name: newName })
        });
        const result = await response.json();
        if (result.status === 'success') {
            fetchGallery();
        } else {
            alert('Rename Error: ' + result.error);
        }
    } catch (error) {
        alert('Failed to rename file.');
    }
};

window.copyFileUrl = function(relativeUrl, btnEl) {
    const fullUrl = window.location.origin + relativeUrl;
    
    // Create temporary input for fallback copy support
    const tempInput = document.createElement('textarea');
    tempInput.value = fullUrl;
    document.body.appendChild(tempInput);
    tempInput.select();
    
    let copied = false;
    try {
        copied = document.execCommand('copy');
    } catch (e) {
        copied = false;
    }
    document.body.removeChild(tempInput);

    if (!copied && navigator.clipboard) {
        navigator.clipboard.writeText(fullUrl).then(() => {
            copied = true;
            updateBtn();
        });
    } else if (copied) {
        updateBtn();
    } else {
        alert('Failed to copy URL.');
    }

    function updateBtn() {
        if (btnEl) {
            const originalText = btnEl.innerText;
            btnEl.innerText = "Copied!";
            btnEl.classList.replace('btn-outline-info', 'btn-success');
            setTimeout(() => {
                btnEl.innerText = originalText;
                btnEl.classList.replace('btn-success', 'btn-outline-info');
            }, 1500);
        }
    }
};

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
            fetchGallery();
        } else {
            alert('Delete Error: ' + result.error);
        }
    } catch(err) {
        alert('Delete failed.');
    }
}

window.openChooseAvatarModal = function() {
    const grid = document.getElementById('choose-avatar-grid');
    grid.innerHTML = '';
    const images = allFiles.filter(f => f.is_image);
    
    if (images.length === 0) {
        grid.innerHTML = '<div class="col-12 text-center text-secondary small py-4">No uploaded images found. Upload a file first.</div>';
        const chooseModal = coreui.Modal.getOrCreateInstance(document.getElementById('chooseAvatarModal'));
        chooseModal.show();
        return;
    }
    
    images.forEach(img => {
        const cardHtml = `
        <div class="col-4 col-sm-3">
            <div class="gallery-card cursor-pointer p-1 text-center border border-secondary border-opacity-10 rounded" onclick="selectExistingAvatar('${img.url}')">
                <img src="${img.url}" class="rounded w-100 mb-1" style="height: 50px; object-fit: cover;">
                <div class="text-truncate small opacity-75" style="font-size: 0.65rem;" title="${img.name}">${img.name}</div>
            </div>
        </div>`;
        grid.insertAdjacentHTML('beforeend', cardHtml);
    });
    
    const chooseModal = coreui.Modal.getOrCreateInstance(document.getElementById('chooseAvatarModal'));
    chooseModal.show();
};

window.selectExistingAvatar = function(imgUrl) {
    const chooseModal = coreui.Modal.getOrCreateInstance(document.getElementById('chooseAvatarModal'));
    chooseModal.hide();
    
    const cropPreviewImg = document.getElementById('avatarCropPreviewImg');
    const zoomSlider = document.getElementById('cropZoomSlider');
    const cropContainer = document.querySelector('.avatar-crop-container');
    const cropModal = coreui.Modal.getOrCreateInstance(document.getElementById('avatarCropModal'));
    
    cropPreviewImg.src = imgUrl;
    cropPreviewImg.onload = function() {
        const originalWidth = cropPreviewImg.naturalWidth;
        const originalHeight = cropPreviewImg.naturalHeight;
        
        const scaleX = 220 / originalWidth;
        const scaleY = 220 / originalHeight;
        const minScale = Math.max(scaleX, scaleY);
        
        // Expose variables to cropping module scope
        window.currentScale = minScale;
        window.minScale = minScale;
        zoomSlider.min = minScale;
        zoomSlider.max = minScale * 4;
        zoomSlider.value = minScale;
        
        const containerWidth = cropContainer.clientWidth || 466;
        const containerHeight = 350;
        
        window.posX = (containerWidth - originalWidth * minScale) / 2;
        window.posY = (containerHeight - originalHeight * minScale) / 2;
        
        cropPreviewImg.style.transform = `translate(${window.posX}px, ${window.posY}px) scale(${window.currentScale})`;
        cropModal.show();
    };
};

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