<?php $sshKeys = Session::get('ssh_keys', []); ?>

<div class="lab-header-section mb-4 d-flex justify-content-between align-items-center">
    <div>
        <h1 class="fw-bold theme-text m-0">Account Settings</h1>
        <p class="text-secondary opacity-75 small">Manage your SSH keys and secure your lab environment.</p>
    </div>
    <button class="btn btn-primary fw-bold px-4 rounded-pill shadow-sm" id="show-add-key-btn">
        <i class='bx bx-plus me-1'></i> Add New Key
    </button>
</div>

<div id="add-key-section"
    class="card border-0 shadow-sm glass-card rounded-4 mb-4 d-none animate__animated animate__fadeIn">
    <div class="card-body p-4">
        <h5 class="fw-bold mb-4">Register New Public Key</h5>
        <form id="sshAddForm">
            <div class="row mb-3 align-items-center">
                <label class="col-sm-3 fw-bold text-secondary small">Key Label</label>
                <div class="col-sm-9"><input type="text" class="form-control" name="title" required
                        placeholder="e.g. Work MacBook Pro"></div>
            </div>
            <div class="row mb-3 align-items-center">
                <label class="col-sm-3 fw-bold text-secondary small">Public Key Content</label>
                <div class="col-sm-9"><textarea class="form-control font-monospace" name="key" rows="4" required
                        placeholder="Begins with 'ssh-rsa' or 'ssh-ed25519'"></textarea></div>
            </div>
            <div class="row mb-4 align-items-center">
                <label class="col-sm-3 fw-bold text-secondary small">Expiration</label>
                <div class="col-sm-9"><input type="date" class="form-control" id="ssh-expiration"
                        name="expiration_date"></div>
            </div>
            <div class="d-flex gap-2 justify-content-end">
                <button type="submit" id="save-key-btn" class="btn btn-warning fw-bold px-4 text-dark shadow-sm">
                    Verify and Save
                </button>
                <button type="button" class="btn btn-secondary px-4"
                    onclick="document.getElementById('add-key-section').classList.add('d-none')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm glass-card rounded-4 overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-dark border-0">
                <tr>
                    <th class="ps-4">Label</th>
                    <th>Fingerprint (SHA256)</th>
                    <th>Created</th>
                    <th>Expires</th>
                    <th class="text-end pe-4">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($sshKeys)): ?>
                <tr>
                    <td colspan="5" class="text-center py-5 text-secondary">No SSH keys found. Add one to access your
                        Labs via SSH.</td>
                </tr>
                <?php else: foreach ($sshKeys as $key): ?>
                <tr>
                    <td class="ps-4 fw-bold"><?= htmlspecialchars($key['title']) ?></td>
                    <td><code class="small text-info"><?= $key['fingerprint'] ?></code></td>
                    <td class="small"><?= date('d M Y', $key['created_at']) ?></td>
                    <td class="small text-warning">
                        <?= $key['expires_at'] ? date('d M Y', $key['expires_at']) : 'Never' ?></td>
                    <td class="text-end pe-4">
                        <button class="btn btn-sm btn-outline-danger border-0"
                            onclick="deleteKey('<?= (string)$key['_id'] ?>')">
                            <i class='bx bx-trash'></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const showBtn = document.getElementById('show-add-key-btn');
    const section = document.getElementById('add-key-section');
    const expInput = document.getElementById('ssh-expiration');
    const addForm = document.getElementById('sshAddForm');
    const saveBtn = document.getElementById('save-key-btn');

    showBtn.onclick = () => {
        section.classList.toggle('d-none');
        const expiry = new Date();
        expiry.setMonth(expiry.getMonth() + 6);
        expInput.value = expiry.toISOString().split('T')[0];
    };

    // PROFESSIONAL FORM HANDLING
    addForm.onsubmit = async (e) => {
        e.preventDefault(); // Stop standard page redirect

        // 1. Show Loading state on button
        const originalText = saveBtn.innerText;
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Verifying...';

        try {
            const formData = new FormData(addForm);
            // Submit to API in the background
            const response = await fetch('/api/account/ssh_add.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.status === 'success') {
                // Success: Reload page normally to see the new key in the table
                window.location.reload();
            } else {
                alert('Error: ' + result.error);
                saveBtn.disabled = false;
                saveBtn.innerText = originalText;
            }
        } catch (error) {
            console.error('Submission failed:', error);
            alert('Server error occurred.');
            saveBtn.disabled = false;
            saveBtn.innerText = originalText;
        }
    };
});

async function deleteKey(id) {
    if (!confirm("Revoke this SSH key? Your labs will be synchronized automatically.")) return;


    const res = await fetch('/api/account/ssh_delete.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            id: id
        })
    });
    const result = await res.json();
    if (result.status === 'success') window.location.reload();
}
</script>