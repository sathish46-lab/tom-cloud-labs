<?php
Session::addMetaTag('<title>RabbitMQ Server - Tom Labs</title>');
Session::addCustomJs('/js/services_rabbitmq.js');
Session::addCustomJs('/js/copy.js');

$user = Session::getUser();
$db = DatabaseConnection::getClient()->selectDatabase('tom_labs_db');

// Fetch the RabbitMQ users
$mysqlUsers = $db->rabbitmq_users->find(['user_id' => $user->getUserId()])->toArray();

// For each user, get the number of vhosts they have
foreach ($mysqlUsers as &$mUser) {
    $mUser['vhost_count'] = $db->rabbitmq_vhosts->countDocuments(['rabbitmq_user_id' => (string)$mUser['_id']]);
}
unset($mUser);

?>

<?php
$current_tab = 'dashboard';
require_once __DIR__ . '/partials/rabbitmq_header.php';
?>

<div class="container-fluid px-2 bg-transparent">
    <!-- Dashboard Content -->
    <div class="row g-4" style="align-items: start;">
        <!-- Left Column: Connection Info -->
        <div class="col-lg-5 col-xl-4">
            <div class="card border-0 shadow-sm rounded-4 mb-4" style="background-color: #111827; border: 1px solid rgba(255,255,255,0.05) !important;">
                <div class="card-header bg-transparent border-bottom-0 pt-4 pb-2">
                    <h5 class="card-title fw-bold text-white mb-0" style="font-size: 1.1rem;">Connection Information</h5>
                </div>
                <div class="card-body px-4 pb-4">
                    <div class="mb-3 d-flex justify-content-between align-items-center bg-dark bg-opacity-50 p-2 px-3 rounded border border-light border-opacity-10">
                        <span class="text-secondary fw-semibold" style="font-size: 0.8rem;">Service</span>
                        <span class="text-light" style="font-size: 0.85rem;">RabbitMQ Server</span>
                    </div>

                    <div class="mb-3 d-flex justify-content-between align-items-center bg-dark bg-opacity-50 p-2 px-3 rounded border border-light border-opacity-10">
                        <span class="text-secondary fw-semibold" style="font-size: 0.8rem;">Hostname</span>
                        <div class="d-flex align-items-center">
                            <span class="text-light font-monospace me-2" style="font-size: 0.8rem;">rabbitmq.selfmade.ninja</span>
                            <i class='bx bx-copy text-secondary cursor-pointer hover-white' onclick="copyText('rabbitmq.selfmade.ninja', 'Hostname copied');"></i>
                        </div>
                    </div>
                    <div class="mb-0 d-flex justify-content-between align-items-center bg-dark bg-opacity-50 p-2 px-3 rounded border border-light border-opacity-10">
                        <span class="text-secondary fw-semibold" style="font-size: 0.8rem;">Port</span>
                        <div class="d-flex align-items-center">
                            <span class="text-light font-monospace me-2" style="font-size: 0.8rem;">5672</span>
                            <i class='bx bx-copy text-secondary cursor-pointer hover-white' onclick="copyText('5672', 'Port copied');"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: RabbitMQ Users -->
        <div class="col-lg-7 col-xl-8">
            <div class="card border-0 shadow-sm rounded-4 mb-4" style="background-color: #111827; border: 1px solid rgba(255,255,255,0.05) !important;">
                <div class="card-header bg-transparent border-bottom-0 pt-4 pb-2 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title fw-bold text-white mb-0 d-inline-block me-2" style="font-size: 1.1rem;">RabbitMQ Server Users</h5>
                        <span class="text-secondary" style="font-size: 0.8rem;">(Maximum 5 users)</span>
                    </div>
                    <button class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm" style="background: linear-gradient(135deg, #6366f1, #8b5cf6); border: none; font-size: 0.8rem; font-weight: 600;" onclick="openAddRabbitMQUserModal()"><i class='bx bx-plus'></i> Add User</button>
                </div>
                <div class="card-body px-4 pb-4">
                    <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3">
                        
                        <?php if (empty($mysqlUsers)): ?>
                            <div class="col-12 text-center py-5">
                                <p class="text-secondary" style="font-size: 0.9rem;">No users found. Click 'Add User' to create one.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($mysqlUsers as $mu): ?>
                                <div class="col">
                                    <div class="card border-0 shadow-sm position-relative overflow-hidden" style="background-color: rgba(0,0,0,0.2); border-radius: 12px; border: 1px solid rgba(255,255,255,0.05) !important;">
                                        <div class="position-absolute bottom-0 start-0 w-100" ></div>
                                        
                                        <div class="card-body p-3 d-flex flex-column">
                                            <div class="mb-3">
                                                <small class="text-secondary fw-bold" style="font-size: 0.65rem; letter-spacing: 1px;">USERNAME</small>
                                                <div class="d-flex justify-content-between align-items-center bg-dark bg-opacity-50 p-2 rounded mt-1 border border-light border-opacity-10">
                                                    <span class="text-light font-monospace" style="font-size: 0.8rem;"><?= htmlspecialchars($mu['rabbitmq_username']) ?></span>
                                                    <i class='bx bx-copy text-secondary cursor-pointer hover-white' onclick="copyText('<?= htmlspecialchars($mu['rabbitmq_username']) ?>', 'Username copied');"></i>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <small class="text-secondary fw-bold" style="font-size: 0.65rem; letter-spacing: 1px;">PASSWORD</small>
                                                <div class="d-flex justify-content-between align-items-center bg-dark bg-opacity-50 p-2 rounded mt-1 border border-light border-opacity-10">
                                                    <span class="text-light font-monospace" style="font-size: 0.8rem;">••••••••••••</span>
                                                    <div>
                                                        <i class='bx bx-show text-secondary cursor-pointer hover-white me-2' onclick="alert('Password: <?= htmlspecialchars(base64_decode($mu['rabbitmq_password'])) ?>')"></i>
                                                        <i class='bx bx-copy text-secondary cursor-pointer hover-white' onclick="copyText('<?= htmlspecialchars(base64_decode($mu['rabbitmq_password'])) ?>', 'Password copied');"></i>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="mb-4 text-secondary fw-semibold" style="font-size: 0.75rem;">
                                                <?= $mu['vhost_count'] ?> / 10 vhosts
                                            </div>

                                            <div class="d-flex flex-column gap-2 mt-auto">
                                                <a href="/services/rabbitmq/vhost?user=<?= htmlspecialchars($mu['rabbitmq_username']) ?>" class="btn btn-sm btn-primary rounded-pill w-100" style="background: linear-gradient(135deg, #6366f1, #8b5cf6); border: none; font-size: 0.8rem; font-weight: 600;">Manage Vhosts</a>
                                                <button class="btn btn-sm btn-outline-danger rounded-pill w-100" style="font-size: 0.8rem; font-weight: 600;" onclick="deleteRabbitMQUser('<?= htmlspecialchars($mu['rabbitmq_username']) ?>')">Delete User</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="background: #1e293b; border: 1px solid rgba(255,255,255,0.1); border-radius: 16px;">
      <div class="modal-header border-bottom-0 pb-0">
        <h5 class="modal-title text-white fw-bold">Add RabbitMQ User</h5>
        <button type="button" class="btn-close btn-close-white" data-coreui-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
            <label class="form-label text-secondary small fw-bold">USERNAME</label>
            <input type="text" id="new-mysql-username" class="form-control bg-dark text-white border-0" placeholder="e.g. john_doe" style="border-radius: 8px;">
        </div>
        <div class="mb-3">
            <label class="form-label text-secondary small fw-bold">PASSWORD</label>
            <input type="password" id="new-mysql-password" class="form-control bg-dark text-white border-0" placeholder="••••••••••••" style="border-radius: 8px;">
            <div class="form-text text-secondary mt-2">Password must be at least 8 characters.</div>
        </div>
      </div>
      <div class="modal-footer border-top-0 pt-0">
        <button type="button" class="btn btn-link text-secondary text-decoration-none" data-coreui-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary rounded-pill px-4" style="background: linear-gradient(135deg, #6366f1, #8b5cf6); border: none;" onclick="submitCreateRabbitMQUser()" id="btn-submit-user">Add User</button>
      </div>
    </div>
  </div>
</div>

<style>
.cursor-pointer { cursor: pointer; }
.hover-white { transition: color 0.2s; }
.hover-white:hover { color: #ffffff !important; }
</style>
