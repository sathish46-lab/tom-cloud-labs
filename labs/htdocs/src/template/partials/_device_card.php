<?php
    $dbId = is_array($device['_id']) ? ($device['_id']['$oid'] ?? '') : (string)$device['_id'];
    $displayStatus = ucfirst(strtolower($device['status'] ?? 'offline')); 
?>
<div class="col-12 col-md-4 device-row card-entrance" id="device-card-<?= $dbId ?>" data-pubkey="<?= $device['public_key'] ?>">
    <div class="card shadow-lg rounded-4 overflow-hidden border-0 glass-card h-100">
        <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-start">
                <h5 class="fw-bold m-0 text-truncate" style="text-transform: none; color: var(--glass-text); font-size: 1.15rem; letter-spacing: -0.3px;">
                    <?= htmlspecialchars($device['device_name']) ?></h5>
                <div class="dropdown">
                    <button class="action-dots p-0 opacity-50 shadow-none border-0 d-flex align-items-center justify-content-center" 
                            data-coreui-toggle="dropdown" 
                            style="width: 32px; height: 32px; transition: all 0.2s; background: none; border: none;">
                        <i class='bx bx-dots-vertical-rounded fs-4' style="color: var(--glass-icon);"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="min-width: 10rem; border-radius: 12px; padding: 8px; background: var(--cui-dropdown-bg); backdrop-filter: blur(10px);">
                        <li><a class="dropdown-item rounded-3 mb-1 px-2 py-1" href="javascript:void(0)" style="font-size: 0.75rem;"
                                onclick="openVPNConnectionModal('<?= $dbId ?>', '<?= htmlspecialchars($device['device_name']) ?>')"><i
                                    class='bx bx-qr-scan me-2 text-primary'></i> Config</a></li>
                        <li><a class="dropdown-item rounded-3 mb-1 px-2 py-1" href="javascript:void(0)" style="font-size: 0.75rem;"
                                onclick="downloadTunnel('<?= htmlspecialchars($device['device_name']) ?>', '<?= $dbId ?>')"><i
                                    class='bx bx-download me-2 text-info'></i> Download</a></li>
                        <li><a class="dropdown-item text-danger rounded-3 px-2 py-1" href="javascript:void(0)" style="font-size: 0.75rem;"
                                onclick="deleteDevice('<?= $dbId ?>', '<?= $device['public_key'] ?>', '<?= htmlspecialchars($device['device_name'], ENT_QUOTES) ?>', '<?= $device['assigned_ip'] ?? 'N/A' ?>')"><i
                                    class='bx bx-trash me-2'></i> Delete</a></li>
                    </ul>
                </div>
            </div>
            <div class="d-flex gap-1 flex-wrap mb-2">
                <?php 
                    $type = strtolower($device['device_type'] ?? 'mobile');
                    $typeClass = 'bg-primary';
                    $typeIcon = 'bx-mobile-alt';
                    
                    if ($type === 'laptop') { $typeClass = 'bg-info text-dark'; $typeIcon = 'bx-laptop'; }
                    elseif ($type === 'desktop') { $typeClass = 'bg-success'; $typeIcon = 'bx-desktop'; }
                    elseif ($type === 'tablet') { $typeClass = 'bg-warning text-dark'; $typeIcon = 'bx-tab'; }
                    elseif ($type === 'server') { $typeClass = 'bg-dark border border-secondary'; $typeIcon = 'bx-server'; }
                    elseif ($type === 'iot') { $typeClass = 'bg-danger'; $typeIcon = 'bx-chip'; }
                ?>
                <span class="badge rounded-pill <?= $typeClass ?> fw-bold" style="font-size: 8px; padding: 2px 6px; text-transform: capitalize;">
                    <i class='bx <?= $typeIcon ?> me-1'></i> <?= $type ?>
                </span>
                <?php 
                    $status = strtolower($device['status'] ?? 'offline');
                    $statusClass = ($status === 'online') ? 'bg-success' : 'bg-danger';
                    $statusIcon = ($status === 'online') ? 'bx-wifi' : 'bx-wifi-off';
                ?>
                <span class="badge rounded-pill status-pill <?= $statusClass ?> fw-bold" style="font-size: 8px; padding: 2px 6px; text-transform: capitalize;">
                    <i class='bx <?= $statusIcon ?> me-1'></i> <?= $status ?>
                </span>
            </div>
            <div class="small stats-area" style="font-size: 0.75rem;">
                <div class="d-flex justify-content-between mb-1">
                    <span style="color: var(--glass-text-muted);">Device IP:</span> 
                    <span class="theme-text font-monospace" style="color: var(--glass-text);"><?= $device['assigned_ip'] ?? 'N/A' ?></span>
                </div>
                <div class="d-flex justify-content-between mb-1">
                    <span style="color: var(--glass-text-muted);">Origin:</span> 
                    <span class="origin-val theme-text" style="color: var(--glass-text);"><?= $device['origin_ip'] ?? 'N/A' ?></span>
                </div>
                <div class="d-flex justify-content-between mb-1">
                    <span style="color: var(--glass-text-muted);">Received:</span> 
                    <span class="rx-val theme-text"><?= $device['rx'] ?? '0 B' ?></span>
                </div>
                <div class="d-flex justify-content-between">
                    <span style="color: var(--glass-text-muted);">Sent:</span> 
                    <span class="tx-val theme-text"><?= $device['tx'] ?? '0 B' ?></span>
                </div>
            </div>
        </div>
    </div>
</div>
