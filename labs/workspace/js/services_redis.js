/**
 * Wrapped with IIFE Error Boundary
 */
try {
  (function() {
    "use strict";


// --- Redis User Management ---

function openAddRedisUserModal() {
    const modal = new coreui.Modal(document.getElementById('addUserModal'));
    document.getElementById('new-mysql-username').value = '';
    document.getElementById('new-mysql-password').value = '';
    modal.show();
}

async function submitCreateRedisUser() {
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

        const response = await fetch('/api/services/redis/user_create', {
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

async function deleteRedisUser(username) {
    if (!confirm(`Are you absolutely sure you want to permanently delete the user "${username}" AND all of their databases? This action cannot be undone.`)) {
        return;
    }

    try {
        const response = await fetch('/api/services/redis/user_delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username })
        });

        const data = await response.json();

        if (data.success) {
            window.location.href = '/services/redis';
        } else {
            alert('Error: ' + (data.error || 'Failed to delete user'));
        }
    } catch (e) {
        alert('Network error occurred.');
    }
}


    

    // --- Explicit Window Exports for Inline HTML ---
    window.deleteRedisUser = deleteRedisUser;
    window.openAddRedisUserModal = openAddRedisUserModal;
    window.submitCreateRedisUser = submitCreateRedisUser;

  })();
} catch (e) {
  console.error("[Fatal Error in services_redis.js]", e);
}
