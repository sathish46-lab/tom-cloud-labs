<div class="lab-header-section">
    <div class="container-fluid p-0">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <!-- Avatar + Info Section -->
            <div class="d-flex align-items-center gap-4">
                <!-- Avatar Section -->
                <div class="position-relative flex-shrink-0">
                    <div class="avatar" style="height: 5.5rem; width: 5.5rem">
                        <div class="avatar-img d-flex align-items-center justify-content-center bg-dark bg-opacity-25 rounded-circle p-2" style="width: 100%; height: 100%;">
                            <?php if (strpos($cfg['icon'], 'http') === 0): ?>
                                <img src="<?= $cfg['icon'] ?>" style="width: 100%; height: 100%; object-fit: contain;">
                            <?php else: ?>
                                <i class="bx <?= $cfg['icon'] ?>" style="font-size: 3.8rem; color: white;"></i>
                            <?php endif; ?>
                        </div>
                        <span class="avatar-status <?= $isRunning ? 'bg-success' : 'bg-secondary' ?> border-dark ring-2 position-absolute bottom-0 end-0 mb-1 me-1 p-1"></span>
                    </div>
                </div>

                <!-- Info Section -->
                <div class="d-flex flex-column gap-1">
                    <!-- Title -->
                    <h3 class="fw-bold text-white mb-0 ls-tight"><?= $cfg['title'] ?></h3>
                    
                    <!-- Meta Info (ID & Instance) -->
                    <div class="d-flex align-items-center gap-3 small">
                        <?php 
                            // Determine the best "Share" URL
                            $shareUrl = "https://" . $_SERVER['HTTP_HOST'] . "/labs/dashboard/" . $fullHash;
                            if (isset($labConfig['fields'])) {
                                foreach ($labConfig['fields'] as $f) {
                                    if (stripos($f['label'], 'URL') !== false) {
                                        $shareUrl = $f['value'];
                                        break; 
                                    }
                                }
                            }
                        ?>
                        <div class="d-flex align-items-center text-secondary opacity-75">
                            <span class="me-1">Lab ID:</span>
                            <code class="text-info fw-bold"><?= $labType ?></code>
                            <button class="btn btn-link btn-sm p-0 ms-1 btn-copy-utility clipboard" 
                                    data-clipboard-text="<?= $labType ?>"
                                    data-coreui-toggle="tooltip" title="Copy Lab ID">
                                <svg class="icon icon-sm"><use xlink:href="/assets/icons/free.svg#cil-copy"></use></svg>
                            </button>
                        </div>
                        
                        <!-- Share Link -->
                        <div class="d-flex align-items-center text-secondary opacity-75">
                            <button class="btn btn-link btn-sm p-0 btn-copy-utility clipboard text-decoration-none d-flex align-items-center gap-1" 
                                    data-clipboard-text="<?= $shareUrl ?>"
                                    data-coreui-toggle="tooltip" title="Copy Shareable URL">
                                <svg class="icon icon-sm"><use xlink:href="/assets/icons/free.svg#cil-share-boxed"></use></svg>
                                <span class="small" style="font-size: 10px;">Share</span>
                            </button>
                        </div>
                    </div>

                    <!-- Description -->
                    <p class="text-secondary mb-2 opacity-75 small" style="max-width: 650px; line-height: 1.5;">
                        <?= $cfg['desc'] ?>
                    </p>

                    <!-- Badges -->
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <span class="badge bg-primary-gradient border border-white border-opacity-10 rounded-pill px-2 py-1">beta</span>
                        <span class="badge bg-warning-gradient text-dark border border-white border-opacity-10 rounded-pill px-2 py-1" data-coreui-toggle="tooltip" title="Public Access Available">public</span>
                        <span class="badge bg-<?= $isRunning ? 'success' : 'danger' ?>-gradient border border-white border-opacity-10 rounded-pill px-2 py-1"><?= $status ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="btn-group shadow-lg rounded-pill overflow-hidden" role="group">
                <?php if($isRunning): ?>
                    <button class="btn btn-primary btn-code-lab px-4 py-2 fw-bold hover-scale border-0 d-flex align-items-center gap-2" 
                            onclick="launchService(this, '<?= $labType ?>')"
                            data-coreui-toggle="loading-button" data-coreui-spinner-type="grow">
                        <svg class="icon"><use xlink:href="/assets/icons/free.svg#cil-terminal"></use></svg>
                        <span><?= $cfg['action'] ?></span>
                    </button>
                <?php endif; ?>
                
                <button class="btn btn-warning btn-redeploy-lab px-4 py-2 fw-bold hover-scale border-0 d-flex align-items-center gap-2 text-dark" 
                        onclick="handleDeploy(this, '<?= $labType ?>')"
                        data-coreui-toggle="tooltip" title="<?= $isRunning ? 'Redeploy for a fresh instance' : 'Deploy this lab' ?>"
                        data-coreui-toggle="loading-button" data-coreui-spinner-type="grow">
                    <?php if($isRunning): ?>
                        <svg class="icon text-dark"><use xlink:href="/assets/icons/free.svg#cil-reload"></use></svg>
                        <span>Redeploy</span>
                    <?php else: ?>
                        <svg class="icon text-dark"><use xlink:href="/assets/icons/free.svg#cil-cloud-upload"></use></svg>
                        <span>Deploy</span>
                    <?php endif; ?>
                </button>

                <?php if($isRunning): ?>
                    <button id="btn-stop-action" class="btn btn-stop-lab-premium px-4 py-2 fw-bold hover-scale border-0 d-flex align-items-center gap-2" 
                            style="background: linear-gradient(135deg, #e55353 0%, #d93737 100%); color: #fff; box-shadow: 0 4px 15px rgba(229, 83, 83, 0.3);"
                            onclick="handleStop()"
                            data-coreui-toggle="tooltip" title="Stop Instance"
                            data-coreui-toggle="loading-button" data-coreui-spinner-type="grow">
                        <svg class="icon" style="filter: brightness(0) invert(1);"><use xlink:href="/assets/icons/free.svg#cil-media-stop"></use></svg>
                        <span>Stop</span>
                    </button>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- <hr style="margin-top: 0"> -->
        
        <!-- Navigation Tabs -->
        <?php include __DIR__ . '/lab_nav.php'; ?>
        
    </div>
</div>
