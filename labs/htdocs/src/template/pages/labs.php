<div class="lab-header-section">
    <div class="header-text">
        <h1>Lab Environment</h1>
        <p>
            Deploy and manage your personal development instances.
            Instant access to Ubuntu, n8n, Node-RED, and AI Labs.
        </p>
    </div>

    <div class="header-actions">
    <a href="https://blog.tomweb.fun" target="_blank" class="header-btn">
        <i class='bx bx-book'></i> Lab Blogs
    </a>
    
    <div class="header-stat">
        <span>Active Labs: <?= Session::get('running_count', 0) ?>/<?= Session::get('total_labs', 0) ?></span>
    </div>
</div>
</div>

<div class="row g-4 mb-4">
    <?php foreach(Session::get('labs_list', []) as $lab): ?>
    <div class="col-12 col-md-4">
        <div class="card h-100 border hovermini border-secondary border-opacity-10 shadow-sm rounded-4 position-relative">
            
            <div class="position-absolute end-0 top-50 translate-middle-y pe-3 opacity-10" style="z-index: 1;">
                <?php if ($lab['id'] === 'minio'): ?>
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" height="140" width="140">
                        <path d="M13.2072 0.006c-0.6216 -0.0478 -1.2 0.1943 -1.6211 0.582a2.15 2.15 0 0 0 -0.0938 3.0352l3.4082 3.5507a3.042 3.042 0 0 1 -0.664 4.6875l-0.463 0.2383V7.2853a15.4198 15.4198 0 0 0 -8.0174 10.4862v0.0176l6.5487 -3.3281v7.621L13.7794 24V13.6817l0.8965 -0.4629a4.4432 4.4432 0 0 0 1.2207 -7.0292l-3.371 -3.5254a0.7489 0.7489 0 0 1 0.037 -1.0547 0.7522 0.7522 0 0 1 1.0567 0.0371l0.4668 0.4863 -0.006 0.0059 4.0704 4.2441a0.0566 0.0566 0 0 0 0.082 0 0.06 0.06 0 0 0 0 -0.0703l-3.1406 -5.1425 -0.1484 0.1425 0.1484 -0.1445C14.4945 0.3926 13.8287 0.0538 13.2072 0.006Zm-0.9024 9.8652v2.9941l-4.1523 2.1484a13.9787 13.9787 0 0 1 2.7676 -3.9277 14.1784 14.1784 0 0 1 1.3847 -1.2148z" fill="currentColor"></path>
                    </svg>
                <?php else: 
                    // Fallback to Boxicons
                    $iconMap = [
                        'tux'    => 'bxl-tux', 
                        'docker' => 'bxl-docker',
                        'git-repo-forked' => 'bx-git-repo-forked'
                    ];
                    $bxClass = $iconMap[$lab['icon']] ?? 'bxl-ubuntu';
                ?>
                    <i class="bx <?= $bxClass ?>" style="font-size: 140px; color: currentColor;"></i>
                <?php endif; ?>
            </div>

            <div class="card-body p-4 text-center d-flex flex-column align-items-center position-relative" style="z-index: 2;">
                <div class="mb-2">
                    <h5 class="card-title fw-bold mb-0">
                        <?= $lab['name'] ?>
                        <div class="terminal-info-wrapper ms-1">
                            <i class="bx bx-info-circle small text-muted"></i>
                            <div class="terminal-tooltip">
                                <div class="fw-bold mb-1 text-uppercase text-secondary" style="font-size: 10px;">Instance ID</div>
                                <div class="font-monospace text-warning text-break"><?= $lab['hash'] ?></div>
                            </div>
                        </div>
                    </h5>
                </div>

                <div class="mb-4">
                    <span class="text-<?= $lab['status'] == 'running' ? 'info' : 'secondary opacity-50' ?> font-monospace small fw-bold">
                        <?= $lab['ip'] ?>
                    </span>
                </div>

                <div class="d-flex justify-content-center flex-wrap gap-2 mb-4">
                    <?php foreach($lab['badges'] as $b): ?>
                        <span class="badge rounded-pill text-bg-primary px-2 py-1 small border-0"><?= $b ?></span>
                    <?php endforeach; ?>

                    <span class="badge rounded-pill text-bg-warning px-2 py-1 small text-dark">
                        <?= $lab['is_public'] ?>
                    </span>

                    <span class="badge rounded-pill text-bg-<?= $lab['status'] == 'running' ? 'success' : 'danger' ?> px-2 py-1 small">
                        <?= $lab['status'] ?>
                    </span>
                </div>

                <div class="mt-auto w-100 d-flex gap-2">
                    <?php if($lab['status'] === 'running'): ?>
                        <button type="button" class="btn btn-primary flex-grow-1 d-flex align-items-center justify-content-center fw-bold small rounded-3 py-2"
                                onclick="openCodeModal('<?= $lab['hash'] ?>', '<?= $lab['name'] ?> Lab', '<?= $lab['status'] ?>')">
                            <i class='bx bx-code-alt me-1'></i> Code
                        </button>
                    <?php endif; ?>
                    
                    <a href="/labs/dashboard/<?= $lab['hash'] ?>" 
                       class="btn btn-success text-white flex-grow-1 d-flex align-items-center justify-content-center fw-bold small rounded-3 py-2">
                        <i class='bx bx-grid-alt me-1'></i> Dashboard
                    </a>
                    
                    <!-- INFO MODAL TRIGGER -->
                    <button type="button" class="btn btn-info text-white d-flex align-items-center justify-content-center rounded-3 px-3"
                            onclick="openConnectionModal('<?= $lab['hash'] ?>', '<?= $lab['name'] ?> Lab', '<?= $lab['status'] ?>')">
                        <i class='bx bx-info-circle'></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Code Info Modal (Simplified IDE Launch) -->
<div class="modal fade" id="codeInfoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg glass-card rounded-4 overflow-hidden" style="background: rgba(15, 15, 20, 0.98) !important; backdrop-filter: blur(20px);">
            <div class="modal-header border-0 p-4 pb-0">
                <h5 class="modal-title fw-bold text-white mb-0">Code Server Access</h5>
                <button type="button" class="btn-close btn-close-white" data-coreui-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <span class="badge rounded-pill bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-3 py-2" id="codeModalLabName">Lab Name</span>
                </div>
                <div id="codeModalLoading" class="text-center py-5">
                    <div class="spinner-grow text-primary" role="status"></div>
                </div>
                <div id="codeModalOffline" class="text-center py-5" style="display: none;">
                    <i class='bx bx-power-off text-danger fs-1 mb-3'></i>
                    <h6 class="text-white fw-bold">Instance is Offline</h6>
                </div>
                <div id="codeModalContent" style="display: none;">
                    <div id="codeFields"></div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="button" class="btn btn-secondary bg-opacity-25 border-0 fw-bold px-4 rounded-pill" data-coreui-dismiss="modal">Dismiss</button>
                <div id="codeModalActionBtn"></div>
            </div>
        </div>
    </div>
</div>

<!-- Technical Connection Info Modal -->
<div class="modal fade" id="connectionInfoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg glass-card rounded-4 overflow-hidden" style="background: rgba(20, 20, 25, 0.95) !important; backdrop-filter: blur(20px);">
            <div class="modal-header border-0 p-4 pb-0">
                <h5 class="modal-title fw-bold text-white mb-0">Technical Connection Info</h5>
                <button type="button" class="btn-close btn-close-white" data-coreui-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <span class="badge rounded-pill bg-info bg-opacity-10 text-info border border-info border-opacity-25 px-3 py-2" id="modalLabName">Lab Name</span>
                </div>
                <div id="modalLoading" class="text-center py-5"><div class="spinner-border text-info" role="status"></div></div>
                <div id="modalOffline" class="text-center py-5" style="display: none;">
                    <i class='bx bx-server text-muted fs-1 mb-3'></i>
                    <h6 class="text-white fw-bold">Offline</h6>
                </div>
                <div id="modalContent" style="display: none;">
                    <div id="connectionFields"></div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="button" class="btn btn-secondary bg-opacity-25 border-0 fw-bold px-4 rounded-pill w-100" data-coreui-dismiss="modal">Close Details</button>
            </div>
        </div>
    </div>
</div>

<script src="workspace/js/connection_info.js"></script>
<script src="workspace/js/lab_code.js"></script>