/**
 * services-manager.js — Generic CRUD manager for all service types
 * (MySQL, MongoDB, PostgreSQL, MariaDB, RabbitMQ, Redis)
 *
 * Usage in PHP templates:
 *   ServiceManager.init({ type: 'mysql', ... });
 */
var ServiceManager = (function() {
    'use strict';

    var config = {};
    var currentTargetUser = '';

    function init(opts) {
        config = Object.assign({
            type: '',              // mysql, mongodb, postgresql, mariadb, rabbitmq, redis
            apiBase: '',           // e.g. '/api/services/mysql'
            entityLabel: 'Database', // Database, Vhost
            gridId: '',            // e.g. 'mysql_db_list'
            hasEntities: true,     // false for Redis (users only)
            entityNameKey: 'db_name', // db_name or vhost_name
            userKey: '',           // e.g. 'mysql_username', 'mongodb_username'
            entityCreateEndpoint: '', // e.g. 'db_create', 'vhost_create'
            entityDeleteEndpoint: '', // e.g. 'db_delete', 'vhost_delete'
            checkEndpoint: 'check',
            userCreateEndpoint: 'user_create',
            userDeleteEndpoint: 'user_delete',
            redirectBase: '',      // e.g. '/services/mysql'
            exportPrefix: '',      // e.g. 'MySQL', 'MongoDB'
            deleteConfirmMsg: function(name) {
                return 'Are you absolutely sure you want to permanently delete the user "' + name + '" AND all of their data? This action cannot be undone.';
            },
            entityDeleteConfirmMsg: function(name) {
                return 'Are you absolutely sure you want to permanently drop the ' + config.entityLabel.toLowerCase() + ' "' + name + '"? This action cannot be undone.';
            }
        }, opts);

        // Export to window for inline HTML onclick handlers
        var p = config.exportPrefix;
        window['openAdd' + p + 'UserModal'] = openAddUserModal;
        window['submitCreate' + p + 'User'] = submitCreateUser;
        window['delete' + p + 'User'] = deleteUser;
        if (config.hasEntities) {
            window['openCreate' + p + 'DbModal'] = openCreateDbModal;
            window['submitCreate' + p + 'Db'] = submitCreateDb;
            window['submitCreate' + p + 'DbInline'] = submitCreateDbInline;
            window['switch' + p + 'User'] = switchUser;
            window['delete' + p + 'Db'] = deleteEntity;
        }
    }

    // --- User Management ---

    function openAddUserModal() {
        var modal = new coreui.Modal(document.getElementById('addUserModal'));
        document.getElementById('new-mysql-username').value = '';
        document.getElementById('new-mysql-password').value = '';
        modal.show();
    }

    async function submitCreateUser() {
        var username = document.getElementById('new-mysql-username').value;
        var password = document.getElementById('new-mysql-password').value;
        var btn = document.getElementById('btn-submit-user');
        var originalText = btn.innerHTML;

        if (!username || password.length < 8) {
            alert('Please enter a valid username and a password of at least 8 characters.');
            return;
        }

        try {
            btn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> Adding...';
            btn.disabled = true;

            var response = await fetch(config.apiBase + '/' + config.userCreateEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ username: username, password: password })
            });

            var data = await response.json();

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

    async function deleteUser(username) {
        if (!confirm(config.deleteConfirmMsg(username))) return;

        try {
            var response = await fetch(config.apiBase + '/' + config.userDeleteEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ username: username })
            });

            var data = await response.json();

            if (data.success) {
                window.location.href = config.redirectBase;
            } else {
                alert('Error: ' + (data.error || 'Failed to delete user'));
            }
        } catch (e) {
            alert('Network error occurred.');
        }
    }

    // --- Entity Management (Database/Vhost) ---

    function openCreateDbModal(username) {
        currentTargetUser = username;
        document.getElementById('lbl-create-db-user').innerText = username;
        document.getElementById('db-prefix').innerText = username + '_';
        document.getElementById('new-db-name').value = '';

        var modal = new coreui.Modal(document.getElementById('createDbModal'));
        modal.show();
    }

    async function submitCreateDb() {
        var rawName = document.getElementById('new-db-name').value;
        var btn = document.getElementById('btn-submit-db');
        var originalText = btn.innerHTML;

        if (!rawName) {
            alert('Please enter a ' + config.entityLabel.toLowerCase() + ' name.');
            return;
        }

        try {
            btn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> Creating...';
            btn.disabled = true;

            var body = {};
            body[config.userKey] = currentTargetUser;
            body[config.entityNameKey] = rawName;

            var response = await fetch(config.apiBase + '/' + config.entityCreateEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body)
            });

            var data = await response.json();

            if (data.success) {
                window.location.reload();
            } else {
                alert('Error: ' + (data.error || 'Failed to create ' + config.entityLabel.toLowerCase()));
            }
        } catch (e) {
            alert('Network error occurred.');
        } finally {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    }

    async function submitCreateDbInline() {
        var username = document.getElementById('select-db-user').value;
        var rawName = document.getElementById('new-db-name').value;
        var btn = event.currentTarget;
        var originalText = btn.innerHTML;

        if (!rawName) {
            alert('Please enter a ' + config.entityLabel.toLowerCase() + ' name.');
            return;
        }

        try {
            btn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> Creating...';
            btn.disabled = true;

            var body = {};
            body[config.userKey] = username;
            body[config.entityNameKey] = rawName;

            var response = await fetch(config.apiBase + '/' + config.entityCreateEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body)
            });

            var data = await response.json();

            if (data.success) {
                document.getElementById('new-db-name').value = '';
                switchUser(username);
            } else {
                alert('Error: ' + (data.error || 'Failed to create ' + config.entityLabel.toLowerCase()));
            }
        } catch (e) {
            alert('Network error occurred.');
        } finally {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    }

    async function switchUser(username) {
        if (!username) return;

        window.history.pushState(null, '', '?user=' + username);

        var prefixEl = document.getElementById('db-prefix');
        if (prefixEl) prefixEl.innerText = username + '_';

        var grid = document.getElementById(config.gridId);
        if (!grid) return;

        grid.innerHTML = '<div class="col-12 text-center py-4"><i class="bx bx-loader-alt bx-spin text-primary" style="font-size: 2rem;"></i><p class="text-secondary mt-2">Loading ' + config.entityLabel.toLowerCase() + 's...</p></div>';

        try {
            var response = await fetch(config.apiBase + '/' + config.checkEndpoint + '?user=' + encodeURIComponent(username));
            var data = await response.json();

            if (data.result && Array.isArray(data.result)) {
                if (data.result.length === 0) {
                    grid.innerHTML = '<div class="col-12 text-center py-4"><p class="text-secondary" style="font-size: 0.9rem;">No ' + config.entityLabel.toLowerCase() + 's found for this user.</p></div>';
                } else {
                    grid.innerHTML = data.result.map(function(item) {
                        var name = item[config.entityNameKey] || item.db_name || item.vhost_name;
                        return '<div class="col" id="' + config.type + '_' + config.entityNameKey + '_' + name + '">' +
                            '<div class="card simple-whitebg h-100">' +
                            '<div class="card-body">' +
                            '<div class="d-flex justify-content-between align-items-start mb-3">' +
                            '<div>' +
                            '<div class="text-medium-emphasis small text-uppercase fw-semibold mb-1">' + config.entityLabel + '</div>' +
                            '<h6 class="card-title mb-0">' + name + '</h6>' +
                            '</div>' +
                            '</div>' +
                            '<div class="d-grid">' +
                            '<button class="btn btn-sm btn-outline-danger btn-delete" onclick="delete' + config.exportPrefix + 'Db(\'' + name + '\')">' +
                            'Drop ' + config.entityLabel +
                            '</button>' +
                            '</div>' +
                            '</div>' +
                            '</div>' +
                            '</div>';
                    }).join('');
                }
            } else if (data.error) {
                grid.innerHTML = '<div class="col-12 text-center py-4"><p class="text-danger" style="font-size: 0.9rem;">Error: ' + data.error + '</p></div>';
            }
        } catch (e) {
            grid.innerHTML = '<div class="col-12 text-center py-4"><p class="text-danger" style="font-size: 0.9rem;">Network error loading ' + config.entityLabel.toLowerCase() + 's.</p></div>';
        }
    }

    async function deleteEntity(name) {
        if (!confirm(config.entityDeleteConfirmMsg(name))) return;

        try {
            var body = {};
            body[config.entityNameKey] = name;

            var response = await fetch(config.apiBase + '/' + config.entityDeleteEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body)
            });

            var data = await response.json();

            if (data.success) {
                window.location.reload();
            } else {
                alert('Error: ' + (data.error || 'Failed to delete ' + config.entityLabel.toLowerCase()));
            }
        } catch (e) {
            alert('Network error occurred.');
        }
    }

    return { init: init };
})();
