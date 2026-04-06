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
                        <a href="/labs/code/<?= $lab['hash'] ?>" 
                           class="btn btn-primary flex-grow-1 d-flex align-items-center justify-content-center fw-bold small rounded-3 py-2">
                            <i class='bx bx-code-alt me-1'></i> Code
                        </a>
                    <?php endif; ?>
                    
                    <a href="/labs/dashboard/<?= $lab['hash'] ?>" 
                       class="btn btn-success text-white flex-grow-1 d-flex align-items-center justify-content-center fw-bold small rounded-3 py-2">
                        <i class='bx bx-grid-alt me-1'></i> Dashboard
                    </a>
                    
                    <!-- INFO MODAL TRIGGER -->
                    <button type="button" class="btn btn-info text-white d-flex align-items-center justify-content-center rounded-3 px-3"
                            onclick="openConnectionModal('<?= $lab['hash'] ?>', '<?= $lab['name'] ?>', '<?= $lab['status'] ?>')">
                        <i class='bx bx-info-circle'></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>


<!-- GENERIC CONNECTION INFO MODAL -->
<div class="modal fade" id="connectionInfoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <!-- Modal Header with gradient background -->
            <div class="modal-header border-0 p-4 text-white">
                <div>
                    <h5 class="modal-title fw-bold mb-1">Connection Information</h5>
                    <div class="badge rounded-pill bg-black bg-opacity-25 border border-white border-opacity-10" id="modalLabName">
                        Lab Name
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-coreui-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <!-- Modal Body -->
            <div class="modal-body p-0">
                <div class="p-4" style="background-color: var(--cui-body-bg);">
                    
                    <!-- Loading State -->
                    <div id="modalLoading" class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-3 text-muted small">Fetching secure connection details...</p>
                    </div>

                    <!-- Validation Error/Offline State -->
                    <div id="modalOffline" class="text-center py-5" style="display: none;">
                        <i class='bx bx-server fs-1 text-muted opacity-25 mb-3'></i>
                        <p class="text-muted fw-bold">Lab is currently offline.</p>
                        <p class="small text-secondary">Start the lab to view connection details.</p>
                    </div>

                    <!-- Content State -->
                    <div id="modalContent" style="display: none;">
                        <p class="text-body-secondary small mb-4">
                            This lab is reachable with the following credentials. You can reach this IP from within your Essentials Lab. 
                            You can do VS Code Forward if you have connected Essentials Lab using VS Code Desktop.
                        </p>

                        <div class="d-flex flex-column gap-3" id="connectionFields">
                            <!-- Fields injected via JS -->
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer border-0 p-3">
                <button type="button" class="btn btn-warning fw-bold text-dark rounded-pill px-4" data-coreui-dismiss="modal">
                    Okay
                </button>
            </div>
        </div>
    </div>
</div>