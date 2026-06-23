// --- RabbitMQ User Management ---

function openAddRabbitMQUserModal() {
    const modal = new coreui.Modal(document.getElementById('addUserModal'));
    document.getElementById('new-mysql-username').value = '';
    document.getElementById('new-mysql-password').value = '';
    modal.show();
}

async function submitCreateRabbitMQUser() {
    const username = document.getElementById('new-mysql-username').value;
    const password = document.getElementById('new-mysql-password').value;
    const btn = document.getElementById('btn-submit-user');
    const originalText = btn.innerHTML;
    
    if (!username || password.length < 8) {
        alert("Please enter a valid username and a password of at least 8 characters.");
        return;
    }

    try {
        btn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> Adding...';
        btn.disabled = true;

        const response = await fetch('/api/services/rabbitmq/user_create', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username, password })
        });

        const data = await response.json();

        if (data.success) {
            window.location.reload();
        } else {
            alert('Error: ' + (data.error || 'Failed to create user'));
        }
    } catch (e) {
        alert('Network error occurred.');
    } finally {
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
}

async function deleteRabbitMQUser(username) {
    if (!confirm(`Are you absolutely sure you want to permanently delete the user "${username}" AND all of their vhosts? This action cannot be undone.`)) {
        return;
    }

    try {
        const response = await fetch('/api/services/rabbitmq/user_delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username })
        });

        const data = await response.json();

        if (data.success) {
            window.location.href = '/services/rabbitmq';
        } else {
            alert('Error: ' + (data.error || 'Failed to delete user'));
        }
    } catch (e) {
        alert('Network error occurred.');
    }
}

// --- RabbitMQ Vhost Management ---

var currentTargetUser = '';

function openCreateRabbitMQVhostModal(username) {
    currentTargetUser = username;
    document.getElementById('lbl-create-db-user').innerText = username;
    document.getElementById('db-prefix').innerText = username + '_';
    document.getElementById('new-db-name').value = '';
    
    const modal = new coreui.Modal(document.getElementById('createDbModal'));
    modal.show();
}

async function submitCreateRabbitMQVhost() {
    const rawDbName = document.getElementById('new-db-name').value;
    const btn = document.getElementById('btn-submit-db');
    const originalText = btn.innerHTML;
    
    if (!rawDbName) {
        alert("Please enter a vhost name.");
        return;
    }

    try {
        btn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> Creating...';
        btn.disabled = true;

        const response = await fetch('/api/services/rabbitmq/vhost_create', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                rabbitmq_username: currentTargetUser,
                vhost_name: rawDbName 
            })
        });

        const data = await response.json();

        if (data.success) {
            window.location.reload();
        } else {
            alert('Error: ' + (data.error || 'Failed to create vhost'));
        }
    } catch (e) {
        alert('Network error occurred.');
    } finally {
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
}

async function submitCreateRabbitMQVhostInline() {
    const username = document.getElementById('select-db-user').value;
    const rawDbName = document.getElementById('new-db-name').value;
    const collation = document.getElementById('new-db-collation').value;
    const btn = event.currentTarget;
    const originalText = btn.innerHTML;
    
    if (!rawDbName) {
        alert("Please enter a vhost name.");
        return;
    }

    try {
        btn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> Creating...';
        btn.disabled = true;

        const response = await fetch('/api/services/rabbitmq/vhost_create', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                rabbitmq_username: username,
                vhost_name: rawDbName,
                collation: collation
            })
        });

        const data = await response.json();

        if (data.success) {
            // Re-fetch vhosts dynamically instead of reload
            document.getElementById('new-db-name').value = '';
            switchRabbitMQUser(username);
        } else {
            alert('Error: ' + (data.error || 'Failed to create vhost'));
        }
    } catch (e) {
        alert('Network error occurred.');
    } finally {
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
}

async function switchRabbitMQUser(username) {
    if (!username) return;

    // Update URL without reload
    window.history.pushState(null, '', '?user=' + username);
    
    // Update prefix
    const prefixEl = document.getElementById('db-prefix');
    if (prefixEl) prefixEl.innerText = username + '_';

    const grid = document.getElementById('rabbitmq_db_list');
    if (!grid) return;

    grid.innerHTML = '<div class="col-12 text-center py-4"><i class="bx bx-loader-alt bx-spin text-primary" style="font-size: 2rem;"></i><p class="text-secondary mt-2">Loading vhosts...</p></div>';

    try {
        const response = await fetch('/api/services/rabbitmq/check?user=' + encodeURIComponent(username));
        const data = await response.json();

        if (data.result && Array.isArray(data.result)) {
            if (data.result.length === 0) {
                grid.innerHTML = '<div class="col-12 text-center py-4"><p class="text-secondary" style="font-size: 0.9rem;">No vhosts found for this user.</p></div>';
            } else {
                grid.innerHTML = data.result.map(dbObj => `
                    <div class="col rabbitmq_db" id="rabbitmq_vhost_${dbObj.vhost_name}">
                        <div class="card simple-whitebg h-100">
                            <div class="card-body">

                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <div class="text-medium-emphasis small text-uppercase fw-semibold mb-1">Vhost</div>
                                        <h6 class="card-title mb-0">${dbObj.vhost_name}</h6>
                                    </div>
                                    <span class="badge bg-light text-dark mysql-collation text-lowercase">${dbObj.collation}</span>
                                </div>

                                <div class="d-grid">
                                    <button class="btn btn-sm btn-outline-danger btn-delete" data-dbname="${dbObj.vhost_name}" onclick="deleteRabbitMQDb('${dbObj.vhost_name}')">
                                        Drop Vhost
                                    </button>
                                </div>

                            </div>
                        </div>
                    </div>
                `).join('');
            }
        } else if (data.error) {
            grid.innerHTML = '<div class="col-12 text-center py-4"><p class="text-danger" style="font-size: 0.9rem;">Error: ' + data.error + '</p></div>';
        }
    } catch (e) {
        grid.innerHTML = '<div class="col-12 text-center py-4"><p class="text-danger" style="font-size: 0.9rem;">Network error loading vhosts.</p></div>';
    }
}

async function deleteRabbitMQDb(dbName) {
    if (!confirm(`Are you absolutely sure you want to permanently drop the vhost "${dbName}"? This action cannot be undone.`)) {
        return;
    }

    try {
        const response = await fetch('/api/services/rabbitmq/vhost_delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ vhost_name: dbName })
        });

        const data = await response.json();

        if (data.success) {
            window.location.reload();
        } else {
            alert('Error: ' + (data.error || 'Failed to delete vhost'));
        }
    } catch (e) {
        alert('Network error occurred.');
    }
}
