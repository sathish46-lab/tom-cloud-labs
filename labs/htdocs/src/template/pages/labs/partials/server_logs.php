<?php
$ui_prefs = Session::getUser()->getUiPreferences() ?? [];
$is_minimized = isset($ui_prefs['labs_serverlogs_min']) && $ui_prefs['labs_serverlogs_min'] === '1';
$minimized_class = $is_minimized ? 'logs-minimized' : '';
$chevron_class = $is_minimized ? 'bx-chevron-up' : 'bx-chevron-down';
?>
<div class="server-logs-panel shadow-lg">
    <div class="logs-header d-flex justify-content-between align-items-center" style="cursor: pointer;" id="serverLogsToggleBtn" data-minimized="<?= $is_minimized ? 'true' : 'false' ?>">
        <div class="logs-title d-flex align-items-center gap-2">
            <i class='bx bx-terminal fs-5'></i>
            <i class="bx bxs-circle" id="mq-status-dot" style="font-size: 8px;"></i>
            <span class="small fw-bold ls-1 opacity-75">Server Logs</span>
            
            <div class="terminal-info-wrapper ms-1">
                <i class='bx bx-info-circle opacity-50' style="font-size: 14px;"></i>
                <div class="terminal-tooltip">
                    You cannot type anything here, this is a terminal to watch server logs
                </div>
            </div>
            
            <i class='bx <?= $chevron_class ?> server-logs-chevron ms-1' style="font-size: 18px; opacity: 0.75;"></i>
        </div>
        <div class="logs-action text-secondary opacity-75 pe-2">
            <i class='bx <?= $chevron_class ?> server-logs-chevron' style="font-size: 18px;"></i>
        </div>
    </div>
    <div class="logs-body <?= $minimized_class ?>" id="terminal-viewport" style="overflow-y: auto; transition: height 0.3s ease;">
        <div id="live-logs-container" class="small"></div>
    </div>
</div>
