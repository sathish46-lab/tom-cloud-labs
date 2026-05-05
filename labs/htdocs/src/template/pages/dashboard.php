<?php
/**
 * Dashboard Template - Pre-rendered for Performance
 */
$user = Session::getUser();
$userId = (int)$user->getUserId();
$db = DatabaseConnection::getClient()->selectDatabase('tom_labs_db');

// 1. Fetch Labs
$activeLabsCount = $db->deployed_labs->countDocuments(['user_id' => $userId, 'status' => 'running']);
$labsLimit = 5;
$deployedLabs = $db->deployed_labs->find(['user_id' => $userId, 'status' => 'running'], ['sort' => ['created_at' => -1]]);

$labsList = [];
foreach ($deployedLabs as $lab) {
    $labsList[] = [
        'name' => ucfirst($lab['lab_type'] ?? 'Lab'),
        'ip' => $lab['internal_ip'] ?? 'Unknown',
        'status' => $lab['status'] ?? 'unknown',
        'hash' => $lab['instance_hash'] ?? '',
        'type' => $lab['lab_type'] ?? 'unknown'
    ];
}

// 2. Fetch Domains
$domainCount = $db->domains->countDocuments(['user_id' => ['$in' => [(string)$userId, $userId]]]);
$domainsLimit = 10;
$domains = $db->domains->find(['user_id' => ['$in' => [(string)$userId, $userId]]], ['sort' => ['created_at' => -1]]);
?>

<div class="container-fluid px-0 pt-4">
    <div class="row g-4 mb-4">
    <div class="col-12 col-md-6">
        <div class="card h-100 border-0 glass-card overflow-hidden">
            <div class="card-body p-4 position-relative">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h4 class="fw-bold mb-0 text-body">Connected Devices</h4>
                    </div>
                    <div class="text-end">
                        <div class="d-flex align-items-baseline justify-content-end gap-1">
                            <h2 class="fw-bold mb-0 text-body ls-n1"><?= str_pad($activeLabsCount, 2, '0', STR_PAD_LEFT) ?></h2>
                            <span class="text-body-secondary fw-semibold">/ <?= $labsLimit ?></span>
                        </div>
                        <div class="small theme-text fw-bold text-uppercase" style="font-size: 0.65rem;">Active Nodes</div>
                    </div>
                </div>

                <!-- Device List -->
                <div class="device-list mt-4 pe-1" style="max-height: 110px; overflow-y: auto;">
                    <?php if (!empty($labsList)): ?>
                        <?php foreach ($labsList as $lab): ?>
                        <div class="d-flex align-items-center justify-content-between p-2 mb-2 rounded-3 border border-white border-opacity-10" style="background: rgba(255,255,255,0.05);">
                            <div class="d-flex align-items-center gap-2">
                                <div class="bg-success bg-opacity-20 p-2 rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                    <i class='bx bxs-circle text-success animate-pulse' style="font-size: 0.5rem;"></i>
                                </div>
                                <div>
                                    <span class="text-body fw-semibold small d-block" style="line-height: 1.2;"><?= $lab['name'] ?> Lab</span>
                                    <span class="text-success fw-bold text-uppercase" style="font-size: 0.6rem; letter-spacing: 0.5px;">Online</span>
                                </div>
                            </div>
                            <div class="text-end">
                                <div class="text-body font-monospace fw-medium" style="font-size: 0.75rem;"><?= $lab['ip'] ?></div>
                                <div class="text-body-secondary small fw-bold text-uppercase" style="font-size: 0.55rem; opacity: 0.7;">Internal IP</div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center text-body-secondary py-4 small">
                            <i class="bx bx-server opacity-25 d-block fs-2 mb-2"></i>
                            No active labs found
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-md-6">
        <div class="card h-100 border-0 glass-card overflow-hidden">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h4 class="fw-bold mb-0 text-body">Linked Domains</h4>
                    </div>
                    <div class="text-end">
                        <div class="d-flex align-items-baseline justify-content-end gap-1">
                            <h2 class="fw-bold mb-0 text-info ls-n1"><?= str_pad($domainCount, 2, '0', STR_PAD_LEFT) ?></h2>
                            <span class="text-body-secondary fw-semibold">/ <?= $domainsLimit ?></span>
                        </div>
                        <div class="small text-info fw-bold text-uppercase" style="font-size: 0.65rem;">Provisioned</div>
                    </div>
                </div>

                <div class="domain-list mt-4 pe-1" style="max-height: 110px; overflow-y: auto;">
                    <?php if ($domainCount > 0): ?>
                        <?php foreach ($domains as $d): ?>
                        <div class="d-flex align-items-center justify-content-between p-2 mb-2 rounded-3 border border-white border-opacity-10" style="background: rgba(255,255,255,0.05);">
                            <div class="d-flex align-items-center gap-2">
                                <div class="bg-info bg-opacity-20 p-2 rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                    <i class='bx bx-world text-info' style="font-size: 0.9rem;"></i>
                                </div>
                                <div class="text-body fw-medium small"><?= $d['domain'] ?></div>
                            </div>
                            <div class="text-end">
                                <div class="text-body opacity-75 font-monospace" style="font-size: 0.65rem;"><?= $d['ip_address'] ?? 'Pending' ?></div>
                                <div class="text-body-secondary small fw-bold text-uppercase" style="font-size: 0.5rem; opacity: 0.6;">A Record</div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center text-body-secondary py-3 small">No active domains</div>
                    <?php endif; ?>
                </div>
                <div class="mt-2 text-center">
                    <a href="/domains" class="text-decoration-none text-body-secondary small fw-bold hover-theme-text transition-all uppercase ls-1">
                        VIEW ALL ASSETS <i class='bx bx-right-arrow-alt align-middle'></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-12">
        <div class="card border-0 glass-card">
            <div class="card-header bg-transparent border-0 pt-3 px-3 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0 d-flex align-items-center text-body" style="font-size: 0.85rem;">
                    Machine Labs 
                    <span class="badge bg-danger rounded-circle p-1 ms-2 animate-pulse" style="width:8px; height:8px;"></span> 
                    <span class="text-danger small fw-bold ms-1" style="font-size: 0.7rem; letter-spacing: 0.5px;">LIVE</span>
                </h6>
                <div class="small text-body-secondary fw-medium" style="font-size: 0.7rem;">
                    Limit: <?= $activeLabsCount ?>/<?= $labsLimit ?> <i class="bx bx-info-circle ms-1 align-middle opacity-50"></i>
                </div>
            </div>
            <div class="card-body p-3 pt-0">
                <div id="machine-labs-container" class="d-flex flex-column gap-2">
                    <?php if (!empty($labsList)): ?>
                        <?php foreach ($labsList as $lab): ?>
                        <div class="lab-row-premium d-flex flex-column flex-lg-row align-items-center p-3 rounded-4 gap-3 border border-white border-opacity-10" style="background: rgba(255,255,255,0.03);">
                            <div class="d-flex align-items-center gap-3 flex-grow-1 w-100">
                                    <div class="d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                                        <?php 
                                            $typeIconMap = ['essentials' => 'bxl-tux', 'minio' => 'bxl-docker', 'n8n' => 'bx-git-repo-forked'];
                                            $iconClass = $typeIconMap[$lab['type']] ?? 'bxl-ubuntu';
                                        ?>
                                        <i class="bx <?= $iconClass ?> theme-text opacity-75" style="font-size: 2.2rem;"></i>
                                    </div>
                                <div>
                                    <h6 class="fw-bold mb-1 text-body"><?= $lab['name'] ?> Lab</h6>
                                    <div class="d-flex gap-1">
                                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-10" style="font-size: 0.6rem;">beta</span>
                                        <?php 
                                            $statusColor = 'bg-danger';
                                            if ($lab['status'] === 'running') $statusColor = 'bg-success';
                                            else if ($lab['status'] === 'deploying') $statusColor = 'bg-warning';
                                            else if ($lab['status'] === 'stopping') $statusColor = 'bg-orange';
                                        ?>
                                        <span class="badge <?= $statusColor ?> bg-opacity-10 <?= str_replace('bg-', 'text-', $statusColor) ?> border border-opacity-10" style="font-size: 0.6rem;"><?= strtoupper($lab['status']) ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex flex-wrap justify-content-between justify-content-lg-end align-items-center gap-4 w-100">
                                <div class="text-center">
                                    <div class="fw-bold text-body small" id="cpu-<?= $lab['hash'] ?>">0.00%</div>
                                    <div class="text-body-secondary" style="font-size: 0.65rem; font-weight: 500;">CPU LOAD</div>
                                </div>
                                <div class="text-center px-lg-4 border-lg-start border-white border-opacity-10">
                                    <div class="fw-bold text-body small" id="mem-<?= $lab['hash'] ?>">0.00%</div>
                                    <div class="text-body-secondary" style="font-size: 0.65rem; font-weight: 500;">MEMORY USAGE</div>
                                </div>
                                <div class="text-center pe-lg-4 border-lg-end border-white border-opacity-10">
                                    <div class="fw-bold text-body small" id="load-<?= $lab['hash'] ?>">0.0000, 0.0000, 0.0000</div>
                                    <div class="text-body-secondary" style="font-size: 0.65rem; font-weight: 500;">LOAD AVERAGE</div>
                                </div>
                                
                                <div class="d-flex gap-2">
                                    <a href="/labs/dashboard/<?= $lab['hash'] ?>" class="btn btn-sm btn-success rounded-3 px-3 fw-bold d-flex align-items-center" style="height: 34px; font-size: 0.75rem;">
                                        <i class='bx bx-grid-alt me-1'></i> Dashboard
                                    </a>
                                    <button onclick="openCodeModal('<?= $lab['hash'] ?>', '<?= $lab['name'] ?> Lab', '<?= $lab['status'] ?>')" class="btn btn-sm btn-primary bg-opacity-75 border-0 rounded-3 px-3 fw-bold d-flex align-items-center" style="height: 34px; font-size: 0.75rem;">
                                        <i class='bx bx-code-alt me-1'></i> Code
                                    </button>
                                    <button onclick="openConnectionModal('<?= $lab['hash'] ?>', '<?= $lab['name'] ?> Lab', '<?= $lab['status'] ?>')" class="btn btn-sm btn-info bg-opacity-75 border-0 rounded-circle d-flex align-items-center justify-content-center" style="width: 34px; height: 34px;">
                                        <i class='bx bx-info-circle text-white' style="font-size: 1rem;"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-5 text-body-secondary small">No machine labs deployed yet.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<!-- Code Info Modal (Simplified IDE Launch) -->
<div class="modal fade" id="codeInfoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden bg-body-tertiary" style="backdrop-filter: blur(20px);">
            <div class="modal-header border-0 p-4 pb-0">
                <h5 class="modal-title fw-bold text-body mb-0">Code Server Access</h5>
                <button type="button" class="btn-close" data-coreui-dismiss="modal" aria-label="Close"></button>
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
                    <h6 class="text-body fw-bold">Instance is Offline</h6>
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
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden bg-body-tertiary" style="backdrop-filter: blur(20px);">
            <div class="modal-header border-0 p-4 pb-0">
                <h5 class="modal-title fw-bold text-body mb-0">Technical Connection Info</h5>
                <button type="button" class="btn-close" data-coreui-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <span class="badge rounded-pill bg-info bg-opacity-10 text-info border border-info border-opacity-25 px-3 py-2" id="modalLabName">Lab Name</span>
                </div>
                <div id="modalLoading" class="text-center py-5"><div class="spinner-border text-info" role="status"></div></div>
                <div id="modalOffline" class="text-center py-5" style="display: none;">
                    <i class='bx bx-server text-muted fs-1 mb-3'></i>
                    <h6 class="text-body fw-bold">Offline</h6>
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
<script>
document.addEventListener('DOMContentLoaded', function() {
    function startMetricsPolling(hash) {
        function fetchMetrics() {
            fetch(`/api/instance/stats?hash=${hash}`)
                .then(res => res.json())
                .then(stats => {
                    if (stats.CPUPerc) document.getElementById(`cpu-${hash}`).textContent = stats.CPUPerc;
                    if (stats.MemUsage) {
                        const usage = stats.MemUsage.split(' / ')[0];
                        document.getElementById(`mem-${hash}`).textContent = usage;
                    }
                    if (stats.Load1 !== undefined) {
                        const loadAvg = `${stats.Load1.toFixed(4)}, ${stats.Load5.toFixed(4)}, ${stats.Load15.toFixed(4)}`;
                        document.getElementById(`load-${hash}`).textContent = loadAvg;
                    }
                })
                .catch(() => {});
        }
        fetchMetrics();
        setInterval(fetchMetrics, 5000); 
    }

    // Initialize polling for all pre-rendered labs
    <?php foreach ($labsList as $lab): ?>
    startMetricsPolling('<?= $lab['hash'] ?>');
    <?php endforeach; ?>
});
</script>