<div class="col-12 col-md-4 domain-card-wrapper card-entrance" id="domain-card-<?= $d['_id'] ?>">
    <div class="card shadow-lg rounded-4 overflow-hidden border-0 glass-card h-100">
        <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div class="d-flex align-items-center gap-2">
                    <h5 class="fw-bold m-0 text-truncate" style="text-transform: none; color: var(--glass-text); font-size: 1.15rem; letter-spacing: -0.3px;">
                        <?= htmlspecialchars(explode('.', $d['domain'])[0]) ?>
                    </h5>
                    <?php if(strtolower($d['type']) == 'tom'): ?>
                        <span class="badge bg-primary rounded-pill" style="font-size: 0.65rem; padding: 3px 8px;">Tom Lab</span>
                    <?php else: ?>
                        <span class="badge border border-secondary text-secondary rounded-pill" style="font-size: 0.65rem; padding: 3px 8px;">Custom</span>
                    <?php endif; ?>
                </div>
                
                <div class="dropdown">
                    <button class="action-dots p-0 opacity-50 shadow-none border-0 d-flex align-items-center justify-content-center" 
                            data-coreui-toggle="dropdown" 
                            style="width: 32px; height: 32px; transition: all 0.2s; background: none; border: none;">
                        <i class='bx bx-dots-vertical-rounded fs-4' style="color: var(--glass-icon);"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="min-width: 10rem; border-radius: 12px; padding: 8px; background: var(--cui-dropdown-bg); backdrop-filter: blur(10px);">
                        <li>
                            <a class="dropdown-item rounded-3 mb-1 px-2 py-1" href="javascript:void(0)" onclick="if(window.TomNotify) window.TomNotify.show('Manage features coming soon!', 'Info', 'info', 3000);" style="font-size: 0.75rem;">
                                <i class='bx bx-cog me-2 text-primary'></i> Manage
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item text-danger rounded-3 px-2 py-1" href="javascript:void(0)" style="font-size: 0.75rem;"
                                onclick="deleteDomain('<?= $d['_id'] ?>', '<?= htmlspecialchars($d['domain']) ?>')">
                                <i class='bx bx-trash me-2'></i> Delete
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="d-flex gap-1 flex-wrap mb-3">
                <?php if ($d['verified']): ?>
                    <span class="badge bg-success text-white rounded-pill fw-bold text-lowercase" style="font-size: 8px; padding: 2px 6px;">verified</span>
                <?php else: ?>
                    <span class="badge bg-warning text-dark rounded-pill fw-bold text-lowercase" style="font-size: 8px; padding: 2px 6px;">unverified</span>
                <?php endif; ?>
                
                <?php 
                    // Add usage status badge
                    if (!isset($dm)) {
                        require_once __DIR__ . '/../../lib/core/DomainManager.class.php';
                        $dm = new DomainManager();
                    }
                    $usageInfo = $dm->getDomainUsage(Session::getUser()->getUserId(), $d['domain']);
                    if ($usageInfo && isset($usageInfo['status']) && $usageInfo['status'] === 'running'): 
                ?>
                    <span class="badge bg-primary text-white rounded-pill fw-bold text-lowercase" style="font-size: 8px; padding: 2px 6px;">in use</span>
                <?php else: ?>
                    <span class="badge bg-warning text-dark rounded-pill fw-bold text-lowercase" style="font-size: 8px; padding: 2px 6px;">orphaned</span>
                    <span class="badge bg-danger text-white rounded-pill fw-bold text-lowercase" style="font-size: 8px; padding: 2px 6px;">not in use</span>
                <?php endif; ?>
                
                <?php
                    // Check if this domain has a valid SSL certificate
                    if (!isset($certs)) {
                        $certs = Session::get('ssl_certificates') ?: [];
                    }
                    $certIndex = -1;
                    $hasValidSsl = false;
                    foreach ($certs as $idx => $cert) {
                        if (in_array($d['domain'], $cert['sans'])) {
                            $certIndex = $idx;
                            $hasValidSsl = $cert['is_valid'];
                            break;
                        }
                    }
                    if ($certIndex >= 0):
                ?>
                    <a href="/ssl" class="badge <?= $hasValidSsl ? 'bg-success' : 'bg-danger' ?> text-white rounded-pill fw-bold text-decoration-none text-lowercase" style="font-size: 8px; padding: 2px 6px; transition: all 0.2s;">
                        ssl <?= $hasValidSsl ? 'valid' : 'invalid' ?>
                    </a>
                <?php endif; ?>
            </div>

            <div style="font-size: 0.8rem; line-height: 1.2; color: var(--glass-text);">
                <div class="mb-1">
                    <b style="color: var(--glass-text-muted); font-weight: 600;">Common Name:</b> 
                    <span class="ms-1"><?= htmlspecialchars(explode('.', $d['domain'])[0]) ?></span>
                </div>
                
                <div class="mb-1">
                    <b style="color: var(--glass-text-muted); font-weight: 600;">Domain Name:</b> 
                    <span class="ms-1" style="color: #3498db;"><?= htmlspecialchars($d['domain']) ?></span>
                </div>
                
                <div class="mb-1">
                    <b style="color: var(--glass-text-muted); font-weight: 600;">A Record:</b> 
                    <span class="ms-1"><?php echo $dm->getServerIP(); ?></span>
                </div>
                
                <div class="mb-1">
                    <b style="color: var(--glass-text-muted); font-weight: 600;">Service:</b> 
                    <span class="ms-1"><?= (strtolower($d['type']) == 'tom') ? 'Tom Lab' : 'Custom' ?></span>
                </div>
                
                <?php 
                    if ($usageInfo && isset($usageInfo['status']) && $usageInfo['status'] === 'running'): 
                ?>
                    <div class="mb-1">
                        <b style="color: var(--glass-text-muted); font-weight: 600;">Currently Used:</b> 
                        <span class="text-success ms-1"><?= htmlspecialchars($usageInfo['usage']) ?> (<?= htmlspecialchars($usageInfo['lab_type']) ?> lab)</span>
                    </div>
                <?php else: ?>
                    <div class="mb-1">
                        <b style="color: var(--glass-text-muted); font-weight: 600;">Currently Used:</b> 
                        <span class="text-secondary ms-1 opacity-75">None</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
