<?php
Session::addMetaTag('<title>Adminer - Tom Labs</title>');
Session::addCustomJs('/assets/js/copy.js');
?>

<div class="lab-header-section px-2 mt-3 mb-4">
    <div class="row align-items-center">
        <div class="col-auto">
            <div class="bg-white p-2 rounded shadow-sm d-flex justify-content-center align-items-center" style="width: 72px; height: 72px;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#2196F3" style="width: 40px; height: 40px;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>
            </div>
        </div>
        <div class="col">
            <div class="d-flex flex-column gap-1">
                <h3 class="fw-bold mb-0 ls-tight text-white">Adminer Database Manager</h3>
                <p class="small" style="max-width: 850px; line-height: 1.5; color: var(--glass-text-muted);">
                    Adminer is a fast, lightweight, and full-featured database management tool. It provides an intuitive web interface for managing databases, tables, relations, indexes, users, and more. It natively supports MySQL and other relational databases. Use the port forwarding instructions below to connect to it securely through your lab environment.
                </p>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid px-2 bg-transparent">
    <div class="row g-4" style="align-items: start;">
        <div class="col-12 col-xl-8">
            <div class="card border-0 shadow-sm rounded-4 mb-4" style="background-color: var(--cui-card-bg);">
                <div class="card-header bg-transparent border-bottom-0 pt-4 pb-2">
                    <h5 class="card-title fw-bold text-white mb-0" style="font-size: 1.1rem;">Connection Instructions</h5>
                </div>
                <div class="card-body px-4 pb-4">
                    <p class="text-secondary mb-4" style="font-size: 0.9rem;">
                        Adminer runs inside the isolated VPS environment and is NOT exposed directly to the internet for security. To access the web interface, you must use <strong>VS Code Port Forwarding</strong> from inside your lab container.
                    </p>
                    
                    <div class="mb-3 d-flex justify-content-between align-items-center bg-dark bg-opacity-50 p-3 rounded border border-light border-opacity-10">
                        <span class="text-secondary fw-semibold" style="font-size: 0.85rem;">1. Open VS Code</span>
                        <span class="text-light" style="font-size: 0.85rem;">Connect to your lab via SSH</span>
                    </div>
                    <div class="mb-3 d-flex justify-content-between align-items-center bg-dark bg-opacity-50 p-3 rounded border border-light border-opacity-10">
                        <span class="text-secondary fw-semibold" style="font-size: 0.85rem;">2. Open "Ports" Tab</span>
                        <span class="text-light" style="font-size: 0.85rem;">Click 'Add Port' at the bottom</span>
                    </div>
                    <div class="mb-3 d-flex justify-content-between align-items-center bg-dark bg-opacity-50 p-3 rounded border border-light border-opacity-10">
                        <span class="text-secondary fw-semibold" style="font-size: 0.85rem;">3. Enter the Forwarding Address</span>
                        <div class="d-flex align-items-center">
                            <span class="text-info font-monospace fw-bold me-3" style="font-size: 0.95rem;">adminer.tomweb.in:8080</span>
                            <i class='bx bx-copy text-secondary cursor-pointer hover-white' style="font-size: 1.2rem;" data-copy="adminer.tomweb.in:8080"></i>
                        </div>
                    </div>
                    <div class="mb-0 d-flex justify-content-between align-items-center bg-dark bg-opacity-50 p-3 rounded border border-light border-opacity-10">
                        <span class="text-secondary fw-semibold" style="font-size: 0.85rem;">4. Open in Web Browser</span>
                        <span class="text-light" style="font-size: 0.85rem;">Click the globe icon in VS Code to open it</span>
                    </div>

                    <hr class="my-4 border-light border-opacity-10">

                    <h6 class="text-white fw-bold mb-3">Database Credentials</h6>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="bg-dark p-3 rounded text-center">
                                <div class="text-secondary small fw-bold mb-1">System</div>
                                <div class="text-white">MySQL</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="bg-dark p-3 rounded text-center">
                                <div class="text-secondary small fw-bold mb-1">Server</div>
                                <div class="text-white font-monospace">mysql.tomweb.in</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="bg-dark p-3 rounded text-center">
                                <div class="text-secondary small fw-bold mb-1">Username</div>
                                <div class="text-white font-monospace">root</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.cursor-pointer { cursor: pointer; }
.hover-white { transition: color 0.2s; }
.hover-white:hover { color: #ffffff !important; }
</style>
