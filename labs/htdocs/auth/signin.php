<?php
require_once '../src/load.php';
use Auth\GoogleAuth;

$google = new GoogleAuth();
$metadata = http(get_config('google_oauth')['metadata_url']);

// Handle Google Callback
if (isset($_GET['code'])) {
    $state = $_GET['state'] ?? null;
    $user = $google->handleCallback($metadata, $_GET['code'], $state);
    if ($user) {
        $_SESSION['username'] = $user['username'];
        $_SESSION['auth_status'] = Constants::STATUS_LOGGEDIN;
        header('Location: /home');
        exit;
    } else {
        header('Location: /finish-signup');
        exit;
    }
}

// Handle Standard Login
$show2faForm = false;
if (isset($_POST['email']) && isset($_POST['password'])) {
    $authResult = UserSession::authenticate($_POST['email'], $_POST['password']);
    if ($authResult === "2fa_required") {
        $show2faForm = true;
    } elseif ($authResult === true) {
        header("Location: /home");
        exit;
    } else {
        $loginError = Session::get('login_error') ?: "Invalid credentials";
    }
}

$google_url = $google->getAuthUrl($metadata);
define('IS_LOGIN_PAGE', true);
Session::$pageTitle = "Sign In";
Session::set('seo_description', 'Sign in to Tom Labs to access your isolated environments and deploy scalable database services.');
Session::set('seo_keywords', 'Sign In, Tom Labs Login, Virtual Labs, Secure Access');
ob_start();
?>


<div class="min-vh-100 d-flex align-items-center">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-5 d-none d-lg-block pe-lg-5">
                <img src="<?= Session::cdn3('/logo/logo.png') ?>" width="60" class="mb-4" alt="Logo">
                <h1 class="display-5 fw-bold mb-3 text-body">Tom Labs Infrastructure</h1>
                <p class=" text-body-secondary">
                    Selfmade Ninja Academy inspired virtual lab system.
                    Access your isolated environments, manage domains, and deploy
                    scalable database services with a single click.
                </p>
            </div>

            <div class="col-md-7 col-lg-4">
                <div class="card shadow-lg border-0 rounded-4 p-4">
                    <div class="card-body">
                        <div class="text-center mb-4 d-lg-none">
                            <img src="/assets/logo/logo.png" width="50" class="mb-3" alt="Tom Labs Logo">
                        </div>
                        
                        <h3 class="fw-bold mb-4 text-body">Sign in</h3>

                        <?php if (isset($loginError)): ?>
                            <div class="alert alert-danger small py-2"><?= htmlspecialchars($loginError) ?></div>
                        <?php endif; ?>

                        <?php if ($show2faForm): ?>
                            <!-- 2FA OTP Form -->
                            <div class="text-center mb-4">
                                <div class="d-inline-flex align-items-center justify-content-center bg-warning bg-opacity-10 text-warning rounded-circle mb-3" style="width:60px; height:60px;">
                                    <i class='bx bx-check-shield fs-1'></i>
                                </div>
                                <h5 class="fw-bold">Two-Factor Authentication</h5>
                                <p class="small text-secondary mb-0">A 6-digit code has been sent to your email.</p>
                                <span class="badge bg-danger rounded-pill mt-2 fs-6 px-3" id="login-timer-2fa">01:00</span>
                            </div>
                            
                            <form id="login-2fa-form">
                                <div class="mb-4">
                                    <input type="text" name="otp" id="login-otp-input" class="form-control form-control-lg text-center fw-bold fs-3 tracking-widest" maxlength="6" placeholder="------" required autocomplete="off" style="letter-spacing: 10px;">
                                </div>
                                <div id="login-2fa-error" class="text-danger small text-center mb-3 d-none fw-bold"></div>
                                <button type="submit" id="login-verify-btn" class="btn btn-warning btn-lg w-100 fw-bold">Verify & Sign In</button>
                            </form>
                            
                            <script>
                                document.addEventListener('DOMContentLoaded', () => {
                                    const timerDisplay = document.getElementById('login-timer-2fa');
                                    const form = document.getElementById('login-2fa-form');
                                    const errorDiv = document.getElementById('login-2fa-error');
                                    const verifyBtn = document.getElementById('login-verify-btn');
                                    const inputOtp = document.getElementById('login-otp-input');
                                    
                                    inputOtp.focus();
                                    
                                    let timeLeft = 60;
                                    const countdown = setInterval(() => {
                                        timeLeft--;
                                        let secs = timeLeft < 10 ? '0' + timeLeft : timeLeft;
                                        timerDisplay.innerText = "00:" + secs;
                                        if (timeLeft <= 0) {
                                            clearInterval(countdown);
                                            inputOtp.disabled = true;
                                            verifyBtn.disabled = true;
                                            errorDiv.innerText = "OTP Expired. Please refresh to try again.";
                                            errorDiv.classList.remove('d-none');
                                        }
                                    }, 1000);
                                    
                                    form.onsubmit = async (e) => {
                                        e.preventDefault();
                                        verifyBtn.disabled = true;
                                        verifyBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Checking...';
                                        errorDiv.classList.add('d-none');
                                        
                                        const formData = new FormData(form);
                                        try {
                                            const res = await fetch('/api/auth/verify_login_2fa', { method: 'POST', body: formData });
                                            const result = await res.json();
                                            if (result.status === 'success') {
                                                clearInterval(countdown);
                                                timerDisplay.classList.replace('bg-danger', 'bg-success');
                                                timerDisplay.innerText = "Verified!";
                                                window.location.href = '/home';
                                            } else {
                                                errorDiv.innerText = result.error;
                                                errorDiv.classList.remove('d-none');
                                                verifyBtn.disabled = false;
                                                verifyBtn.innerText = 'Verify & Sign In';
                                            }
                                        } catch (err) {
                                            errorDiv.innerText = "Server Error.";
                                            errorDiv.classList.remove('d-none');
                                            verifyBtn.disabled = false;
                                            verifyBtn.innerText = 'Verify & Sign In';
                                        }
                                    };
                                });
                            </script>
                        <?php else: ?>
                            <!-- Standard Login Form -->
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="small fw-bold text-secondary mb-1">USERNAME OR EMAIL</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-transparent border-end-0">
                                            <i class="bx bx-user"></i>
                                        </span>
                                        <input type="text" name="email" class="form-control border-start-0 ps-0" required>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <div class="d-flex justify-content-between">
                                        <label class="small fw-bold text-secondary mb-1">PASSWORD</label>
                                        <a href="#" class="small text-warning text-decoration-none">Forgot?</a>
                                    </div>
                                    <div class="input-group">
                                        <span class="input-group-text bg-transparent border-end-0">
                                            <i class="bx bx-lock-alt"></i>
                                        </span>
                                        <input type="password" name="password" class="form-control border-start-0 ps-0" required>
                                    </div>
                                </div>

                                <div class="form-check form-switch mb-4">
                                    <input class="form-check-input" type="checkbox" id="remember">
                                    <label class="form-check-label small text-warning" for="remember">Remember me</label>
                                </div>

                                <button type="submit" class="btn btn-warning btn-lg w-100 mb-3 fw-bold">
                                    Sign in
                                </button>
                            </form>
                        <?php endif; ?>

                        <div class="d-flex align-items-center my-4">
                            <hr class="flex-grow-1 opacity-25">
                            <span class="mx-3 small text-secondary">or sign in with</span>
                            <hr class="flex-grow-1 opacity-25">
                        </div>

                        <a href="<?= $google_url ?>" class="btn btn-outline-secondary btn-lg w-100 d-flex align-items-center justify-content-center fw-semibold">
                            <img src="https://www.gstatic.com/images/branding/product/1x/gsa_512dp.png" width="18" class="me-2" alt="Google Logo">
                            Google
                        </a>

                        <p class="text-center mt-4 mb-0 small text-secondary">
                            Don't have an account? <a href="/signup" class="text-warning fw-bold text-decoration-none">Register now</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
Session::set('page_content', ob_get_clean());
Session::loadMaster();