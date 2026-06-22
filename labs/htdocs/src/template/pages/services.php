<?php
Session::addMetaTag('<title>Services - Tom Labs</title>');
Session::addCustomJs('/js/services.js');
Session::addCustomJs('/js/copy.js');
?>

<div class="lab-header-section mb-4 px-4 mt-3">
    <div class="row align-items-center">
        <div class="col">
            <h1 class="fw-bold theme-text m-0" style="font-size: 1.8rem; letter-spacing: -0.5px;">Services</h1>
            <p class="text-secondary opacity-75 mt-2 mb-0" style="font-size: 0.85rem; line-height: 1.7; letter-spacing: 0.2px; max-width: 900px;">
                List of available services are shown here. You can utilize these services inside your laboratory. Some services require authentication and other configuration which can be managed from here. Services are still in beta and we are constantly upgrading them.
            </p>
        </div>
    </div>
</div>

<div class="row row-cols-1 row-cols-lg-2 row-cols-xl-3 g-4 px-4 pb-4" style="align-items: start;">
    <!-- MySQL Server Card -->
    <div class="col">
        <div class="card border border-light border-opacity-10 shadow-lg position-relative overflow-hidden" style="background: #111827; border-radius: 12px;">
            <div class="card-body p-4 position-relative z-1">
                <div class="d-flex align-items-center mb-4">
                    <div class="bg-white p-2 rounded me-3 shadow-sm d-flex justify-content-center align-items-center" style="width: 54px; height: 54px;">
                        <img src="https://www.mysql.com/common/logos/logo-mysql-170x115.png" alt="MySQL" class="img-fluid" style="max-height: 36px; object-fit: contain;">
                    </div>
                    <div>
                        <h5 class="card-title fw-bold mb-1 text-white" style="font-size: 1.1rem;">MySQL Server</h5>
                        <span class="badge rounded-pill bg-primary bg-opacity-25 text-primary border border-primary border-opacity-25 px-2 py-1" style="font-size: 0.65rem;">mysql.tomweb.in</span>
                    </div>
                </div>

                <p class="text-secondary mb-4" style="font-size: 0.85rem; line-height: 1.5;">
                    MySQL is the world's most popular open source database.
                </p>

                <div class="bg-dark bg-opacity-50 rounded p-2 px-3 mb-3 border border-light border-opacity-10">
                    <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom border-light border-opacity-10">
                        <span class="text-secondary fw-semibold" style="font-size: 0.8rem;">Hostname</span>
                        <div class="d-flex align-items-center">
                            <span class="text-light font-monospace me-2" style="font-size: 0.8rem;">mysql.tomweb.in</span>
                            <i class='bx bx-copy text-secondary cursor-pointer hover-white' onclick="copyText('mysql.tomweb.in', 'Hostname copied');"></i>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-secondary fw-semibold" style="font-size: 0.8rem;">MySQL Database Service</span>
                        <div class="d-flex align-items-center">
                            <span class="text-light font-monospace me-2" style="font-size: 0.8rem;">3306</span>
                            <i class='bx bx-copy text-secondary cursor-pointer hover-white' onclick="copyText('3306', 'Port copied');"></i>
                        </div>
                    </div>
                </div>

                <h6 class="text-white fw-bold mb-2" style="font-size: 0.8rem;">Port Forwarding</h6>
                <div class="bg-dark bg-opacity-50 rounded p-2 px-3 border border-light border-opacity-10 d-flex justify-content-between align-items-center">
                    <span class="text-secondary fw-semibold" style="font-size: 0.8rem;">VS Code Desktop</span>
                    <div class="d-flex align-items-center">
                        <span class="text-light font-monospace me-2" style="font-size: 0.8rem;">mysql.tomweb.in:3306</span>
                        <i class='bx bx-copy text-secondary cursor-pointer hover-white' onclick="copyText('mysql.tomweb.in:3306', 'Port forwarding info copied');"></i>
                    </div>
                </div>
            </div>

            <div class="card-footer bg-transparent border-top-0 p-4 pt-0 d-flex justify-content-end gap-2 position-relative z-1">
                <a href="/services/mysql" class="btn btn-primary rounded-pill px-4" style="background: #6366f1; border: none; font-size: 0.85rem; font-weight: 600;">Manage</a>
                <a href="https://dev.mysql.com/doc/" target="_blank" class="btn btn-success rounded-pill px-4 text-white" style="font-size: 0.85rem; font-weight: 600;">Learn More</a>
            </div>
        </div>
    </div>

    <!-- Adminer Card -->
    <div class="col">
        <div class="card border border-light border-opacity-10 shadow-lg position-relative overflow-hidden" style="background: #111827; border-radius: 12px;">
            <div class="card-body p-4 position-relative z-1">
                <div class="d-flex align-items-center mb-4">
                    <div class="bg-white p-2 rounded me-3 shadow-sm d-flex justify-content-center align-items-center" style="width: 54px; height: 54px;">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#2196F3" style="width: 32px; height: 32px;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>
                    </div>
                    <div>
                        <h5 class="card-title fw-bold mb-1 text-white" style="font-size: 1.1rem;">Adminer</h5>
                        <span class="badge rounded-pill bg-success bg-opacity-25 text-success border border-success border-opacity-25 px-2 py-1" style="font-size: 0.65rem;">v4.8.1</span>
                    </div>
                </div>

                <p class="text-secondary mb-4" style="font-size: 0.85rem; line-height: 1.5;">
                    Adminer (formerly phpMinAdmin) is a full-featured database management tool written in PHP. Supports MySQL, PostgreSQL, SQLite, MS SQL, Oracle and more.
                </p>

                <div class="bg-dark bg-opacity-50 rounded p-2 px-3 mb-3 border border-light border-opacity-10">
                    <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom border-light border-opacity-10">
                        <span class="text-secondary fw-semibold" style="font-size: 0.8rem;">Hostname</span>
                        <div class="d-flex align-items-center">
                            <span class="text-light font-monospace me-2" style="font-size: 0.8rem;">adminer.tomweb.in</span>
                            <i class='bx bx-copy text-secondary cursor-pointer hover-white' onclick="copyText('adminer.tomweb.in', 'Hostname copied');"></i>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-secondary fw-semibold" style="font-size: 0.8rem;">Database Adminer (HTTP)</span>
                        <div class="d-flex align-items-center">
                            <span class="text-light font-monospace me-2" style="font-size: 0.8rem;">8080</span>
                            <i class='bx bx-copy text-secondary cursor-pointer hover-white' onclick="copyText('8080', 'Port copied');"></i>
                        </div>
                    </div>
                </div>

                <h6 class="text-white fw-bold mb-2" style="font-size: 0.8rem;">Port Forwarding</h6>
                <div class="bg-dark bg-opacity-50 rounded p-2 px-3 border border-light border-opacity-10 d-flex justify-content-between align-items-center">
                    <span class="text-secondary fw-semibold" style="font-size: 0.8rem;">VS Code Desktop</span>
                    <div class="d-flex align-items-center">
                        <span class="text-light font-monospace me-2" style="font-size: 0.8rem;">adminer.tomweb.in:8080</span>
                        <i class='bx bx-copy text-secondary cursor-pointer hover-white' onclick="copyText('adminer.tomweb.in:8080', 'Port forwarding info copied');"></i>
                    </div>
                </div>
            </div>

            <div class="card-footer bg-transparent border-top-0 p-4 pt-0 d-flex justify-content-end gap-2 position-relative z-1">
                <a href="https://www.adminer.org/" target="_blank" class="btn btn-success rounded-pill px-4 text-white" style="font-size: 0.85rem; font-weight: 600;">Learn More</a>
            </div>
        </div>
    </div>

    <!-- MariaDB Placeholder Card -->
    <div class="col">
        <div class="card border border-light border-opacity-10 shadow-lg position-relative overflow-hidden" style="background: #111827; border-radius: 12px;">
            <div class="card-body p-4 position-relative z-1">
                <div class="d-flex align-items-center mb-4">
                    <div class="bg-white p-2 rounded me-3 shadow-sm d-flex justify-content-center align-items-center" style="width: 54px; height: 54px;">
                        <img src="https://mariadb.org/wp-content/uploads/2019/11/mariadb-seal-browntext.png" alt="MariaDB" class="img-fluid" style="max-height: 36px; object-fit: contain;">
                    </div>
                    <div>
                        <h5 class="card-title fw-bold mb-1 text-white" style="font-size: 1.1rem;">MariaDB Server</h5>
                        <span class="badge rounded-pill bg-danger bg-opacity-25 text-danger border border-danger border-opacity-25 px-2 py-1" style="font-size: 0.65rem;">Coming Soon</span>
                    </div>
                </div>
                <p class="text-secondary mb-4" style="font-size: 0.85rem; line-height: 1.5;">
                    MariaDB Server is one of the most popular open source relational databases. It's made by the original developers of MySQL.
                </p>
            </div>
            <div class="card-footer bg-transparent border-top-0 p-4 pt-0 d-flex justify-content-end gap-2 position-relative z-1">
                <button class="btn btn-secondary rounded-pill px-4 disabled" style="border: none; font-size: 0.85rem; font-weight: 600;">Manage</button>
                <a href="https://mariadb.com/kb/en/" target="_blank" class="btn btn-success rounded-pill px-4 text-white" style="font-size: 0.85rem; font-weight: 600;">Learn More</a>
            </div>
        </div>
    </div>

    <!-- PostgreSQL Placeholder Card -->
    <div class="col">
        <div class="card border border-light border-opacity-10 shadow-lg position-relative overflow-hidden" style="background: #111827; border-radius: 12px;">
            <div class="card-body p-4 position-relative z-1">
                <div class="d-flex align-items-center mb-4">
                    <div class="bg-white p-2 rounded me-3 shadow-sm d-flex justify-content-center align-items-center" style="width: 54px; height: 54px;">
                        <img src="https://wiki.postgresql.org/images/a/a4/PostgreSQL_logo.3colors.svg" alt="PostgreSQL" class="img-fluid" style="max-height: 36px; object-fit: contain;">
                    </div>
                    <div>
                        <h5 class="card-title fw-bold mb-1 text-white" style="font-size: 1.1rem;">PostgreSQL Server</h5>
                        <span class="badge rounded-pill bg-info bg-opacity-25 text-info border border-info border-opacity-25 px-2 py-1" style="font-size: 0.65rem;">Coming Soon</span>
                    </div>
                </div>
                <p class="text-secondary mb-4" style="font-size: 0.85rem; line-height: 1.5;">
                    PostgreSQL is a powerful, open source object-relational database system with over 30 years of active development.
                </p>
            </div>
            <div class="card-footer bg-transparent border-top-0 p-4 pt-0 d-flex justify-content-end gap-2 position-relative z-1">
                <button class="btn btn-secondary rounded-pill px-4 disabled" style="border: none; font-size: 0.85rem; font-weight: 600;">Manage</button>
                <a href="https://www.postgresql.org/docs/" target="_blank" class="btn btn-success rounded-pill px-4 text-white" style="font-size: 0.85rem; font-weight: 600;">Learn More</a>
            </div>
        </div>
    </div>
</div>

<style>
.cursor-pointer { cursor: pointer; }
.hover-white { transition: color 0.2s; }
.hover-white:hover { color: #ffffff !important; }
</style>
