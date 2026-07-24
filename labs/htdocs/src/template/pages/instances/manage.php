<?php
// Fetch instance data for this page
$hash = $_GET['slug'] ?? '';
$user = Session::getUser();
$userId = (int)$user->getUserId();
$db = DatabaseConnection::getClient()->selectDatabase('tom_labs_instances_db');
$instance = $db->instances->findOne(['instance_hash' => $hash]);
// Fallback: try by slug for old URLs
if (!$instance) {
    $instance = $db->instances->findOne(['slug' => $hash]);
}

$instName = $instance['name'] ?? ucfirst($hash);
$instType = $instance['type'] ?? 'machine';
$instStatus = $instance['status'] ?? 'draft';
$instImage = $instance['image'] ?? 'ubuntu:24.04';
$instIcon = $instance['icon'] ?? 'bx-cube-alt';
$instColor = $instance['color'] ?? '#ff416c';
$instSlug = $instance['slug'] ?? $hash;
$instHash = $instance['instance_hash'] ?? $hash;
$instVisibility = $instance['visibility'] ?? 'private';
$instDescription = $instance['description'] ?? '';
$instVersion = $instance['version'] ?? 'v0.0.1';
?>

<style>
/* Custom styling for Instance Manager */
.instance-header-btn {
    border: 1px solid rgba(255,255,255,0.1);
    background-color: rgba(255,255,255,0.05);
    color: white;
    border-radius: 20px;
    padding: 6px 16px;
    font-size: 0.85rem;
    font-weight: 600;
    transition: all 0.2s;
}
.instance-header-btn:hover {
    background-color: rgba(255,255,255,0.1);
    transform: translateY(-2px);
    box-shadow: 0 8px 22px rgba(0, 0, 0, 0.28);
}
.instance-header-btn.btn-primary {
    background-color: #ff4b2b;
    border-color: #ff4b2b;
}
.instance-header-btn.btn-primary:hover {
    background-color: #ff416c;
    transform: translateY(-2px);
    box-shadow: 0 8px 22px rgba(0, 0, 0, 0.28);
}
.copy-hash-btn {
    transition: all 0.2s;
    cursor: pointer;
}
.copy-hash-btn:hover {
    color: white !important;
    transform: translateY(-2px);
}
.nav-tabs .nav-link {
    color: rgba(255,255,255,0.6);
    border: none;
    border-bottom: 2px solid transparent;
    padding: 10px 16px;
    font-weight: 600;
    font-size: 0.9rem;
}
.nav-tabs .nav-link:hover {
    color: white;
    border-color: transparent;
}
.nav-tabs .nav-link.active {
    background: transparent;
    color: white;
    border-bottom: 2px solid white;
}
.nav-tabs {
    border-bottom: 1px solid rgba(255,255,255,0.1);
}
.config-label {
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: rgba(255,255,255,0.5);
    margin-bottom: 10px;
    margin-top: 25px;
}
.config-input {
    background-color: #1a1a1a;
    border: 1px solid rgba(255,255,255,0.1);
    color: white;
    border-radius: 12px;
    padding: 10px 16px;
}
.config-input:focus {
    background-color: #222;
    border-color: var(--cui-primary);
    box-shadow: none;
}
/* File Tree Styling */
.file-tree-container {
    background-color: #111;
    border-radius: 12px;
    border: 1px solid rgba(255,255,255,0.05);
    height: 600px;
    display: flex;
}
.file-tree-sidebar {
    width: 250px;
    border-right: 1px solid rgba(255,255,255,0.05);
    background-color: #161616;
    padding: 10px;
    overflow-y: auto;
}
.file-tree-item {
    padding: 4px 8px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.85rem;
    color: rgba(255,255,255,0.7);
    display: flex;
    align-items: center;
    gap: 8px;
}
.file-tree-item:hover {
    background-color: rgba(255,255,255,0.05);
    color: white;
}
.file-tree-item.folder {
    font-weight: 600;
}
.file-tree-editor {
    flex: 1;
    background-color: #0d0d0d;
    padding: 20px;
    position: relative;
}
.editor-toolbar {
    position: absolute;
    top: 10px;
    right: 10px;
    z-index: 10;
}
.code-mockup {
    font-family: 'Courier New', Courier, monospace;
    font-size: 0.85rem;
    color: #a9b7c6;
    line-height: 1.5;
}
.code-keyword { color: #cc7832; }
.code-string { color: #6a8759; }
.code-comment { color: #808080; }
</style>

<div class="blur mb-3 rounded-0">
    <div class="container-fluid px-4 pt-3">
        
        <!-- Top Header Navigation -->
        <!-- <a href="/instances" class="text-decoration-none theme-text d-flex align-items-center gap-2 mb-3 hover-text-primary transition-all small fw-bold">
            <i class='bx bx-left-arrow-alt'></i> Back to Instances
        </a> -->
        
        <!-- Header Block -->
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div class="d-flex align-items-center gap-3">
                <div class="text-white rounded-3 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; background-color: <?= htmlspecialchars($instColor) ?> !important;">
                    <i class='bx <?= htmlspecialchars($instIcon) ?> fs-3'></i>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <h3 class="fw-bold theme-text m-0"><?= htmlspecialchars($instName) ?></h3>
                        <span class="badge instance-badge-tag badge-type-<?= htmlspecialchars($instType) ?>"><?= htmlspecialchars($instType) ?></span>
                        <span class="badge instance-badge-tag badge-status-<?= htmlspecialchars($instStatus) ?>"><?= htmlspecialchars($instStatus) ?></span>
                        <?php if ($instVisibility === 'public'): ?>
                        <span class="badge instance-badge-tag badge-vis-public">public</span>
                        <?php else: ?>
                        <span class="badge instance-badge-tag badge-vis-private">private</span>
                        <?php endif; ?>
                        <span class="badge instance-badge-tag border border-secondary text-secondary"><?= htmlspecialchars($instVersion) ?></span>
                    </div>
                    <div class="d-flex align-items-center gap-2 text-secondary small">
                        Template <span class="text-info font-monospace"><?= htmlspecialchars($instance['template'] ?? 'essentials') ?></span> - <?= htmlspecialchars($instImage) ?>
                        <button type="button" class="btn btn-link text-secondary p-0 border-0 ms-1 copy-hash-btn" data-hash="<?= htmlspecialchars($instHash) ?>" title="Copy Instance ID"><i class='bx bx-copy'></i></button>
                    </div>
                    <?php if (!empty($instDescription)): ?>
                    <div class="text-secondary small mt-1">
                        <?= htmlspecialchars($instDescription) ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="d-flex gap-1">
                <button id="hdrDeployBtn" class="btn btn-sm theme-text rounded-pill px-3 py-1 fw-bold d-flex align-items-center gap-1 small" style="background-color: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); font-size: 0.78rem;"
                    data-coreui-toggle="loading-button" data-coreui-spinner-type="grow">
                    <i class='bx bx-rocket text-danger' style="font-size: 0.9rem;"></i> Deploy
                </button>
                <button id="hdrEditorBtn" class="btn btn-sm theme-text rounded-pill px-3 py-1 fw-bold d-flex align-items-center gap-1 small" style="background-color: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); font-size: 0.78rem;">
                    <i class='bx bx-code-alt' style="font-size: 0.9rem;"></i> Open in editor
                </button>
                <button id="hdrMoreBtn" class="btn btn-sm theme-text rounded-pill px-2 py-1 d-flex align-items-center justify-content-center" style="background-color: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);">
                    <i class='bx bx-dots-vertical-rounded' style="font-size: 0.9rem;"></i>
                </button>
            </div>
        </div>

        <!-- Tab Navigation - inside blur header (same as challenges/labs) -->
        <div class="row m-0 p-0 mt-3">
            <ul class="nav nav-tabs lab-nav-tabs border-0" id="instanceTabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link d-flex align-items-center gap-2 manage-tab-btn" data-tab="deployments" type="button" role="tab">
                        <i class='bx bx-rocket'></i> Deployments
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link d-flex align-items-center gap-2 manage-tab-btn" data-tab="files" type="button" role="tab">
                        <i class='bx bx-folder'></i> Files
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link d-flex align-items-center gap-2 manage-tab-btn active" data-tab="configuration" type="button" role="tab">
                        <i class='bx bx-cog'></i> Configuration
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link d-flex align-items-center gap-2 manage-tab-btn" data-tab="build" type="button" role="tab">
                        <i class='bx bx-hammer'></i> Build & validate
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link d-flex align-items-center gap-2 manage-tab-btn" data-tab="sharing" type="button" role="tab">
                        <i class='bx bx-share-alt'></i> Sharing
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link d-flex align-items-center gap-2 manage-tab-btn" data-tab="versions" type="button" role="tab">
                        <i class='bx bx-history'></i> Versions
                    </button>
                </li>
            </ul>
        </div>
    </div>
</div>

<div class="container-fluid px-3 py-2">

    <!-- Progress Pipeline Bar -->
    <div class="card blur border-0 rounded-3 shadow-sm px-4 py-3 mb-2">
        <div class="d-flex align-items-center gap-3 text-secondary small fw-bold">
            <span class="text-success d-flex align-items-center gap-1">Configure <i class='bx bx-check'></i></span>
            <i class='bx bx-chevron-right fs-5 opacity-50'></i>
            <span class="text-warning d-flex align-items-center gap-1"><span class="badge bg-warning text-dark rounded-circle px-2 py-1">2</span> Build & validate</span>
            <i class='bx bx-chevron-right fs-5 opacity-50'></i>
            <span>Deploy</span>
            <i class='bx bx-chevron-right fs-5 opacity-50'></i>
            <span>Share</span>
        </div>
    </div>

    <!-- Dynamic Tab Content Container -->
    <div id="instanceTabsContent">
        <!-- Content injected here via AJAX -->
        <div class="text-center py-5">
            <div class="spinner-border text-secondary" role="status"></div>
        </div>
    </div>
</div>

<!-- Server Logs Panel (footer, same as lab dashboard) -->
<div class="server-logs-panel shadow-lg px-4">
    <div class="logs-header d-flex justify-content-between align-items-center logs-header-clickable" id="instanceLogsToggleBtn">
        <div class="logs-title d-flex align-items-center gap-2">
            <i class='bx bx-terminal fs-5'></i>
            <i class="bx bxs-circle" id="mq-status-dot"></i>
            <span class="small fw-bold ls-1 opacity-75">Server Logs</span>
            <div class="terminal-info-wrapper ms-1">
                <i class='bx bx-info-circle opacity-50'></i>
                <div class="terminal-tooltip">Live build/deploy logs from the worker</div>
            </div>
            <i class='bx bx-chevron-down server-logs-chevron ms-1'></i>
        </div>
        <div class="logs-action text-secondary opacity-75 pe-2">
            <i class='bx bx-chevron-down server-logs-chevron'></i>
        </div>
    </div>
    <div class="logs-body logs-minimized" id="terminal-viewport">
        <div id="live-logs-container" class="small"></div>
    </div>
</div>

<script>
(function() {
    const instanceHash = <?= json_encode($instHash) ?>;
    if (!instanceHash) return;

    async function apiPost(endpoint, extra) {
        const body = new URLSearchParams({ hash: instanceHash, ...extra });
        const res = await fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        });
        return res.json();
    }

    const deployBtn = document.getElementById('hdrDeployBtn');
    if (deployBtn) {
        deployBtn.addEventListener('click', async () => {
            if (typeof Dashboard !== 'undefined') Dashboard.toggleLoading(deployBtn, true);
            else { deployBtn.disabled = true; deployBtn.innerHTML = '<span class="spinner-grow spinner-grow-sm me-1" role="status"></span> Processing'; }
            try {
                const data = await apiPost('/api/instances/deploy_instance');
                if (data.status === 'success') {
                    if (window.__loadInstanceTab) window.__loadInstanceTab('deployments');
                } else {
                    alert(data.error || 'Deploy failed');
                    if (typeof Dashboard !== 'undefined') Dashboard.toggleLoading(deployBtn, false);
                    else { deployBtn.innerHTML = '<i class="bx bx-rocket"></i> Deploy'; deployBtn.disabled = false; }
                }
            } catch (e) {
                alert('Network error');
                if (typeof Dashboard !== 'undefined') Dashboard.toggleLoading(deployBtn, false);
                else { deployBtn.innerHTML = '<i class="bx bx-rocket"></i> Deploy'; deployBtn.disabled = false; }
            }
        });
    }

    const editorBtn = document.getElementById('hdrEditorBtn');
    if (editorBtn) {
        editorBtn.addEventListener('click', () => {
            if (window.__loadInstanceTab) window.__loadInstanceTab('files');
        });
    }
})();
</script>

<script>
(function() {
    const instanceHash = <?= json_encode($instHash) ?>;
    if (!instanceHash) return;

    // --- Log append function (used by Build/Deploy tabs too) ---
    const container = document.getElementById('live-logs-container');
    const viewport = document.getElementById('terminal-viewport');
    if (!container || !viewport) return;

    let lineCount = 0;
    window.appendInstanceLog = function(msg) {
        const div = document.createElement('div');
        div.className = 'log-entry py-1';
        div.style.whiteSpace = 'pre-wrap';
        div.style.wordBreak = 'break-all';

        if (typeof msg === 'object' && msg !== null && msg.log) msg = msg.log;
        if (typeof msg !== 'string') msg = JSON.stringify(msg);

        if (msg.startsWith('[✓]') || msg.includes('success') || msg.includes('Complete')) {
            div.style.color = '#a6e3a1';
        } else if (msg.startsWith('[!]') || msg.toLowerCase().includes('error') || msg.toLowerCase().includes('failed')) {
            div.style.color = '#f38ba8';
        } else if (msg.startsWith('[*]') || msg.includes('reload')) {
            div.style.color = '#ffa502';
        }

        div.innerText = msg;
        container.appendChild(div);
        viewport.scrollTop = viewport.scrollHeight;

        lineCount++;
        if (lineCount % 200 === 0) {
            while (container.children.length > 1000) container.removeChild(container.firstChild);
        }

        if (typeof msg === 'string' && msg.includes('[*] reload')) {
            setTimeout(() => {
                if (window.__loadInstanceTab) window.__loadInstanceTab('deployments');
            }, 2500);
        }
    };

    // --- LogSocket connection ---
    const dot = document.getElementById('mq-status-dot');
    let logSocket = null;

    function connectLogs() {
        if (logSocket && logSocket.isConnected) return;
        logSocket = new TomSocketClient();
        logSocket.connect(
            'logs.' + instanceHash,
            (data) => window.appendInstanceLog(data),
            { dot: dot },
            () => {
                if (dot) { dot.style.color = '#a6e3a1'; }
                // window.appendInstanceLog('[✓] Log stream connected.');
            }
        );
        window.__instanceLogSocket = logSocket;
    }

    // --- Toggle minimize/expand ---
    const logsBody = document.getElementById('terminal-viewport');
    const toggleBtn = document.getElementById('instanceLogsToggleBtn');
    const chevrons = document.querySelectorAll('.server-logs-chevron');

    if (toggleBtn && logsBody) {
        toggleBtn.addEventListener('click', function(e) {
            if (e.target.closest('.terminal-info-wrapper')) return;
            const willMinimize = logsBody.classList.contains('logs-minimized');
            if (willMinimize) {
                logsBody.classList.remove('logs-minimized');
                chevrons.forEach(c => { c.classList.remove('bx-chevron-up'); c.classList.add('bx-chevron-down'); });
            } else {
                logsBody.classList.add('logs-minimized');
                chevrons.forEach(c => { c.classList.remove('bx-chevron-down'); c.classList.add('bx-chevron-up'); });
            }
        });
    }

    // --- Auto-connect if instance is active ---
    // Defer until app.js (TomSocketClient) is loaded — scripts load AFTER page body
    const status = <?= json_encode($instStatus) ?>;
    window.addEventListener('load', () => connectLogs());

    // Expose for tab scripts
    window.__connectInstanceLogs = connectLogs;
})();
</script>

