<style>
.proc-table {
    font-size: 0.8rem;
    font-family: 'SF Mono', 'Fira Code', monospace;
}
.proc-table th {
    position: sticky;
    top: 0;
    background: rgba(var(--cui-body-bg-rgb, 255,255,255), 0.95);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    z-index: 2;
    font-weight: 700;
    text-transform: uppercase;
    font-size: 0.65rem;
    letter-spacing: 0.05em;
    color: var(--cui-secondary-color, #6c757d);
    border-bottom: 1px solid rgba(var(--cui-body-color-rgb, 0,0,0), 0.08) !important;
}
.proc-table td {
    border-color: rgba(var(--cui-body-color-rgb, 0,0,0), 0.04) !important;
    padding: 6px 10px;
    vertical-align: middle;
}
.proc-table tr:hover td {
    background: rgba(var(--cui-body-color-rgb, 0,0,0), 0.03);
}
.proc-table .high-cpu { color: #ff6b6b; font-weight: 700; }
.proc-table .high-mem { color: #ffa502; font-weight: 700; }
.proc-table .normal { color: var(--cui-secondary-color, #6c757d); }
.svc-toggle { transition: all 0.3s; }
.svc-card {
    transition: all 0.2s;
}
.svc-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 22px rgba(0, 0, 0, 0.28);
}
.svc-dot {
    width: 8px; height: 8px; border-radius: 50%;
    display: inline-block;
}
.svc-dot.active { background: #2ecc71; box-shadow: 0 0 6px rgba(46,204,113,0.5); }
.svc-dot.inactive { background: #636e72; }
.mem-bar {
    height: 6px; border-radius: 3px; background: rgba(var(--cui-body-color-rgb, 0,0,0), 0.08);
    overflow: hidden;
}
.mem-bar-fill {
    height: 100%; border-radius: 3px; transition: width 0.5s ease;
}
.container-select-btn {
    border: 1px solid rgba(var(--cui-body-color-rgb, 0,0,0), 0.1);
    background: rgba(var(--cui-body-color-rgb, 0,0,0), 0.03);
    color: var(--cui-secondary-color, #6c757d);
    border-radius: 20px;
    padding: 6px 14px;
    font-size: 0.75rem;
    font-weight: 600;
    transition: all 0.2s;
    cursor: pointer;
}
.container-select-btn:hover {
    background: rgba(var(--cui-body-color-rgb, 0,0,0), 0.06);
    color: var(--cui-body-color, #1a1a1a);
}
.container-select-btn.active {
    background: rgba(46,134,222,0.15);
    border-color: rgba(46,134,222,0.5);
    color: #2e86de;
}
.sm-card { transition: all 0.2s; }
.sm-card:hover { transform: translateY(-2px); box-shadow: 0 8px 22px rgba(0,0,0,0.28); }
</style>

<div class="blur banner rounded-0 border-bottom border-secondary border-opacity-10">
    <div class="card-body p-0" style="margin-left: 1rem; margin-right: 1rem;">
        <div class="container-fluid pt-3 pb-1">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="position-relative flex-shrink-0">
                        <div class="avatar lab-header-avatar">
                            <div class="avatar-img d-flex align-items-center justify-content-center bg-dark bg-opacity-25 rounded-circle p-2">
                                <i class='bx bx-crown'></i>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex flex-column gap-1">
                        <h3 class="fw-bold mb-0 ls-tight lab-header-title">Superuser Admin Panel</h3>
                        <div class="d-flex flex-wrap align-items-center gap-2 small">
                            <div class="d-flex align-items-center text-secondary">
                                <span class="me-1 opacity-75">Manage users and global feature flags</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        
            <!-- Navigation Tabs -->
            <?php include __DIR__ . '/admin_nav.php'; ?>
        </div>
    </div>
</div>

<div class="blur banner mb-3 rounded-0 border-bottom border-secondary border-opacity-10">
    <div class="card-body py-3 px-4">
        <!-- Container Selector -->
        <div class="d-flex gap-2 mb-3 flex-wrap">
            <button class="container-select-btn active" data-container="Dev_lab" onclick="selectContainer(this, 'Dev_lab')">
                <i class='bx bx-cube me-1'></i> Dev_lab
            </button>
            <button class="container-select-btn" data-container="TomCloudLab_mongodb" onclick="selectContainer(this, 'TomCloudLab_mongodb')">
                <i class='bx bx-data me-1'></i> TomCloudLab MongoDB
            </button>
            <button class="container-select-btn" data-container="TomCloudLab" onclick="selectContainer(this, 'TomCloudLab')">
                <i class='bx bx-server me-1'></i> TomCloudLab
            </button>
            <button class="container-select-btn" data-container="TomCloudLab_cloudflared" onclick="selectContainer(this, 'TomCloudLab_cloudflared')">
                <i class='bx bx-cloud me-1'></i> Cloudflared
            </button>
        </div>

        <!-- Inner Tab Navigation (Overview/Processes/Services) -->
        <div class="d-flex justify-content-between align-items-center">
            <ul class="nav lab-nav-tabs border-0 mb-0" id="adminTabs">
                <li class="nav-item">
                    <a class="nav-link active" data-tab="overview" onclick="switchTab('overview', this)">
                        <i class='bx bx-bar-chart-alt-2 me-1'></i> Overview
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-tab="processes" onclick="switchTab('processes', this)">
                        <i class='bx bx-list-ul me-1'></i> Processes
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-tab="services" onclick="switchTab('services', this)">
                        <i class='bx bx-cog me-1'></i> Services
                    </a>
                </li>
            </ul>
            <button class="btn btn-sm rounded-pill px-3 fw-semibold" id="refreshBtn" onclick="fetchProcesses(); fetchServices();" style="background: rgba(255,165,0,0.15); color: #ffa502; border: 1px solid rgba(255,165,0,0.3);">
                <i class='bx bx-refresh me-1'></i> Refresh
            </button>
        </div>
    </div>
</div>

<div class="container-fluid px-4">
    <!-- Tab Content -->
    <div id="tabContent">
        <!-- Overview Tab -->
        <div id="tab-overview" class="tab-pane active">
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card border-0 rounded-4 blur shadow-sm sm-card h-100">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <small class="text-body-secondary fw-semibold text-uppercase" style="font-size:0.65rem;">Memory</small>
                                <i class='bx bx-chip text-info'></i>
                            </div>
                            <h4 class="fw-bold mb-1" id="memUsed">--</h4>
                            <small class="text-body-secondary" id="memTotal">/ -- MB</small>
                            <div class="mem-bar mt-2">
                                <div class="mem-bar-fill" id="memBar" style="width: 0%; background: #2e86de;"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 rounded-4 blur shadow-sm sm-card h-100">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <small class="text-body-secondary fw-semibold text-uppercase" style="font-size:0.65rem;">Load Average</small>
                                <i class='bx bx-tachometer text-warning'></i>
                            </div>
                            <h4 class="fw-bold mb-1" id="load1">--</h4>
                            <small class="text-body-secondary" id="loadInfo">1 / 5 / 15 min</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 rounded-4 blur shadow-sm sm-card h-100">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <small class="text-body-secondary fw-semibold text-uppercase" style="font-size:0.65rem;">Processes</small>
                                <i class='bx bx-terminal text-success'></i>
                            </div>
                            <h4 class="fw-bold mb-1" id="procCount">--</h4>
                            <small class="text-body-secondary">running tasks</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 rounded-4 blur shadow-sm sm-card h-100">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <small class="text-body-secondary fw-semibold text-uppercase" style="font-size:0.65rem;">Uptime</small>
                                <i class='bx bx-time text-primary'></i>
                            </div>
                            <h4 class="fw-bold mb-1 text-truncate" id="uptimeVal">--</h4>
                            <small class="text-body-secondary">since boot</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 rounded-4 blur shadow-sm mb-4">
                <div class="card-header border-bottom border-body-secondary border-opacity-10 py-2 px-3" style="background: rgba(var(--cui-body-bg-rgb, 255,255,255), 0.5);">
                    <h6 class="mb-0 fw-bold"><i class='bx bx-bar-chart text-warning me-2'></i>Top Processes by Memory</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 320px; overflow-y: auto;">
                        <table class="table table-dark table-hover mb-0 proc-table">
                            <thead>
                                <tr>
                                    <th>PID</th>
                                    <th>User</th>
                                    <th>CPU%</th>
                                    <th>MEM%</th>
                                    <th>RSS (MB)</th>
                                    <th>Command</th>
                                </tr>
                            </thead>
                            <tbody id="overviewProcBody">
                                <tr><td colspan="6" class="text-center text-body-secondary py-4">Click Refresh to load</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Processes Tab -->
        <div id="tab-processes" class="tab-pane d-none">
            <div class="card border-0 rounded-4 blur shadow-sm">
                <div class="card-header border-bottom border-body-secondary border-opacity-10 py-2 px-3 d-flex justify-content-between align-items-center" style="background: rgba(var(--cui-body-bg-rgb, 255,255,255), 0.5);">
                    <h6 class="mb-0 fw-bold"><i class='bx bx-list-ul text-info me-2'></i>All Processes</h6>
                    <div class="d-flex gap-2 align-items-center">
                        <input type="text" class="form-control form-control-sm bg-body-secondary bg-opacity-25 border-0 text-body rounded-pill px-3" style="width:200px; font-size:0.75rem;" placeholder="Filter..." id="procFilter" oninput="filterProcesses()">
                        <select class="form-select form-select-sm bg-body-secondary bg-opacity-25 border-0 text-body rounded-pill px-3" style="width:140px; font-size:0.75rem;" id="procSort" onchange="renderAllProcesses()">
                            <option value="mem-desc">MEM % ↓</option>
                            <option value="cpu-desc">CPU % ↓</option>
                            <option value="rss-desc">RSS ↓</option>
                            <option value="pid-asc">PID ↑</option>
                        </select>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                        <table class="table table-dark table-hover mb-0 proc-table">
                            <thead>
                                <tr>
                                    <th>PID</th>
                                    <th>User</th>
                                    <th>CPU%</th>
                                    <th>MEM%</th>
                                    <th>RSS (MB)</th>
                                    <th>VSZ (MB)</th>
                                    <th>Stat</th>
                                    <th>Time</th>
                                    <th>Command</th>
                                </tr>
                            </thead>
                            <tbody id="allProcBody">
                                <tr><td colspan="9" class="text-center text-body-secondary py-4">Click Refresh to load</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Services Tab -->
        <div id="tab-services" class="tab-pane d-none">
            <div class="row g-3" id="servicesContainer">
                <div class="col-12 text-center py-4 text-body-secondary">
                    <div class="spinner-border spinner-border-sm me-2" role="status"></div> Loading services...
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let currentContainer = 'Dev_lab';
let allProcesses = [];
let servicesData = [];

function selectContainer(el, container) {
    document.querySelectorAll('.container-select-btn').forEach(function(b) { b.classList.remove('active'); });
    el.classList.add('active');
    currentContainer = container;
    allProcesses = [];
    servicesData = [];
    document.getElementById('overviewProcBody').innerHTML = '<tr><td colspan="6" class="text-center text-body-secondary py-4">Click Refresh to load</td></tr>';
    document.getElementById('allProcBody').innerHTML = '<tr><td colspan="9" class="text-center text-body-secondary py-4">Click Refresh to load</td></tr>';
    document.getElementById('memUsed').textContent = '--';
    document.getElementById('memTotal').textContent = '/ -- MB';
    document.getElementById('memBar').style.width = '0%';
    document.getElementById('load1').textContent = '--';
    document.getElementById('loadInfo').textContent = '1 / 5 / 15 min';
    document.getElementById('procCount').textContent = '--';
    document.getElementById('uptimeVal').textContent = '--';
    document.getElementById('servicesContainer').innerHTML = '<div class="col-12 text-center py-4 text-body-secondary">Click Refresh to load</div>';
}

function switchTab(tab, el) {
    document.querySelectorAll('#adminTabs .nav-link').forEach(function(l) { l.classList.remove('active'); });
    el.classList.add('active');
    document.querySelectorAll('#tabContent > .tab-pane').forEach(function(p) {
        p.classList.add('d-none');
        p.classList.remove('active');
    });
    var pane = document.getElementById('tab-' + tab);
    pane.classList.remove('d-none');
    pane.classList.add('active');
}

function formatMem(kb) {
    return (kb / 1024).toFixed(1);
}

async function fetchProcesses() {
    var btn = document.getElementById('refreshBtn');
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Loading...';
    btn.disabled = true;

    try {
        var res = await fetch('/api/admin/server_monitor?action=processes&container=' + encodeURIComponent(currentContainer));
        if (!res.ok) throw new Error('HTTP ' + res.status);
        var json = await res.json();
        if (json.status === 'success') {
            allProcesses = json.data.processes;
            document.getElementById('memUsed').textContent = json.data.memory.used + ' MB';
            document.getElementById('memTotal').textContent = '/ ' + json.data.memory.total + ' MB';
            var pct = json.data.memory.total > 0 ? Math.round((json.data.memory.used / json.data.memory.total) * 100) : 0;
            var bar = document.getElementById('memBar');
            bar.style.width = pct + '%';
            bar.style.background = pct > 85 ? '#ff6b6b' : pct > 60 ? '#ffa502' : '#2e86de';
            document.getElementById('load1').textContent = json.data.load['1min'];
            document.getElementById('loadInfo').textContent = json.data.load['1min'] + ' / ' + json.data.load['5min'] + ' / ' + json.data.load['15min'];
            document.getElementById('procCount').textContent = allProcesses.length;
            document.getElementById('uptimeVal').textContent = json.data.uptime || '--';
            renderOverviewProcesses();
            renderAllProcesses();
        } else {
            TomNotify.show(json.error || 'Failed to fetch', 'Error', 'error', 4000);
        }
    } catch(e) {
        console.error('Fetch processes error:', e);
        TomNotify.show('Failed to fetch processes: ' + e.message, 'Error', 'error', 4000);
    } finally {
        btn.innerHTML = '<i class="bx bx-refresh me-1"></i> Refresh';
        btn.disabled = false;
    }
}

function renderOverviewProcesses() {
    var top = allProcesses.slice(0, 10);
    var tbody = document.getElementById('overviewProcBody');
    if (top.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-body-secondary py-4">No processes</td></tr>';
        return;
    }
    tbody.innerHTML = top.map(function(p) {
        return '<tr>' +
            '<td class="text-body-secondary">' + p.pid + '</td>' +
            '<td class="text-body-secondary">' + escapeHtml(p.user) + '</td>' +
            '<td class="' + (p.cpu > 50 ? 'high-cpu' : 'normal') + '">' + p.cpu + '%</td>' +
            '<td class="' + (p.mem > 5 ? 'high-mem' : 'normal') + '">' + p.mem + '%</td>' +
            '<td class="normal">' + formatMem(p.rss) + '</td>' +
            '<td class="text-body-secondary text-truncate" style="max-width:250px;" title="' + escapeHtml(p.command) + '">' + escapeHtml(p.command) + '</td>' +
        '</tr>';
    }).join('');
}

function renderAllProcesses() {
    var procs = allProcesses.slice();
    var sort = document.getElementById('procSort').value;
    var filter = (document.getElementById('procFilter').value || '').toLowerCase();

    if (filter) {
        procs = procs.filter(function(p) {
            return p.command.toLowerCase().indexOf(filter) !== -1 ||
                   p.user.toLowerCase().indexOf(filter) !== -1 ||
                   String(p.pid).indexOf(filter) !== -1;
        });
    }

    if (sort === 'mem-desc') procs.sort(function(a,b) { return b.mem - a.mem; });
    else if (sort === 'cpu-desc') procs.sort(function(a,b) { return b.cpu - a.cpu; });
    else if (sort === 'rss-desc') procs.sort(function(a,b) { return b.rss - a.rss; });
    else if (sort === 'pid-asc') procs.sort(function(a,b) { return a.pid - b.pid; });

    var tbody = document.getElementById('allProcBody');
    if (procs.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center text-body-secondary py-4">No processes</td></tr>';
        return;
    }
    tbody.innerHTML = procs.map(function(p) {
        return '<tr>' +
            '<td class="text-body-secondary">' + p.pid + '</td>' +
            '<td class="text-body-secondary">' + escapeHtml(p.user) + '</td>' +
            '<td class="' + (p.cpu > 50 ? 'high-cpu' : 'normal') + '">' + p.cpu + '%</td>' +
            '<td class="' + (p.mem > 5 ? 'high-mem' : 'normal') + '">' + p.mem + '%</td>' +
            '<td class="normal">' + formatMem(p.rss) + '</td>' +
            '<td class="normal">' + formatMem(p.vsz) + '</td>' +
            '<td class="text-body-secondary font-monospace" style="font-size:0.7rem;">' + escapeHtml(p.stat) + '</td>' +
            '<td class="text-body-secondary">' + escapeHtml(p.time) + '</td>' +
            '<td class="text-body-secondary text-truncate" style="max-width:300px;" title="' + escapeHtml(p.command) + '">' + escapeHtml(p.command) + '</td>' +
        '</tr>';
    }).join('');
}

function filterProcesses() { renderAllProcesses(); }

async function fetchServices() {
    var container = (currentContainer === 'Dev_lab' || currentContainer === 'TomCloudLab_mongodb') ? currentContainer : 'Dev_lab';
    var el = document.getElementById('servicesContainer');
    el.innerHTML = '<div class="col-12 text-center py-4 text-body-secondary"><div class="spinner-border spinner-border-sm me-2"></div> Loading services...</div>';

    try {
        var res = await fetch('/api/admin/server_monitor?action=services&container=' + encodeURIComponent(container));
        if (!res.ok) throw new Error('HTTP ' + res.status);
        var json = await res.json();
        if (json.status === 'success') {
            servicesData = json.data;
            renderServices();
        } else {
            el.innerHTML = '<div class="col-12 text-center text-danger py-4">' + (json.error || 'Failed') + '</div>';
        }
    } catch(e) {
        console.error('Fetch services error:', e);
        el.innerHTML = '<div class="col-12 text-center text-danger py-4">Failed to load services: ' + e.message + '</div>';
    }
}

function renderServices() {
    var el = document.getElementById('servicesContainer');
    var serviceIcons = {
        'mysql': 'bx-data', 'mongodb': 'bx-data', 'rabbitmq-server': 'bx-message-rounded',
        'apache2': 'bx-globe', 'redis-server': 'bx-server', 'fail2ban': 'bx-shield-quarter', 'docker': 'bxl-docker'
    };
    var serviceLabels = {
        'mysql': 'MySQL', 'mongodb': 'MongoDB', 'rabbitmq-server': 'RabbitMQ',
        'apache2': 'Apache2', 'redis-server': 'Redis', 'fail2ban': 'Fail2Ban', 'docker': 'Docker'
    };

    el.innerHTML = servicesData.map(function(svc) {
        return '<div class="col-md-4 col-sm-6">' +
            '<div class="card border-0 rounded-4 blur shadow-sm svc-card h-100">' +
                '<div class="card-body p-3">' +
                    '<div class="d-flex align-items-center justify-content-between mb-3">' +
                        '<div class="d-flex align-items-center gap-2">' +
                            '<div class="rounded-circle d-flex align-items-center justify-content-center" style="width:38px;height:38px;background:rgba(255,255,255,0.05);">' +
                                '<i class="bx ' + (serviceIcons[svc.name] || 'bx-cog') + ' text-info"></i>' +
                            '</div>' +
                            '<div>' +
                                '<h6 class="mb-0 fw-bold">' + (serviceLabels[svc.name] || svc.name) + '</h6>' +
                                '<small class="text-body-secondary" style="font-size:0.65rem;">' + svc.name + '</small>' +
                            '</div>' +
                        '</div>' +
                        '<div class="d-flex align-items-center gap-2">' +
                            '<span class="svc-dot ' + (svc.active ? 'active' : 'inactive') + '"></span>' +
                            '<span class="fw-semibold" style="font-size:0.75rem; color: ' + (svc.active ? '#2ecc71' : '#636e72') + ';">' +
                                (svc.active ? 'Running' : 'Stopped') +
                            '</span>' +
                        '</div>' +
                    '</div>' +
                    '<div class="d-flex justify-content-end">' +
                        '<div class="form-check form-switch fs-5 mb-0">' +
                            '<input class="form-check-input pointer svc-toggle" type="checkbox" role="switch"' +
                                   (svc.active ? ' checked' : '') +
                                   ' onchange="toggleService(\'' + svc.name + '\', this.checked)"' +
                                   (svc.name === 'docker' ? ' disabled title="Cannot stop Docker inside container"' : '') + '>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</div>' +
        '</div>';
    }).join('');
}

async function toggleService(service, enable) {
    var container = (currentContainer === 'Dev_lab' || currentContainer === 'TomCloudLab_mongodb') ? currentContainer : 'Dev_lab';
    try {
        var formData = new FormData();
        formData.append('action', 'toggle_service');
        formData.append('container', container);
        formData.append('service', service);
        formData.append('enable', enable ? '1' : '0');

        var res = await fetch('/api/admin/server_monitor', { method: 'POST', body: formData });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        var json = await res.json();
        if (json.status === 'success') {
            var svc = servicesData.find(function(s) { return s.name === service; });
            if (svc) svc.active = json.data.active;
            renderServices();
            TomNotify.show(service + ' is now ' + (json.data.active ? 'RUNNING' : 'STOPPED'), 'Service Updated', json.data.active ? 'success' : 'warning', 3000);
            setTimeout(function() { fetchServices(); }, 3000);
        } else {
            TomNotify.show(json.error || 'Failed', 'Error', 'error', 4000);
            fetchServices();
        }
    } catch(e) {
        console.error('Toggle service error:', e);
        TomNotify.show('Failed to toggle service: ' + e.message, 'Error', 'error', 4000);
        fetchServices();
    }
}

function escapeHtml(unsafe) {
    return (unsafe || '').toString()
         .replace(/&/g, "&amp;")
         .replace(/</g, "&lt;")
         .replace(/>/g, "&gt;")
         .replace(/"/g, "&quot;")
         .replace(/'/g, "&#039;");
}

document.addEventListener('DOMContentLoaded', function() {
    // No auto-fetch — user clicks Refresh to load data
});
</script>
