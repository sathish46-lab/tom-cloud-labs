function openConnectionModal(hash, name, status) {
    // 1. Set static info
    document.getElementById('modalLabName').textContent = name;

    // 2. Reset View State
    const loadingEl = document.getElementById('modalLoading');
    const offlineEl = document.getElementById('modalOffline');
    const contentEl = document.getElementById('modalContent');
    const fieldsEl = document.getElementById('connectionFields');

    loadingEl.style.display = 'block';
    offlineEl.style.display = 'none';
    contentEl.style.display = 'none';
    fieldsEl.innerHTML = '';

    // 3. Show Modal
    const modal = new coreui.Modal(document.getElementById('connectionInfoModal'));
    modal.show();

    // 4. Check Status
    if (status !== 'running') {
        loadingEl.style.display = 'none';
        offlineEl.style.display = 'block';
        return;
    }

    // 5. Fetch Data securely
    fetch(`/api/labs/connection_info?hash=${hash}`)
        .then(response => response.json())
        .then(data => {
            loadingEl.style.display = 'none';

            if (data.status === 'success') {
                contentEl.style.display = 'block';
                renderConnectionFields(data.data.fields, fieldsEl);
            } else {
                alert('Failed to load credentials: ' + (data.error || 'Unknown error'));
                modal.hide();
            }
        })
        .catch(err => {
            console.error(err);
            loadingEl.style.display = 'none';
            alert('Network error occurred.');
            modal.hide();
        });
}

function renderConnectionFields(fields, container) {
    let html = '';
    fields.forEach(field => {
        const isLink = (field.type === 'link');
        const inputType = (field.type === 'password') ? 'password' : 'text';
        const isMono = field.mono ? 'font-monospace' : '';

        let valueHtml = '';

        if (isLink) {
            valueHtml = `
                <a href="${field.value}" target="_blank" class="text-decoration-none small fw-bold">
                    ${field.value} <i class='bx bx-link-external ms-1'></i>
                </a>`;
        } else {
            // Check for copy button
            const copyBtn = field.copy ? `
                <button class="btn btn-outline-secondary ms-2 rounded-pill px-3" 
                        onclick="navigator.clipboard.writeText('${field.value}')">
                    <i class='bx bx-copy'></i>
                </button>` : '';

            valueHtml = `
                <div class="input-group input-group-sm">
                    <input type="${inputType}" 
                        class="form-control rounded-pill border-secondary bg-body-tertiary text-body px-3 ${isMono}" 
                        value="${field.value}" 
                        readonly style="opacity: 0.85;">
                    ${copyBtn}
                </div>`;
        }

        html += `
            <div class="row align-items-center">
                <div class="col-4 text-body-emphasis small fw-bold">
                    ${field.icon ? `<i class='bx ${field.icon} me-1'></i>` : ''}
                    ${field.label}
                </div>
                <div class="col-8">
                    ${valueHtml}
                </div>
            </div>`;
    });

    container.innerHTML = html;
}