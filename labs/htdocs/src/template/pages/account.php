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
                            <img id="profileAvatarImg" src="<?= $avatar ?>" class="rounded-circle shadow-sm account-avatar-img" width="60" height="60">
                            <button type="button" class="btn btn-sm btn-primary rounded-circle position-absolute bottom-0 end-0 shadow dropdown-toggle no-caret account-avatar-camera-btn" title="Change Profile Picture" data-coreui-toggle="dropdown" aria-expanded="false">
                                <i class='bx bx-camera account-fs-xs'></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-dark shadow-sm border border-secondary border-opacity-10 account-avatar-dropdown">
                                <li><a class="dropdown-item py-2" href="#" onclick="document.getElementById('avatarUploadInput').click(); return false;"><i class="bx bx-upload me-2 text-primary"></i> Upload Device</a></li>
                                <li><a class="dropdown-item py-2" href="#" onclick="openChooseAvatarModal(); return false;"><i class="bx bx-image me-2 text-warning"></i> Choose Uploads</a></li>
                            </ul>
                            <input type="file" id="avatarUploadInput" class="d-none" accept="image/png, image/jpeg, image/gif, image/webp">
                        </div>
                        <div>
                            <h6 class="fw-bold mb-0 account-fs-md">Profile Picture</h6>
                            <p class="small text-secondary mb-0 account-fs-xs">JPG, GIF or PNG. Max size of 800K</p>
                        </div>
                    </div>
                    
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary mb-1 account-fs-xs">First Name</label>
                            <input type="text" class="form-control form-control-sm" name="first_name" placeholder="First Name" value="<?= htmlspecialchars($firstName) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary mb-1 account-fs-xs">Last Name</label>
                            <input type="text" class="form-control form-control-sm" name="last_name" placeholder="Last Name" value="<?= htmlspecialchars($lastName) ?>">
                        </div>
                    </div>
                    
                    <div class="mb-2">
                        <label class="form-label small fw-bold text-secondary mb-1 account-fs-xs">Username</label>
                        <input type="text" class="form-control form-control-sm" value="<?= htmlspecialchars($username) ?>" readonly>
                        <div class="form-text small opacity-75 account-fs-xxs">Username cannot be changed.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary mb-1 account-fs-xs">Email Address</label>
                        <input type="email" class="form-control form-control-sm" value="<?= htmlspecialchars($email) ?>" readonly>
                        <div class="form-text small opacity-75 account-fs-xxs">Email Address cannot be changed.</div>
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
                    <p class="text-secondary small mb-0 account-fs-xxs">Manage authorized keys for accessing environments.</p>
                </div>
                <button class="btn btn-primary btn-sm fw-bold px-3 py-1 rounded-pill shadow-sm transition-all account-fs-xs" id="show-add-key-btn">
                    <i class='bx bx-plus me-1'></i> Add
                </button>
            </div>
            <div class="card-body p-3">
                
                <!-- Add Key Form Section -->
                <div id="add-key-section" class="mb-3 d-none animate__animated animate__fadeIn border border-info border-opacity-25 rounded-4 p-3 bg-info bg-opacity-10 shadow-sm">
                    <h6 class="fw-bold mb-3 d-flex align-items-center account-fs-sm">
                        <i class='bx bx-lock-open-alt text-info me-2'></i> Register Key
                    </h6>
                    <form id="sshAddForm">
                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="fw-bold text-secondary small mb-1 account-fs-xxs">Key Label</label>
                                <input type="text" class="form-control form-control-sm" name="title" required placeholder="e.g. MacBook">
                            </div>
                            <div class="col-md-6">
                                <label class="fw-bold text-secondary small mb-1 account-fs-xxs">Expiration</label>
                                <input type="date" class="form-control form-control-sm" id="ssh-expiration" name="expiration_date">
                            </div>
                            <div class="col-12">
                                <label class="fw-bold text-secondary small mb-1 account-fs-xxs">Key Content</label>
                                <textarea class="form-control form-control-sm font-monospace" name="key" rows="3" required placeholder="ssh-rsa ..."></textarea>
                            </div>
                        </div>
                        <div class="d-flex gap-2 justify-content-end pt-2 border-top border-info border-opacity-10">
                            <button type="button" class="btn btn-sm btn-secondary px-3 rounded-pill account-fs-xs"
                                onclick="document.getElementById('add-key-section').classList.add('d-none')">Cancel</button>
                            <button type="submit" id="save-key-btn" class="btn btn-sm btn-warning fw-bold px-3 text-dark rounded-pill shadow-sm account-fs-xs">
                                Save Key
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Keys Table -->
                <div class="table-responsive rounded-3 border border-secondary border-opacity-10 shadow-sm">
                    <table class="table table-hover align-middle mb-0 table-sm account-table">
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
                                <td class="ps-2 fw-bold text-truncate account-key-title" title="<?= htmlspecialchars($key['title']) ?>"><?= htmlspecialchars($key['title']) ?></td>
                                <td><code class="small text-info px-1 py-0.5 bg-info bg-opacity-10 rounded account-fs-tiny" title="<?= $key['fingerprint'] ?>"><?= substr($key['fingerprint'], 0, 10) ?>...</code></td>
                                <td class="fw-semibold <?= $key['expires_at'] && $key['expires_at'] < time() ? 'text-danger' : 'text-warning' ?> account-fs-xxs">
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
                                <button type="button" class="btn btn-sm btn-link text-secondary p-0 ms-2 border-0" onclick="refreshGallery(this)" title="Refresh Files">
                                    <i class='bx bx-refresh fs-4'></i>
                                </button>
                            </h5>
                            <!-- Circular progress on the right -->
                            <div class="d-flex align-items-center gap-2">
                                <div class="text-end">
                                    <div class="fw-bold text-white account-fs-xs"><span id="storage-used-text">Loading...</span></div>
                                    <div class="text-secondary account-fs-tiny">of 2 GB used</div>
                                </div>
                                <div class="position-relative d-flex align-items-center justify-content-center account-storage-wrapper">
                                    <svg width="36" height="36" viewBox="0 0 36 36" class="transform-rotate-n90">
                                        <circle cx="18" cy="18" r="15" fill="none" stroke="rgba(255, 255, 255, 0.05)" stroke-width="3"></circle>
                                        <circle id="storage-circle-progress" cx="18" cy="18" r="15" fill="none" stroke="#ffc107" stroke-width="3" stroke-dasharray="94.2" stroke-dashoffset="94.2" stroke-linecap="round" class="account-storage-circle"></circle>
                                    </svg>
                                    <div class="position-absolute account-storage-text" id="storage-percent-text">0%</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- File Manager Toolbar -->
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="d-flex gap-1" id="file-tabs">
                                <button type="button" class="btn btn-xs btn-outline-warning active rounded-pill px-2 py-0.5 account-tab-btn" onclick="switchFilter('all')" id="tab-all">All 0</button>
                                <button type="button" class="btn btn-xs btn-outline-warning rounded-pill px-2 py-0.5 account-tab-btn" onclick="switchFilter('images')" id="tab-images">Images 0</button>
                                <button type="button" class="btn btn-xs btn-outline-warning rounded-pill px-2 py-0.5 account-tab-btn" onclick="switchFilter('others')" id="tab-others">Others 0</button>
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
                            <button class="btn btn-outline-warning rounded-pill fw-bold btn-sm shadow-sm px-3" id="btnUploadGallery" onclick="document.getElementById('genericUploadInput').click()">
                                <i class='bx bx-cloud-upload me-1'></i> Upload new
                            </button>
                            <input type="file" id="genericUploadInput" class="d-none" multiple>
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
                                    <div class="small text-success text-opacity-75 account-verify-text">Your email address has been successfully verified.</div>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-4 pt-2">
                            <div>
                                <div class="fw-bold small mb-1">Two-Factor Authentication</div>
                                <div class="small text-secondary account-fs-xs">Add an extra layer of security</div>
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
                            <p class="small text-secondary mb-3 account-fs-xs">A 6-digit code has been sent to your email. It expires in exactly 1 minute.</p>
                            <form id="form-2fa-verify" class="d-flex gap-2">
                                <input type="text" class="form-control form-control-sm text-center fw-bold fs-5 tracking-widest account-otp-input" name="otp" id="input-otp-2fa" maxlength="6" placeholder="------" required autocomplete="off">
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
        <div class="modal-content border-0 shadow glass-card rounded-4 text-white account-modal-content">
            <div class="modal-header border-bottom border-secondary border-opacity-10">
                <h5 class="modal-title fw-bold account-modal-title" id="chooseAvatarModalLabel">Choose from uploads</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <div class="row g-2 overflow-y-auto px-1 account-grid-scroll" id="choose-avatar-grid">
                    <!-- Injected dynamically via JS -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Avatar Crop Modal -->
<div class="modal fade" id="avatarCropModal" tabindex="-1" aria-labelledby="avatarCropModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow glass-card rounded-4 text-white account-modal-content">
            <div class="modal-header border-bottom border-secondary border-opacity-10">
                <h5 class="modal-title fw-bold" id="avatarCropModalLabel">Position and size your new avatar</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="avatar-crop-container position-relative overflow-hidden rounded-3 mb-4 account-crop-container">
                    <!-- Grid background overlay (checkerboard) -->
                    <div class="avatar-crop-grid position-absolute w-100 h-100 opacity-25"></div>
                    <!-- Image to crop -->
                    <img id="avatarCropPreviewImg" class="position-absolute account-crop-preview" src="" alt="Preview">
                    <!-- Visible Blue boundary crop box -->
                    <div class="avatar-crop-box position-absolute start-50 top-50 translate-middle border border-2 border-primary rounded-1 shadow account-crop-box"></div>
                </div>
                
                <!-- Zoom controls -->
                <div class="d-flex align-items-center justify-content-center gap-3 mb-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-circle d-flex align-items-center justify-content-center account-crop-btn" id="btnCropZoomOut">
                        <i class='bx bx-zoom-out fs-5'></i>
                    </button>
                    <input type="range" class="form-range flex-grow-1" id="cropZoomSlider" min="0.1" max="3" step="0.01" value="1">
                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-circle d-flex align-items-center justify-content-center account-crop-btn" id="btnCropZoomIn">
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
<script>
window.allFiles = window.allFiles || [];
window.currentFilter = window.currentFilter || 'all';
window.currentView = window.currentView || 'grid';
window.selectedFileIndex = window.selectedFileIndex || 0;
window.renderedFileCount = window.renderedFileCount || 6;

function initAccountScripts() {
    if (window._accountScriptsInitialized) return;
    if (document.readyState === 'loading' || typeof coreui === 'undefined' || !coreui.Modal) {
        setTimeout(initAccountScripts, 50);
        return;
    }
    window._accountScriptsInitialized = true;
    const showBtn = document.getElementById('show-add-key-btn');
    const section = document.getElementById('add-key-section');
    const expInput = document.getElementById('ssh-expiration');
    const addForm = document.getElementById('sshAddForm');
    const saveBtn = document.getElementById('save-key-btn');

    if (showBtn && section && expInput) {
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
    }

    // AVATAR UPLOAD & CROP HANDLING
    const cropModalEl = document.getElementById('avatarCropModal');
    const cropModal = cropModalEl ? (coreui.Modal.getInstance(cropModalEl) || new coreui.Modal(cropModalEl)) : null;
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
                    document.querySelectorAll('#profileAvatarImg, .dropdown-toggle img, .header-avatar, img[alt*="Avatar"], img[src*="avatar"]').forEach(img => {
                        if (img) img.src = result.avatar_url;
                    });
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
                const fname = profileForm.querySelector('[name="first_name"]')?.value || '';
                const lname = profileForm.querySelector('[name="last_name"]')?.value || '';
                const fullName = (fname + ' ' + lname).trim();
                if (fullName) {
                    document.querySelectorAll('.user-name, .profile-name, #header-user-name').forEach(el => {
                        if (el) el.innerText = fullName;
                    });
                }
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
                // Success: Update partial page to see the new key in the table without reload
                if (window.htmx) {
                    htmx.ajax('GET', location.pathname + location.search, '#main-content');
                } else {
                    window.location.reload();
                }
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
        if (!this.files || !this.files.length) return;
        
        const uploadBtn = document.getElementById('btnUploadGallery') || document.querySelector('button[onclick*="genericUploadInput"]');
        const originalBtnHtml = uploadBtn ? uploadBtn.innerHTML : '';
        if (uploadBtn) {
            uploadBtn.disabled = true;
            uploadBtn.innerHTML = `<i class='bx bx-loader-alt bx-spin me-1'></i> Uploading (${this.files.length})...`;
        }

        let successCount = 0;
        let errorMessages = [];

        for (let i = 0; i < this.files.length; i++) {
            const file = this.files[i];
            if (file.size > 5 * 1024 * 1024) {
                errorMessages.push(`"${file.name}": exceeds 5MB limit.`);
                continue;
            }
            
            const formData = new FormData();
            formData.append('file', file);
            
            try {
                const response = await fetch('/api/account/upload_file', { method: 'POST', body: formData });
                const result = await response.json();
                if (result.status === 'success') {
                    successCount++;
                } else {
                    errorMessages.push(`"${file.name}": ${result.error}`);
                }
            } catch (error) {
                errorMessages.push(`"${file.name}": Upload network error.`);
            }
        }

        if (uploadBtn) {
            uploadBtn.disabled = false;
            uploadBtn.innerHTML = originalBtnHtml;
        }

        if (successCount > 0) {
            await fetchGallery(); // Refresh gallery with new files
        }

        if (errorMessages.length > 0) {
            alert("Some files could not be uploaded:\n\n" + errorMessages.join("\n"));
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
                    setTimeout(() => {
                        if (window.htmx) {
                            htmx.ajax('GET', location.pathname + location.search, '#main-content');
                        } else {
                            window.location.reload();
                        }
                    }, 1000);
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
} // end initAccountScripts
initAccountScripts();

window.allFiles = window.allFiles || [];
window.currentFilter = window.currentFilter || 'all';
window.currentView = window.currentView || 'grid';
window.selectedFileIndex = window.selectedFileIndex || 0;
window.galleryOffset = window.galleryOffset || 0;
window.galleryLimit = 6;
window.galleryHasMore = false;
window.galleryTotalCount = 0;
window.galleryImagesCount = 0;
window.galleryOthersCount = 0;
window.galleryFilteredCount = 0;
window.isFetchingGallery = false;

let allFiles = window.allFiles;
let currentFilter = window.currentFilter;
let currentView = window.currentView;
let selectedFileIndex = window.selectedFileIndex;

window.refreshGallery = async function(btn) {
    if (window.isFetchingGallery) return;
    if (btn) {
        const icon = btn.querySelector('i');
        if (icon) icon.classList.add('bx-spin');
    }
    await fetchGallery(true, false);
    if (btn) {
        const icon = btn.querySelector('i');
        if (icon) icon.classList.remove('bx-spin');
    }
};

async function fetchGallery(resetCount = true, loadMore = false) {
    if (window.isFetchingGallery) return;
    window.isFetchingGallery = true;
    try {
        if (resetCount) {
            window.galleryOffset = 0;
        } else if (loadMore) {
            window.galleryOffset += window.galleryLimit;
        }
        const res = await fetch(`/api/account/list_files?limit=${window.galleryLimit}&offset=${window.galleryOffset}&filter=${window.currentFilter}`);
        const result = await res.json();
        if(result.status === 'success') {
            if (resetCount || window.galleryOffset === 0) {
                allFiles = result.files;
            } else {
                allFiles = allFiles.concat(result.files);
            }
            window.allFiles = allFiles;
            window.galleryHasMore = result.has_more;
            window.galleryTotalCount = result.total_count || allFiles.length;
            window.galleryImagesCount = result.images_count || 0;
            window.galleryOthersCount = result.others_count || 0;
            window.galleryFilteredCount = result.filtered_count || allFiles.length;
            
            // Update Storage Counters
            const usedEl = document.getElementById('storage-used-text');
            if (usedEl) usedEl.innerText = result.storage.used_formatted;
            const percentEl = document.getElementById('storage-percent-text');
            if (percentEl) percentEl.innerText = result.storage.percent + '%';
            
            const circle = document.getElementById('storage-circle-progress');
            if (circle) {
                const percent = result.storage.percent;
                const offset = 94.2 - (percent / 100) * 94.2;
                circle.style.strokeDashoffset = offset;
            }
            
            renderFileExplorer(resetCount);
        } else {
            document.getElementById('storage-used-text').innerText = 'Error';
            document.getElementById('file-explorer-container').innerHTML = `<div class="col-12 text-center text-danger small py-4">Failed to load files: ${result.error} <button class="btn btn-sm btn-link text-warning p-0 ms-1" onclick="fetchGallery(true)">Retry</button></div>`;
            if (window.TomNotify) window.TomNotify.show("Failed to load storage files: " + (result.error || "Unknown error"), "Warning", "warning", 4000);
        }
    } catch(err) {
        console.error('Failed to load gallery', err);
        document.getElementById('storage-used-text').innerText = 'Error';
        document.getElementById('file-explorer-container').innerHTML = `<div class="col-12 text-center text-danger small py-4">Network error loading files. <button class="btn btn-sm btn-link text-warning p-0 ms-1" onclick="fetchGallery(true)">Retry</button></div>`;
        if (window.TomNotify) window.TomNotify.show("Error loading storage files. Click refresh or retry.", "Warning", "warning", 4000);
    } finally {
        window.isFetchingGallery = false;
    }
}
window.fetchGallery = fetchGallery;

function getFilteredFiles() {
    if (currentFilter === 'images') {
        return allFiles.filter(f => f.is_image);
    } else if (currentFilter === 'others') {
        return allFiles.filter(f => !f.is_image);
    }
    return allFiles;
}

function renderFileExplorer(resetCount = true) {
    const tabAll = document.getElementById('tab-all');
    if (tabAll) tabAll.innerText = `All ${window.galleryTotalCount || allFiles.length}`;
    const tabImages = document.getElementById('tab-images');
    if (tabImages) tabImages.innerText = `Images ${window.galleryImagesCount || 0}`;
    const tabOthers = document.getElementById('tab-others');
    if (tabOthers) tabOthers.innerText = `Others ${window.galleryOthersCount || 0}`;
    
    ['all', 'images', 'others'].forEach(t => {
        const btn = document.getElementById(`tab-${t}`);
        if (btn) {
            if (t === currentFilter) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        }
    });
    
    if (currentView === 'grid') {
        document.getElementById('btn-view-grid')?.classList.add('active');
        document.getElementById('btn-view-list')?.classList.remove('active');
    } else {
        document.getElementById('btn-view-grid')?.classList.remove('active');
        document.getElementById('btn-view-list')?.classList.add('active');
    }
    
    const filtered = getFilteredFiles();
    const container = document.getElementById('file-explorer-container');
    if (!container) return;

    const scrollBox = container.querySelector('.account-grid-scroll-sm, .account-table-scroll');
    const oldScrollTop = (scrollBox && !resetCount) ? scrollBox.scrollTop : 0;
    
    container.innerHTML = '';
    
    if (filtered.length === 0) {
        container.innerHTML = '<div class="col-12 text-center text-secondary small py-4">No files found.</div>';
        return;
    }

    const displayedFiles = allFiles;
    const hasMore = window.galleryHasMore;
    const totalMatching = window.galleryFilteredCount || window.galleryTotalCount;
    const loadMoreBtnHtml = hasMore ? `
        <div class="col-12 text-center py-2 border-top border-secondary border-opacity-10 mt-2">
            <button type="button" class="btn btn-xs btn-outline-warning rounded-pill px-3 py-1 account-fs-xs" onclick="loadMoreGalleryFiles(this)">
                <i class='bx bx-down-arrow-alt me-1'></i>Showing ${displayedFiles.length} of ${totalMatching} — Scroll or Click for more (+6)
            </button>
        </div>` : '';
    
    if (currentView === 'grid') {
        let gridHtml = '<div class="row g-2 px-1 account-grid-scroll-sm" onscroll="handleGalleryScroll(this)">';
        displayedFiles.forEach((file) => {
            const index = filtered.indexOf(file);
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
                    <div class="fw-bold small text-truncate account-fs-xs" title="${file.name}">${file.name}</div>
                    <div class="d-flex align-items-center justify-content-between mt-1">
                        <span class="text-secondary account-fs-tiny">${file.size_formatted}</span>
                        <div class="rounded-circle bg-success account-dot-sm"></div>
                    </div>
                </div>
            </div>`;
        });
        gridHtml += loadMoreBtnHtml;
        gridHtml += '</div>';
        container.innerHTML = gridHtml;
    } else {
        if (selectedFileIndex >= filtered.length) {
            selectedFileIndex = 0;
        }
        const selectedFile = filtered[selectedFileIndex];
        
        let listHtml = `<div class="row g-3">
            <div class="col-md-7 col-lg-8 border-end border-secondary border-opacity-10 pe-md-3">
                <div class="table-responsive rounded-3 border border-secondary border-opacity-10 shadow-sm account-table-scroll" onscroll="handleGalleryScroll(this)">
                    <table class="table table-hover align-middle mb-0 table-sm account-table-sm">
                        <thead class="table-dark">
                            <tr>
                                <th class="ps-2">Name</th>
                                <th>Size</th>
                                <th>Uploaded</th>
                                <th class="text-end pe-2"></th>
                            </tr>
                        </thead>
                        <tbody>`;
        
        displayedFiles.forEach((file) => {
            const index = filtered.indexOf(file);
            const isSelected = index === selectedFileIndex ? 'table-active fw-bold' : '';
            const formatTime = new Date(file.modified * 1000).toLocaleString('en-US', {
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                hour12: false
            });
            
            let fileIcon = file.is_image ? `<img src="${file.url}" width="20" height="20" class="rounded me-2 account-file-icon-img">` : `<i class='bx bxs-file text-secondary fs-5 me-2'></i>`;
            
            listHtml += `
            <tr class="${isSelected} cursor-pointer" onclick="selectListFile(${index})">
                <td class="ps-2">
                    <div class="d-flex align-items-center text-truncate account-file-name-truncate" title="${file.name}">
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
        
        listHtml += `</tbody></table>${loadMoreBtnHtml}</div></div>`;
        
        if (selectedFile) {
            const uploadDate = new Date(selectedFile.modified * 1000).toLocaleString();
            let detailPreview = selectedFile.is_image ? 
                `<img src="${selectedFile.url}" class="img-fluid rounded mb-3 border border-secondary border-opacity-25 account-file-preview-img">` : 
                `<div class="d-flex align-items-center justify-content-center bg-dark bg-opacity-20 rounded mb-3 border border-secondary border-opacity-25 account-file-preview-box">
                    <i class='bx bxs-file-blank fs-1 text-secondary opacity-50'></i>
                 </div>`;
            
            listHtml += `
            <div class="col-md-5 col-lg-4 ps-md-3">
                <div class="card bg-transparent border-0">
                    <div class="card-body p-0">
                        ${detailPreview}
                        <div class="mb-2">
                            <div class="text-secondary small fw-bold text-uppercase account-fs-tiny">Filename</div>
                            <div class="text-truncate fw-bold account-fs-xs" id="detail-name" title="${selectedFile.name}">${selectedFile.name}</div>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <div class="text-secondary small fw-bold text-uppercase account-fs-tiny">Size</div>
                                <div class="fw-semibold account-fs-xxs">${selectedFile.size_formatted}</div>
                            </div>
                            <div class="col-6">
                                <div class="text-secondary small fw-bold text-uppercase account-fs-tiny">Type</div>
                                <div class="fw-semibold text-uppercase account-fs-xxs">${selectedFile.ext || 'File'}</div>
                            </div>
                            <div class="col-12 mt-1">
                                <div class="text-secondary small fw-bold text-uppercase account-fs-tiny">Uploaded</div>
                                <div class="fw-semibold opacity-75 account-fs-xxs">${uploadDate}</div>
                            </div>
                        </div>
                        
                        <div class="d-flex flex-wrap gap-1 mt-2 pt-2 border-top border-secondary border-opacity-10">
                            <button class="btn btn-xs btn-outline-warning rounded-pill fw-bold account-action-btn" onclick="renameFilePrompt('${selectedFile.name}')">Rename</button>
                            <button class="btn btn-xs btn-outline-danger rounded-pill fw-bold account-action-btn" onclick="deleteGalleryFile('${selectedFile.name}')">Delete</button>
                            <button class="btn btn-xs btn-outline-info rounded-pill fw-bold account-action-btn" onclick="copyFileUrl('${selectedFile.url}', this)">Copy URL</button>
                        </div>
                    </div>
                </div>
            </div>`;
        }
        
        listHtml += `</div>`;
        container.innerHTML = listHtml;
    }

    if (!resetCount) {
        const newScrollBox = container.querySelector('.account-grid-scroll-sm, .account-table-scroll');
        if (newScrollBox) newScrollBox.scrollTop = oldScrollTop;
    }
}

window.handleGalleryScroll = function(el) {
    if (window.isFetchingGallery || !window.galleryHasMore) return;
    if (el.scrollTop > 20 && el.scrollTop + el.clientHeight >= el.scrollHeight - 10) {
        window.loadMoreGalleryFiles();
    }
};

window.loadMoreGalleryFiles = async function(btnEl) {
    if (window.isFetchingGallery || !window.galleryHasMore) return;
    if (btnEl) {
        btnEl.disabled = true;
        btnEl.innerHTML = `<i class='bx bx-loader-alt bx-spin me-1'></i>Loading more files...`;
    }
    await fetchGallery(false, true);
};

window.switchFilter = function(filter) {
    window.currentFilter = filter;
    currentFilter = filter;
    selectedFileIndex = 0;
    fetchGallery(true, false);
};

window.switchView = function(view) {
    currentView = view;
    renderFileExplorer(true);
};

window.selectListFile = function(index) {
    selectedFileIndex = index;
    renderFileExplorer(false);
};

window.selectGridFile = function(filename) {
    const filtered = getFilteredFiles();
    const idx = filtered.findIndex(f => f.name === filename);
    if (idx !== -1) {
        selectedFileIndex = idx;
        currentView = 'list';
        renderFileExplorer(false);
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

window.deleteGalleryFile = async function deleteGalleryFile(filename) {
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
        const chooseModalEl = document.getElementById('chooseAvatarModal');
        const chooseModal = chooseModalEl ? (coreui.Modal.getInstance(chooseModalEl) || new coreui.Modal(chooseModalEl)) : null;
        if (chooseModal) chooseModal.show();
        return;
    }
    
    images.forEach(img => {
        const cardHtml = `
        <div class="col-4 col-sm-3">
            <div class="gallery-card cursor-pointer p-1 text-center border border-secondary border-opacity-10 rounded" onclick="selectExistingAvatar('${img.url}')">
                <img src="${img.url}" class="rounded w-100 mb-1 account-gallery-img-sm">
                <div class="text-truncate small opacity-75 account-fs-tiny" title="${img.name}">${img.name}</div>
            </div>
        </div>`;
        grid.insertAdjacentHTML('beforeend', cardHtml);
    });
    
    const chooseModalEl = document.getElementById('chooseAvatarModal');
    const chooseModal = chooseModalEl ? (coreui.Modal.getInstance(chooseModalEl) || new coreui.Modal(chooseModalEl)) : null;
    if (chooseModal) chooseModal.show();
};

window.selectExistingAvatar = function(imgUrl) {
    const chooseModalEl = document.getElementById('chooseAvatarModal');
    const chooseModal = chooseModalEl ? (coreui.Modal.getInstance(chooseModalEl) || new coreui.Modal(chooseModalEl)) : null;
    if (chooseModal) chooseModal.hide();
    
    const cropPreviewImg = document.getElementById('avatarCropPreviewImg');
    const zoomSlider = document.getElementById('cropZoomSlider');
    const cropContainer = document.querySelector('.avatar-crop-container');
    const cropModalEl = document.getElementById('avatarCropModal');
    const cropModal = cropModalEl ? (coreui.Modal.getInstance(cropModalEl) || new coreui.Modal(cropModalEl)) : null;
    
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

window.deleteKey = async function deleteKey(id) {
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
    if (result.status === 'success') {
        if (window.htmx) {
            htmx.ajax('GET', location.pathname + location.search, '#main-content');
        } else {
            window.location.reload();
        }
    }
};
</script>