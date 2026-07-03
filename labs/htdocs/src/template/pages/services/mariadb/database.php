<?php
Session::addMetaTag('<title>MariaDB Databases - Tom Labs</title>');
Session::addCustomJs('/js/services_mariadb.js');

$user = Session::getUser();
$db = DatabaseConnection::getClient()->selectDatabase('tom_labs_db');

require_once $_SERVER['DOCUMENT_ROOT'] . '/src/lib/services/MariaDbManager.php';
$mysqlManager = new MariaDbManager();
$collations = $mysqlManager->getCollations();

// Fetch the MariaDB users
$mysqlUsers = $db->mariadb_users->find(['user_id' => $user->getUserId()])->toArray();

$selectedUsername = $_GET['user'] ?? '';
$selectedUserObj = null;

if (!empty($selectedUsername)) {
    foreach ($mysqlUsers as $mu) {
        if ($mu['mariadb_username'] === $selectedUsername) {
            $selectedUserObj = $mu;
            break;
        }
    }
} else if (!empty($mysqlUsers)) {
    // Default to the first user if none selected
    $selectedUserObj = $mysqlUsers[0];
    $selectedUsername = $selectedUserObj['mariadb_username'];
}

$databases = [];
if ($selectedUserObj) {
    $databases = $db->mariadb_databases->find(['mariadb_user_id' => (string)$selectedUserObj['_id']])->toArray();
}
?>

<?php
$current_tab = 'database';
require_once __DIR__ . '/partials/mariadb_header.php';
?>

<div class="container-fluid px-2 bg-transparent">
    <!-- Database Content -->
    <div class="row g-4" style="align-items: start;">
        
        <?php if (empty($mysqlUsers)): ?>
            <div class="col-12 text-center py-5">
                <i class='bx bx-user-x text-secondary' style="font-size: 4rem;"></i>
                <h4 class="mt-3 text-light">No MariaDB Users Found</h4>
                <p class="text-secondary">You must create a MariaDB Server User from the Dashboard before you can create databases.</p>
                <a href="/services/mariadb" class="btn btn-primary rounded-pill mt-3 px-4 shadow-sm" style="background: linear-gradient(135deg, #6366f1, #8b5cf6); border: none;">Go to Dashboard</a>
            </div>
        <?php else: ?>

            <!-- Left Content: Create Database -->
        <div class="col-lg-3 col-xl-3 mb-4">
                <div class="card border-0 shadow-sm rounded-4 mb-4" style="background-color: #111827; border: 1px solid rgba(255,255,255,0.05) !important;">
                    <div class="card-header bg-transparent border-bottom-0 pt-4 pb-2">
                        <h6 class="card-title fw-bold text-white mb-0" style="font-size: 1rem;">Create Database</h6>
                    </div>
                    <div class="card-body px-4 pb-4">
                        <div class="mb-3">
                            <label class="form-label text-secondary small fw-bold">Username</label>
                            <select id="select-db-user" class="form-select text-white shadow-none" style="background-color: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; cursor: pointer; color-scheme: dark;" onchange="switchMariaDBUser(this.value)">
                                <?php foreach ($mysqlUsers as $mu): ?>
                                    <option value="<?= htmlspecialchars($mu['mariadb_username']) ?>" <?= ($selectedUsername === $mu['mariadb_username']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($mu['mariadb_username']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-secondary small fw-bold">Database Name</label>
                            <div class="input-group" style="background: rgba(0,0,0,0.2); border-radius: 8px; border: 1px solid rgba(255,255,255,0.1);">
                                <span class="input-group-text text-secondary border-0 bg-transparent pe-1" id="db-prefix" style="border-radius: 8px 0 0 8px;"><?= htmlspecialchars($selectedUsername) ?>_</span>
                                <input type="text" id="new-db-name" class="form-control text-white border-0 bg-transparent ps-0 shadow-none" style="border-radius: 0 8px 8px 0;">
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label text-secondary small fw-bold">Collation</label>
                            <select id="new-db-collation" class="form-select text-white shadow-none" style="background-color: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; cursor: pointer; color-scheme: dark;">
                                <?php foreach ($collations as $charset => $charsetCollations): ?>
                                    <optgroup label="<?= htmlspecialchars($charset) ?>">
                                        <?php foreach ($charsetCollations as $col): ?>
                                            <option value="<?= htmlspecialchars($col) ?>" <?= ($col === 'utf8mb4_0900_ai_ci') ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($col) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button class="btn rounded-pill px-4 shadow-sm text-white" style="background-color: #6366f1; border: none; font-size: 0.85rem; font-weight: 600;" onclick="submitCreateMariaDBDbInline()">
                            Create Database
                        </button>
                    </div>
                </div>
            </div>

            <!-- Right Content: Databases List -->
        <div class="col-lg-9 col-xl-9">
                <div class="card border-0 shadow-sm rounded-4 mb-4" style="background-color: #111827; border: 1px solid rgba(255,255,255,0.05) !important;">
                    <div class="card-header bg-transparent border-bottom-0 pt-4 pb-2">
                        <h6 class="card-title fw-bold text-white mb-1" style="font-size: 1rem;">MariaDB Server Databases</h6>
                        <p class="text-secondary small mb-0">Your database will be listed below</p>
                    </div>
                    
                    <div class="card-body px-4 pb-4">
                        <div id="mariadb_db_list" class="row row-cols-1 row-cols-md-3 g-3">
                            
                            <?php if (empty($databases)): ?>
                                <div class="col-12 text-center py-4">
                                    <p class="text-secondary" style="font-size: 0.9rem;">No databases found for this user.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($databases as $dbObj): ?>
                                    <div class="col mariadb_db" id="mariadb_database_<?= htmlspecialchars($dbObj['db_name']) ?>">
                                        <div class="card h-100 border-0" style="background-color: rgba(0,0,0,0.2); border-radius: 12px; border: 1px solid rgba(255,255,255,0.05) !important;">
                                            <div class="card-body">

                                                <div class="d-flex justify-content-between align-items-start mb-3">
                                                    <div>
                                                        <div class="text-secondary small fw-bold mb-1" style="font-size: 0.65rem; letter-spacing: 1px;">DATABASE</div>
                                                        <h6 class="card-title mb-0 text-white font-monospace"><?= htmlspecialchars($dbObj['db_name']) ?></h6>
                                                    </div>
                                                    <span class="badge bg-dark bg-opacity-50 text-light border border-light border-opacity-10 mysql-collation text-lowercase"><?= htmlspecialchars($dbObj['collation'] ?? 'utf8mb4_0900_ai_ci') ?></span>
                                                </div>

                                                <div class="d-grid">
                                                    <button class="btn btn-sm btn-outline-danger btn-delete" data-dbname="<?= htmlspecialchars($dbObj['db_name']) ?>" onclick="deleteMariaDBDb('<?= htmlspecialchars($dbObj['db_name']) ?>')">
                                                        Drop Database
                                                    </button>
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

        <?php endif; ?>

    </div>
</div>

<style>
.cursor-pointer { cursor: pointer; }
.hover-white { transition: color 0.2s; }
.hover-white:hover { color: #ffffff !important; }
.active-user-item {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.2), rgba(139, 92, 246, 0.2)) !important;
    border-left: 3px solid #8b5cf6 !important;
    color: #fff !important;
}
.active-user-item:hover {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.3), rgba(139, 92, 246, 0.3)) !important;
}
</style>
