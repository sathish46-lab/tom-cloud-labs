<?php
// Default to dashboard if not set
$current_tab = $current_tab ?? 'dashboard';
?>
<div class="lab-header-section px-2 mt-3 mb-4">
    <div class="row align-items-center">
        <div class="col-auto">
            <div class="bg-white p-2 rounded shadow-sm d-flex justify-content-center align-items-center" style="width: 72px; height: 72px;">
                <img src="https://www.mysql.com/common/logos/logo-mysql-170x115.png" alt="MongoDB" class="img-fluid" style="max-height: 40px; object-fit: contain;">
            </div>
        </div>
        <div class="col">
            <div class="d-flex flex-column gap-1">
                <h3 class="fw-bold mb-0 ls-tight text-white">MongoDB Server</h3>
                <p class="small" style="max-width: 850px; line-height: 1.5; color: var(--glass-text-muted);">
                    MongoDB is the world's most popular open source database. With its proven performance, reliability and ease-of-use, MongoDB has become the leading database choice for web-based applications, used by high profile web properties including Facebook, Twitter, YouTube, Yahoo! and many more. Additionally, it is an extremely popular choice as embedded database, distributed by thousands of ISVs and OEMs. MongoDB is a key part of LAMP (Linux, Apache, MongoDB, PHP / Perl / Python), the fast growing open source enterprise software stack.
                </p>
                <div class="d-flex gap-2 mt-2">
                    <button class="btn btn-primary rounded-pill px-4" style="background: #6366f1; border-color: #6366f1; font-size: 0.85rem;" onclick="openAddMongoDBUserModal()">Add User</button>
                    <a href="/services/mongodb/database" class="btn btn-outline-light rounded-pill px-4 text-decoration-none" style="border-color: rgba(255,255,255,0.2); font-size: 0.85rem;">Manage Database</a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Navigation Tabs -->
    <div class="row m-0 p-0 mt-4">
        <ul class="nav nav-tabs">
            <li class="nav-item">
                <a class="nav-link <?= ($current_tab === 'dashboard') ? 'active' : '' ?>" href="/services/mongodb">Dashboard</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($current_tab === 'database') ? 'active' : '' ?>" href="/services/mongodb/database">Database</a>
            </li>
        </ul>
    </div>
</div>
