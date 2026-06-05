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

    // 5. Fetch Technical Connection Info
    fetch(`/api/labs/connection_info?hash=${hash}`)
        .then(response => response.json())
        .then(data => {
            loadingEl.style.display = 'none';

            if (data.status === 'success') {
                contentEl.style.display = 'block';

                // Set Title
                if (data.data.title) {
                    document.querySelector('#connectionInfoModal .modal-title').textContent = data.data.title;
                }

                // Render Technical Fields
                renderConnectionFields(data.data.fields, fieldsEl);

            } else {
                alert('Failed to load connection details: ' + (data.error || 'Unknown error'));
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
    let html = '<div class="d-flex flex-column gap-3">';
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
            const escapedValue = field.value.replace(/'/g, "\\'");
            const copyBtn = field.copy ? `
                <button class="btn btn-outline-secondary ms-2 rounded-pill px-3" 
                        onclick="copyText('${escapedValue}', '${field.label} copied!')">
                    <i class='bx bx-copy'></i>
                </button>` : '';

            valueHtml = `
                <div class="input-group input-group-sm">
                    <input type="${inputType}" 
                        class="form-control rounded-pill border-secondary bg-body-tertiary text-body px-3 ${isMono}" 
                        value="${field.value}" 
                        readonly style="opacity: 0.85; background: rgba(255,255,255,0.05) !important; color: white !important;">
                    ${copyBtn}
                </div>`;
        }

        html += `
            <div class="row align-items-center">
                <div class="col-4 text-white-50 small fw-bold">
                    ${field.label}
                </div>
                <div class="col-8">
                    ${valueHtml}
                </div>
            </div>`;
    });
    html += '</div>';
    container.innerHTML = html;
}