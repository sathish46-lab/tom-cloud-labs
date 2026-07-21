/**
 * Wrapped with IIFE Error Boundary
 */
try {
  (function() {
    "use strict";


function openCodeModal(hash, name, status) {
    // 1. Set static info
    const modalEl = document.getElementById('codeInfoModal');
    if (!modalEl) return;

    document.getElementById('codeModalLabName').textContent = name;

    // 2. Reset View State
    const loadingEl = document.getElementById('codeModalLoading');
    const offlineEl = document.getElementById('codeModalOffline');
    const contentEl = document.getElementById('codeModalContent');
    const fieldsEl = document.getElementById('codeFields');
    const actionBtn = document.getElementById('codeModalActionBtn');

    loadingEl.style.display = 'block';
    offlineEl.style.display = 'none';
    contentEl.style.display = 'none';
    fieldsEl.innerHTML = '';
    actionBtn.innerHTML = '';

    // 3. Show Modal
    const modal = new coreui.Modal(modalEl);
    modal.show();

    // 4. Check Status
    if (status !== 'running') {
        loadingEl.style.display = 'none';
        offlineEl.style.display = 'block';
        return;
    }

    // 5. Fetch Code Info
    fetch(`/api/labs/code_info?hash=${hash}`)
        .then(res => res.json())
        .then(data => {
            loadingEl.style.display = 'none';
            if (data.status === 'success') {
                contentEl.style.display = 'block';

                // Title
                if (data.data.title) {
                    modalEl.querySelector('.modal-title').textContent = data.data.title;
                }

                let html = '';

                // Description (Bullets)
                if (data.data.description) {
                    html += `<div class="mb-4">
                        <h6 class="fw-bold text-white small mb-2">What can you do here?</h6>
                        <ul class="text-white-50 small ps-3 mb-0">
                            ${data.data.description.map(d => `<li>${d}</li>`).join('')}
                        </ul>
                    </div>`;
                }

                // Instruction
                html += `<div class="mb-4 text-white small fw-medium">${data.data.instruction}</div>`;

                // Primary Credential
                if (data.data.primary) {
                    html += `
                        <div class="row align-items-center mb-4">
                            <div class="col-5 text-white small fw-bold">${data.data.primary.label}</div>
                            <div class="col-7">
                                <div class="input-group input-group-sm">
                                    <input type="password" class="form-control rounded-pill-start border-secondary bg-dark text-white px-3 font-monospace" value="${data.data.primary.value}" readonly>
                                    <button class="btn btn-outline-secondary rounded-pill-end px-3 border-start-0" data-copy="${data.data.primary.value}">
                                        <i class='bx bx-copy'></i>
                                    </button>
                                </div>
                            </div>
                        </div>`;
                }

                // Tip
                html += `<div class="mt-4"><p class="text-white-50 small"><span class="fw-bold text-white">Tip:</span> If there is an error while logging in, redeploy and try again.</p></div>`;

                fieldsEl.innerHTML = html;

                // Action Button
                if (data.data.action) {
                    actionBtn.innerHTML = `<a href="${data.data.action.link}" target="_blank" class="btn btn-primary fw-bold px-4 rounded-pill">${data.data.action.label}</a>`;
                }

            } else {
                alert('Error: ' + data.error);
                modal.hide();
            }
        })
        .catch(() => {
            loadingEl.style.display = 'none';
            alert('Network error');
            modal.hide();
        });
}


    

    // --- Explicit Window Exports for Inline HTML ---
    window.openCodeModal = openCodeModal;

  })();
} catch (e) {
  console.error("[Fatal Error in lab_code.js]", e);
}
