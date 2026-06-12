<?php
$certs = Session::get('ssl_certificates') ?: [];
$autoManaged = Session::get('ssl_auto_managed') ?: 0;
$dm = new DomainManager();
$serverIP = $dm->getServerIP();
?>

<!-- SSL Manager Header -->
<div class="lab-header-section mb-4 px-4">
    <div class="row align-items-start">
        <div class="col-lg-7">
            <h1 class="fw-bold theme-text m-0" style="font-size: 1.8rem; letter-spacing: -0.5px;">SSL Manager</h1>
            <p class="text-secondary opacity-75 mt-2 mb-0" style="font-size: 0.82rem; line-height: 1.8; letter-spacing: 0.2px;">
                SSL Manager shows every SSL certificate linked to your domains and labs. Certificates exist only for domains exposed to the web by a 
                lab, and all domains exposed on one port share a single certificate &mdash; so the number of certificates will not match the number of 
                domains you own. Track expiry, see why a certificate fails to renew, and request a re-issue once you have cleaned up expired domains. 
                We acquire and renew certificates for you automatically &mdash; this is where you watch it happen 🔐
            </p>
        </div>
        <div class="col-lg-5 text-end mt-3 mt-lg-0">
            <div class="d-flex align-items-center justify-content-end gap-2 mb-2">
                <button class="btn btn-warning fw-bold px-4 rounded-pill shadow-sm" style="font-size: 0.8rem; height: 38px; white-space: nowrap;" 
                        id="btnTroubleshootAll" onclick="SSLManager.troubleshootAll()">
                    <i class="bx bx-search-alt me-1"></i> Troubleshoot
                </button>
                <button class="btn btn-primary fw-bold px-4 rounded-pill shadow-sm" style="font-size: 0.8rem; height: 38px; white-space: nowrap;" 
                        id="btnRefresh" onclick="SSLManager.refresh()">
                    <i class="bx bx-refresh me-1"></i> Refresh
                </button>
            </div>
            <div class="small text-secondary opacity-60" style="font-size: 0.75rem;">
                Certificate data is cached for up to 15 minutes.<br>
                Use Refresh to fetch the latest state.
            </div>
            <?php if ($autoManaged > 0): ?>
            <div class="mt-2">
                <a href="#" class="small text-info text-decoration-none fw-semibold" style="font-size: 0.78rem;" 
                   onclick="SSLManager.toggleAutoManaged(); return false;">
                    Show <?php echo htmlspecialchars((string)$autoManaged); ?> auto-managed certificate(s)
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Certificate Cards Grid -->
<div class="row g-4 mb-4" id="ssl-cards-container">
    <?php if (empty($certs)): ?>
    <div class="col-12">
        <div class="card border-0 shadow-lg rounded-4 glass-card">
            <div class="card-body text-center py-5">
                <i class="bx bx-shield-x" style="font-size: 3rem; color: var(--glass-text-muted);"></i>
                <h5 class="mt-3 fw-bold">No SSL Certificates Found</h5>
                <p class="text-secondary small mb-3">
                    Your labs don't have any active SSL certificates yet. Deploy a lab with web exposure enabled to automatically get an SSL certificate.
                </p>
                <a href="/labs" class="btn btn-primary btn-sm rounded-pill px-4">Go to Labs</a>
            </div>
        </div>
    </div>
    <?php else: ?>
    <?php foreach ($certs as $index => $cert): ?>
    <div class="col-xl-4 col-md-6">
        <div class="card border-0 shadow-lg rounded-4 h-100 ssl-cert-card glass-card" data-cert-index="<?php echo (int)$index; ?>">
            <div class="card-body d-flex justify-content-between align-items-start p-3">
                <div class="w-100" style="margin-bottom: 6px;">
                    <!-- Main Domain -->
                    <div class="fw-bold mb-2" style="font-size: 1.15rem; letter-spacing: -0.3px; color: var(--glass-text);">
                        <?php echo htmlspecialchars($cert['main_domain']); ?>
                    </div>

                    <!-- Badges -->
                    <div style="margin-bottom: 14px;" class="d-flex gap-1 flex-wrap">
                        <?php 
                        $badges = $cert['badges'] ?? [];
                        if (empty($badges)) {
                            // Generate default badges
                            $badges = [];
                            if ($cert['is_valid']) $badges[] = 'valid';
                        }
                        foreach ($badges as $badge): 
                            $badgeClass = 'bg-secondary';
                            $textClass = 'text-white';
                            $badgeLabel = htmlspecialchars($badge);
                            
                            if (stripos($badge, 'Tom') !== false) {
                                $badgeClass = 'bg-purple';
                                $textClass = 'text-white';
                            } elseif (stripos($badge, 'valid') !== false) {
                                $badgeClass = 'bg-success';
                                $textClass = 'text-white';
                            } elseif (stripos($badge, 'in use') !== false) {
                                $badgeClass = 'bg-info';
                                $textClass = 'text-dark';
                            } elseif (stripos($badge, 'expired') !== false) {
                                $badgeClass = 'bg-danger';
                                $textClass = 'text-white';
                            }
                        ?>
                            <span class="badge <?php echo $badgeClass; ?> <?php echo $textClass; ?> rounded-pill fw-bold" 
                                  style="font-size: 8px; padding: 2px 6px; text-transform: capitalize;"><?php echo $badgeLabel; ?></span>
                        <?php endforeach; ?>
                    </div>

                    <!-- Certificate Details -->
                    <div style="font-size: 0.8rem; line-height: 1.6; color: var(--glass-text);">
                        <div class="mb-1">
                            <b style="color: var(--glass-text-muted); font-weight: 600;">Resolver:</b>
                            <span class="ms-1 text-success"><?php echo htmlspecialchars($cert['resolver']); ?></span>
                        </div>
                        <div class="mb-1">
                            <b style="color: var(--glass-text-muted); font-weight: 600;">SANs:</b>
                            <span class="ms-1">
                                <?php 
                                $sans = $cert['sans'] ?? [];
                                $displayCount = 3;
                                $displayed = array_slice($sans, 0, $displayCount);
                                echo htmlspecialchars(implode(', ', $displayed));
                                if (count($sans) > $displayCount) {
                                    echo ' <span class="text-info">+' . (count($sans) - $displayCount) . ' more</span>';
                                }
                                ?>
                            </span>
                        </div>
                        <div class="mb-1">
                            <b style="color: var(--glass-text-muted); font-weight: 600;">Expires:</b>
                            <span class="ms-1">
                                <?php 
                                $expiresDate = $cert['expires'] ? date('d M Y', $cert['expires_timestamp']) : 'Unknown';
                                $daysLeft = $cert['days_left'] ?? null;
                                echo htmlspecialchars($expiresDate);
                                if ($daysLeft !== null) {
                                    $daysColor = $daysLeft > 30 ? 'text-success' : ($daysLeft > 7 ? 'text-warning' : 'text-danger');
                                    echo ' <span class="' . $daysColor . '">(in ' . (int)$daysLeft . ' days)</span>';
                                }
                                ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Three-dot dropdown -->
                <div class="dropdown">
                    <button class="action-dots p-0 opacity-50 shadow-none border-0 d-flex align-items-center justify-content-center" 
                            data-coreui-toggle="dropdown" 
                            style="width: 32px; height: 32px; transition: all 0.2s; background: none; border: none;">
                        <i class='bx bx-dots-vertical-rounded fs-4' style="color: var(--glass-icon);"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="min-width: 10rem; border-radius: 12px; padding: 8px; background: var(--cui-dropdown-bg); backdrop-filter: blur(10px);">
                        <li>
                            <a class="dropdown-item rounded-3 mb-1 px-3 py-2 d-flex align-items-center" href="#" 
                               onclick="SSLManager.viewDetails(<?php echo (int)$index; ?>); return false;" style="font-size: 0.8rem;">
                                <i class='bx bx-info-circle me-2 text-info'></i> View Details
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item rounded-3 mb-1 px-3 py-2 d-flex align-items-center" href="#" 
                               onclick="SSLManager.troubleshoot('<?php echo htmlspecialchars($cert['main_domain']); ?>'); return false;" style="font-size: 0.8rem;">
                                <i class='bx bx-wrench me-2 text-warning'></i> Troubleshoot
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- View Details Modal -->
<div class="modal fade" id="sslDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pt-4 px-4">
                <h4 class="modal-title fw-bold" id="sslDetailTitle">Certificate Details</h4>
                <button type="button" class="btn-close btn-close-white" data-coreui-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 pb-2" id="sslDetailBody">
                <!-- Populated dynamically -->
            </div>
            <div class="modal-footer border-0 pb-4 px-4">
                <button type="button" class="btn btn-secondary px-4 rounded-pill" data-coreui-dismiss="modal">Okay</button>
            </div>
        </div>
    </div>
</div>

<!-- Troubleshoot Modal -->
<div class="modal fade" id="sslTroubleshootModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pt-4 px-4">
                <h4 class="modal-title fw-bold">
                    <i class="bx bx-search-alt text-warning me-2"></i>
                    <span id="troubleshootTitle">SSL Troubleshoot</span>
                </h4>
                <button type="button" class="btn-close btn-close-white" data-coreui-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 pb-2" id="troubleshootBody">
                <div class="text-center py-5" id="troubleshootLoader">
                    <div class="spinner-border text-info mb-3" role="status"></div>
                    <p class="text-secondary small">Verifying DNS records for all domains...</p>
                </div>
                <div id="troubleshootResults" style="display: none;"></div>
            </div>
            <div class="modal-footer border-0 pb-4 px-4">
                <button type="button" class="btn btn-secondary px-4 rounded-pill" data-coreui-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<style>
.bg-purple { background-color: #7c3aed !important; }

.ssl-cert-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.ssl-cert-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 40px rgba(0,0,0,0.25) !important;
}

/* SSL Detail Table styling */
.ssl-detail-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.85rem;
}
.ssl-detail-table th {
    color: var(--glass-text-muted);
    font-weight: 700;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 10px 12px;
    border-bottom: 1px solid rgba(255,255,255,0.08);
}
.ssl-detail-table td {
    padding: 10px 12px;
    border-bottom: 1px solid rgba(255,255,255,0.04);
    vertical-align: middle;
}
.ssl-detail-table tr:last-child td {
    border-bottom: none;
}
.ssl-detail-table .domain-link {
    color: #22d3ee;
    text-decoration: none;
    font-weight: 600;
}
.ssl-detail-table .ip-mono {
    font-family: 'Courier New', monospace;
    color: var(--glass-text);
    font-size: 0.82rem;
}

/* Troubleshoot status badges */
.ts-badge-ok { background: rgba(34, 197, 94, 0.15); color: #22c55e; border: 1px solid rgba(34, 197, 94, 0.3); }
.ts-badge-warning { background: rgba(234, 179, 8, 0.15); color: #eab308; border: 1px solid rgba(234, 179, 8, 0.3); }
.ts-badge-error { background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); }

/* Renewal badges */
.renewal-auto { background: rgba(34, 197, 94, 0.12); color: #22c55e; }
.renewal-renewable { background: rgba(34, 197, 94, 0.12); color: #22c55e; }
.renewal-failed { background: rgba(239, 68, 68, 0.12); color: #ef4444; }
.renewal-risk { background: rgba(234, 179, 8, 0.12); color: #eab308; }

.ssl-meta-section {
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.06);
    border-radius: 12px;
    padding: 14px 16px;
    margin-top: 16px;
}
.ssl-meta-section .meta-item {
    font-size: 0.82rem;
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.ssl-meta-section .meta-item i {
    font-size: 1rem;
    color: var(--glass-text-muted);
}
</style>

<script>
// Store cert data for JS access (already escaped via PHP json_encode)
const SSL_CERTS = <?php echo json_encode($certs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const SSL_SERVER_IP = <?php echo json_encode($serverIP, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

const SSLManager = {
    /**
     * View certificate details in a modal.
     */
    viewDetails(index) {
        const cert = SSL_CERTS[index];
        if (!cert) return;

        const titleEl = document.getElementById('sslDetailTitle');
        titleEl.textContent = 'Certificate: ' + cert.main_domain;

        const body = document.getElementById('sslDetailBody');
        // Clear previous content safely
        body.replaceChildren();

        // "Domains in this certificate" label
        const label = document.createElement('p');
        label.className = 'small text-secondary mb-3 fw-bold';
        label.textContent = 'Domains in this certificate:';
        body.appendChild(label);

        // Build the table
        const table = document.createElement('table');
        table.className = 'ssl-detail-table';

        const thead = document.createElement('thead');
        const headerRow = document.createElement('tr');
        ['Domain', 'Points to', 'Renewal'].forEach(text => {
            const th = document.createElement('th');
            th.textContent = text;
            headerRow.appendChild(th);
        });
        thead.appendChild(headerRow);
        table.appendChild(thead);

        const tbody = document.createElement('tbody');
        (cert.sans || []).forEach(san => {
            const row = document.createElement('tr');

            // Domain cell
            const domainTd = document.createElement('td');
            const domainLink = document.createElement('span');
            domainLink.className = 'domain-link';
            domainLink.textContent = san;
            domainTd.appendChild(domainLink);
            row.appendChild(domainTd);

            // Points to cell
            const ipTd = document.createElement('td');
            const ipSpan = document.createElement('span');
            ipSpan.className = 'ip-mono';
            ipSpan.textContent = SSL_SERVER_IP;
            ipTd.appendChild(ipSpan);
            row.appendChild(ipTd);

            // Renewal cell
            const renewalTd = document.createElement('td');
            const renewalBadge = document.createElement('span');
            renewalBadge.className = 'badge rounded-pill px-2 py-1 fw-bold renewal-renewable';
            renewalBadge.style.fontSize = '0.72rem';
            renewalBadge.textContent = 'renewable';
            renewalTd.appendChild(renewalBadge);
            row.appendChild(renewalTd);

            tbody.appendChild(row);
        });
        table.appendChild(tbody);
        body.appendChild(table);

        // Metadata section
        const meta = document.createElement('div');
        meta.className = 'ssl-meta-section';

        const items = [
            { icon: 'bx-check-shield', label: 'Resolver', value: cert.resolver, color: 'text-success' },
            { icon: 'bx-calendar', label: 'Issued', value: cert.issued || 'Unknown' },
            { icon: 'bx-time-five', label: 'Expires', value: cert.expires || 'Unknown' },
            { icon: 'bx-server', label: 'Used by', value: cert.used_by || 'Not assigned to a lab' }
        ];

        items.forEach(item => {
            const div = document.createElement('div');
            div.className = 'meta-item';

            const icon = document.createElement('i');
            icon.className = 'bx ' + item.icon;
            div.appendChild(icon);

            const strong = document.createElement('strong');
            strong.className = 'text-secondary me-1';
            strong.textContent = item.label + ':';
            div.appendChild(strong);

            const span = document.createElement('span');
            span.className = item.color || '';
            span.textContent = item.value;
            div.appendChild(span);

            meta.appendChild(div);
        });

        body.appendChild(meta);

        // Show modal
        const modal = new coreui.Modal(document.getElementById('sslDetailModal'));
        modal.show();
    },

    /**
     * Troubleshoot a specific certificate.
     */
    troubleshoot(mainDomain) {
        const titleEl = document.getElementById('troubleshootTitle');
        titleEl.textContent = 'Troubleshoot: ' + mainDomain;

        const loader = document.getElementById('troubleshootLoader');
        const results = document.getElementById('troubleshootResults');
        loader.style.display = 'block';
        results.style.display = 'none';
        results.replaceChildren();

        const modal = new coreui.Modal(document.getElementById('sslTroubleshootModal'));
        modal.show();

        fetch('/api/ssl/troubleshoot?domain=' + encodeURIComponent(mainDomain))
            .then(r => r.json())
            .then(data => {
                loader.style.display = 'none';
                results.style.display = 'block';

                if (!data.success) {
                    const errP = document.createElement('p');
                    errP.className = 'text-danger';
                    errP.textContent = data.error || 'Troubleshoot failed.';
                    results.appendChild(errP);
                    return;
                }

                const res = data.result;

                // Summary banner
                const summary = document.createElement('div');
                summary.className = 'p-3 rounded-3 mb-3';
                summary.style.cssText = res.issues_found === 0
                    ? 'background: rgba(34,197,94,0.08); border: 1px solid rgba(34,197,94,0.2);'
                    : 'background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.2);';

                const summaryIcon = document.createElement('i');
                summaryIcon.className = res.issues_found === 0 ? 'bx bx-check-circle text-success me-2 fs-5' : 'bx bx-error-circle text-danger me-2 fs-5';
                summary.appendChild(summaryIcon);

                const summaryText = document.createElement('span');
                summaryText.className = 'fw-bold small';
                summaryText.textContent = res.issues_found === 0
                    ? 'All ' + res.total_sans + ' domains passed verification!'
                    : res.issues_found + ' of ' + res.total_sans + ' domains have issues.';
                summary.appendChild(summaryText);
                results.appendChild(summary);

                // Results table
                const table = document.createElement('table');
                table.className = 'ssl-detail-table';

                const thead = document.createElement('thead');
                const headerRow = document.createElement('tr');
                ['Domain', 'Points to', 'Status', 'Issues'].forEach(h => {
                    const th = document.createElement('th');
                    th.textContent = h;
                    headerRow.appendChild(th);
                });
                thead.appendChild(headerRow);
                table.appendChild(thead);

                const tbody = document.createElement('tbody');
                (res.domains || []).forEach(d => {
                    const row = document.createElement('tr');

                    const domainTd = document.createElement('td');
                    const domainSpan = document.createElement('span');
                    domainSpan.className = 'domain-link';
                    domainSpan.textContent = d.domain;
                    domainTd.appendChild(domainSpan);
                    row.appendChild(domainTd);

                    const ipTd = document.createElement('td');
                    const ipSpan = document.createElement('span');
                    ipSpan.className = 'ip-mono';
                    ipSpan.textContent = d.points_to || 'N/A';
                    ipTd.appendChild(ipSpan);
                    row.appendChild(ipTd);

                    const statusTd = document.createElement('td');
                    const statusBadge = document.createElement('span');
                    const statusMap = {
                        'ok': { cls: 'ts-badge-ok', label: '✓ OK' },
                        'warning': { cls: 'ts-badge-warning', label: '⚠ Warning' },
                        'error': { cls: 'ts-badge-error', label: '✕ Error' }
                    };
                    const s = statusMap[d.status] || statusMap['error'];
                    statusBadge.className = 'badge rounded-pill px-2 py-1 fw-bold ' + s.cls;
                    statusBadge.style.fontSize = '0.72rem';
                    statusBadge.textContent = s.label;
                    statusTd.appendChild(statusBadge);
                    row.appendChild(statusTd);

                    const issuesTd = document.createElement('td');
                    if (d.issues && d.issues.length > 0) {
                        d.issues.forEach(issue => {
                            const issueSpan = document.createElement('div');
                            issueSpan.className = 'small text-danger';
                            issueSpan.textContent = issue;
                            issuesTd.appendChild(issueSpan);
                        });
                    } else {
                        const noneSpan = document.createElement('span');
                        noneSpan.className = 'small text-success';
                        noneSpan.textContent = 'No issues';
                        issuesTd.appendChild(noneSpan);
                    }
                    row.appendChild(issuesTd);

                    tbody.appendChild(row);
                });
                table.appendChild(tbody);
                results.appendChild(table);

                // Certificate metadata
                const metaDiv = document.createElement('div');
                metaDiv.className = 'ssl-meta-section';

                const metaItems = [
                    { icon: 'bx-check-shield', label: 'Resolver', value: res.resolver },
                    { icon: 'bx-calendar', label: 'Issued', value: res.issued || 'Unknown' },
                    { icon: 'bx-time-five', label: 'Expires', value: res.expires || 'Unknown' },
                    { icon: 'bx-server', label: 'Used by', value: res.used_by || 'Not assigned' }
                ];
                metaItems.forEach(item => {
                    const div = document.createElement('div');
                    div.className = 'meta-item';

                    const icon = document.createElement('i');
                    icon.className = 'bx ' + item.icon;
                    div.appendChild(icon);

                    const strong = document.createElement('strong');
                    strong.className = 'text-secondary me-1';
                    strong.textContent = item.label + ':';
                    div.appendChild(strong);

                    const span = document.createElement('span');
                    span.textContent = item.value;
                    div.appendChild(span);

                    metaDiv.appendChild(div);
                });
                results.appendChild(metaDiv);
            })
            .catch(err => {
                loader.style.display = 'none';
                results.style.display = 'block';
                const errP = document.createElement('p');
                errP.className = 'text-danger small';
                errP.textContent = 'Network error: ' + err.message;
                results.appendChild(errP);
            });
    },

    /**
     * Troubleshoot ALL certificates sequentially.
     */
    troubleshootAll() {
        if (SSL_CERTS.length === 0) return;
        // Troubleshoot the first cert to start (user can then pick others)
        this.troubleshoot(SSL_CERTS[0].main_domain);
    },

    /**
     * Refresh certificates via API.
     */
    refresh() {
        const btn = document.getElementById('btnRefresh');
        const originalContent = btn.textContent;
        btn.disabled = true;
        btn.replaceChildren();
        const spinner = document.createElement('span');
        spinner.className = 'spinner-border spinner-border-sm me-1';
        spinner.setAttribute('role', 'status');
        btn.appendChild(spinner);
        const txt = document.createTextNode(' Refreshing...');
        btn.appendChild(txt);

        fetch('/api/ssl/refresh')
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    // Reload the page to show fresh data
                    window.location.reload();
                } else {
                    btn.disabled = false;
                    btn.replaceChildren();
                    const icon = document.createElement('i');
                    icon.className = 'bx bx-refresh me-1';
                    btn.appendChild(icon);
                    btn.appendChild(document.createTextNode(' Refresh'));
                }
            })
            .catch(() => {
                btn.disabled = false;
                btn.replaceChildren();
                const icon = document.createElement('i');
                icon.className = 'bx bx-refresh me-1';
                btn.appendChild(icon);
                btn.appendChild(document.createTextNode(' Refresh'));
            });
    },

    toggleAutoManaged() {
        // Currently shows all certs; this could filter to only auto-managed ones
        // For now, just scroll to the cards
        const container = document.getElementById('ssl-cards-container');
        if (container) {
            container.scrollIntoView({ behavior: 'smooth' });
        }
    }
};
</script>
