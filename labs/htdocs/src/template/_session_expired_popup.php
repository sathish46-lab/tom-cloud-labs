<?php if (Session::get('show_session_expired')): ?>
<!-- Standalone Full Page Session Expired View (Plain Login Theme) -->
<link href="https://cdn.jsdelivr.net/npm/@coreui/coreui@5.0.2/dist/css/coreui.min.css" rel="stylesheet">
<link href='https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
<style>
    body {
        margin: 0 !important;
        padding: 0 !important;
        background-color: #0f172a !important;
        color: white !important;
        font-family: 'Ubuntu', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
        overflow-x: hidden !important;
        background-image: radial-gradient(circle at top right, rgba(56, 189, 248, 0.15), transparent 500px),
                          radial-gradient(circle at bottom left, rgba(251, 146, 60, 0.15), transparent 500px) !important;
    }
    /* Hide any leftover loading indicators or progress bars from HTMX */
    #htmx-top-progress, .htmx-indicator { display: none !important; }
</style>

<div class="min-vh-100 w-100 d-flex align-items-center justify-content-center py-5" data-no-boost="true" hx-boost="false">
    <div class="container">
        <div class="row justify-content-center align-items-center g-5">
            <!-- Left Column: Branding & Value Proposition -->
            <div class="col-lg-6 d-none d-lg-block pe-lg-4 text-white" style="text-shadow: 0 2px 10px rgba(0,0,0,0.5);">
                <img src="<?= Session::cdn3('logo/logo.png') ?>" width="80" class="mb-4" alt="Logo" style="filter: drop-shadow(0 0 10px rgba(15,159,248,0.5));">
                <h1 class="display-4 fw-bolder mb-4" style="line-height: 1.1; letter-spacing: -1px;">
                    Session <span style="color: #fb923c;">Expired.</span><br>
                    Security <span style="color: #38bdf8;">Active.</span>
                </h1>
                
                <div class="p-4 rounded-4" style="background: rgba(0, 0, 0, 0.4); border: 1px solid rgba(255, 255, 255, 0.1); backdrop-filter: blur(10px); box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
                    <i class='bx bxs-shield-check fs-2 mb-3 opacity-75' style="color: #38bdf8;"></i>
                    <p class="fs-5 text-white mb-0" style="line-height: 1.6; text-shadow: 0 1px 2px rgba(0,0,0,0.8);">
                        Your secure session has timed out to protect your cloud credentials and running laboratory instances. Please authenticate again to resume your work.
                    </p>
                </div>
            </div>

            <!-- Right Column: Card Action -->
            <div class="col-md-8 col-lg-5">
                <div class="card shadow-lg border-0 rounded-4 p-4 text-white" style="background: rgba(30, 41, 59, 0.85); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.1) !important; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5) !important;">
                    <div class="card-body text-center p-3">
                        <div class="mb-4">
                            <div class="d-inline-flex align-items-center justify-content-center bg-warning bg-opacity-10 text-warning rounded-circle mb-3" style="width: 80px; height: 80px; border: 1px solid rgba(251, 146, 60, 0.3);">
                                <i class='bx bxs-lock-open-alt bx-tada display-4' style="color: #fb923c;"></i>
                            </div>
                            <h3 class="fw-bold mb-2 text-white">Authentication Required</h3>
                            <p class="small text-secondary mb-0">You must be signed in to access this laboratory endpoint.</p>
                        </div>

                        <div class="d-grid gap-3 mt-4 pt-2">
                            <a href="/signin" data-no-boost="true" rel="external" class="btn btn-warning btn-lg fw-bold d-flex align-items-center justify-content-center gap-2 py-3 shadow-sm rounded-3" style="background: linear-gradient(135deg, #fb923c, #f97316); border: none; color: #fff; text-decoration: none;">
                                <i class='bx bx-log-in-circle fs-4'></i>
                                <span>Sign In to Continue</span>
                            </a>
                            <a href="/" data-no-boost="true" rel="external" class="btn btn-outline-light btn-lg fw-bold d-flex align-items-center justify-content-center gap-2 py-3 rounded-3" style="border-color: rgba(255,255,255,0.2); text-decoration: none;">
                                <i class='bx bx-home-alt fs-5'></i>
                                <span>Return to Lobby</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>
