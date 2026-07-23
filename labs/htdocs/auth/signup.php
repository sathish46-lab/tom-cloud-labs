<?php
require_once '../src/load.php';

// Force full page reload if accessed via HTMX
if (isset($_SERVER['HTTP_HX_REQUEST']) && $_SERVER['HTTP_HX_REQUEST'] == 'true') {
    header('HX-Redirect: /signup');
    exit;
}

use Auth\GoogleAuth;
use Auth\Mailer;

// 1. Initialize Google Auth
$google = new GoogleAuth();
$metadata = http(get_config('google_oauth')['metadata_url']);
$google_url = $google->getAuthUrl($metadata);

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'], $_POST['password'])) {
    $db = DatabaseConnection::getDefaultDatabase();
    $email = trim(strtolower($_POST['email']));
    $username = trim($_POST['username']);
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');

    $existingUser = $db->users->findOne(['$or' => [['email' => $email], ['username' => $username]]]);
    
    if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
        $error = "Username must be 3-20 characters and contain only letters, numbers, and underscores.";
    } elseif ($existingUser) {
        $error = "Username or Email already exists.";
    } else {
        $token = bin2hex(random_bytes(32));
        $db->users->insertOne([
            'user_id' => DatabaseConnection::getNextSequence("userid"),
            'username' => $username,
            'email' => $email,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'password' => password_hash($_POST['password'], PASSWORD_DEFAULT),
            'verification_token' => $token,
            'is_verified' => false,
            'state' => 'pending',
            'created_at' => time(),
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'theme_preferences' => [
                'mode' => 'spiderman',
                'plain_color' => '#010d12',
                'accent_color' => '#8b91f9',
                'custom_slots' => [],
                'custom_themes' => []
            ]
        ]);

        try {
            if (\Auth\Mailer::sendVerification($email, $username, $token)) {
                $success = "Registration successful! Please check your email.";
            } else {
                $error = "Account created but verification email failed. Please contact support.";
            }
        } catch (\Throwable $e) {
            error_log('Signup mail error: ' . $e->getMessage());
            $error = "Account created but verification email failed. Please contact support.";
        }
    }
}

define('IS_LOGIN_PAGE', true);
Session::$pageTitle = "Register";
Session::set('seo_description', 'Create a free Tom Labs account to start deploying your own automated lab environments today.');
Session::set('seo_keywords', 'Register, Sign Up, Tom Labs, Create Account');
?>
<!DOCTYPE html>
<html lang="en" data-coreui-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <meta name="description" content="<?= Session::get('seo_description') ?>">
    <meta name="keywords" content="<?= Session::get('seo_keywords') ?>">
    <title><?= Session::$pageTitle ?></title>
    <link rel="icon" type="image/png" href="<?= Session::cdn3('logo/favicon.png') ?>">
    
    <!-- CoreUI & Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/@coreui/coreui@5.0.2/dist/css/coreui.min.css" rel="stylesheet">
    
    <!-- Fonts and Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href='https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    
    <style>
        body { 
            margin: 0; padding: 0; 
            background-color: #0f172a; 
            color: white; 
            font-family: 'Ubuntu', sans-serif; 
            overflow-x: hidden;
            background-image: radial-gradient(circle at top right, rgba(56, 189, 248, 0.15), transparent 500px),
                              radial-gradient(circle at bottom left, rgba(251, 146, 60, 0.15), transparent 500px);
        }
        a { text-decoration: none; }
    </style>
</head>
<body>



<div class="min-vh-100 d-flex align-items-center">
    <div class="container">
        <div class="row justify-content-center align-items-center">
            
            <div class="col-lg-5 d-none d-lg-block pe-lg-5 text-white" style="text-shadow: 0 2px 10px rgba(0,0,0,0.5);">
                <img src="<?= Session::cdn3('logo/logo.png') ?>" width="80" class="mb-4" alt="Logo" style="filter: drop-shadow(0 0 10px rgba(56,189,248,0.5));">
                <h1 id="animated-heading" class="display-4 fw-bolder mb-4" style="line-height: 1.1; letter-spacing: -1px; transition: opacity 0.5s ease-in-out;">
                    Join the <span style="color: #fb923c;">Hub.</span><br>
                    Ignite <span style="color: #38bdf8;">Innovation.</span>
                </h1>
                
                <div class="p-4 rounded-4" style="background: rgba(0, 0, 0, 0.4); border: 1px solid rgba(255, 255, 255, 0.1); backdrop-filter: blur(10px); box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
                    <i class='bx bxs-quote-alt-left fs-2 mb-3 opacity-75' style="color: #fb923c;"></i>
                    <p class="fs-5 fst-italic text-white mb-4" style="line-height: 1.6; text-shadow: 0 1px 2px rgba(0,0,0,0.8);">
                        "The learning environment provided here is unparalleled. It empowers true engineers to securely test, break, and build the systems of tomorrow."
                    </p>
                    <a href="#" onclick="event.preventDefault(); window.open('https://sathish46.in', '_blank'); setTimeout(() => { window.open('https://sathish46.selfmade.fun', '_blank'); }, 800);" class="d-flex align-items-center gap-3 text-decoration-none text-light" style="transition: opacity 0.2s;" onmouseover="this.style.opacity='0.8'" onmouseout="this.style.opacity='1'">
                        <img src="<?= Session::cdn3('author/sathish.png') ?>" width="50" height="50" class="rounded-circle border border-2" style="border-color: #9ca3af; object-fit: cover;" alt="Avatar">
                        <div>
                            <div class="fw-bold fs-6 text-white" style="text-shadow: 0 1px 2px rgba(0,0,0,0.8);">Sathish46 🔥</div>
                            <div class="small" style="color: rgba(255, 255, 255, 0.9); text-shadow: 0 1px 2px rgba(0,0,0,0.8);">CEO & Founder, Tom Labs 😎</div>
                        </div>
                    </a>
                </div>
            </div>

            <script>
                document.addEventListener("DOMContentLoaded", function() {
                    const headings = [
                        { line1: 'Join the <span style="color: #fb923c;">Hub.</span>', line2: 'Ignite <span style="color: #38bdf8;">Innovation.</span>' },
                        { line1: 'Empower <span style="color: #fb923c;">Engineers.</span>', line2: 'Build the <span style="color: #38bdf8;">Future.</span>' },
                        { line1: 'Master <span style="color: #fb923c;">DevOps.</span>', line2: 'Deploy <span style="color: #38bdf8;">Securely.</span>' }
                    ];
                    let currentIndex = 0;
                    const headingBlock = document.getElementById("animated-heading");
                    
                    setInterval(() => {
                        headingBlock.style.opacity = 0;
                        setTimeout(() => {
                            currentIndex = (currentIndex + 1) % headings.length;
                            headingBlock.innerHTML = headings[currentIndex].line1 + '<br>' + headings[currentIndex].line2;
                            headingBlock.style.opacity = 1;
                        }, 500);
                    }, 3000);
                });
            </script>

            <div class="col-md-8 col-lg-4">
                <div class="card shadow-lg border-0 rounded-4 p-4">
                    <div class="card-body">
                        <h3 class="fw-bold mb-4 text-body">Create account</h3>

                        <?php if ($error): ?>
                            <div class="alert alert-danger d-flex align-items-center p-2 border-0 small mb-4" 
                                 style="background-color: rgba(248,81,73,0.1); color: #ff7b72;">
                                <i class="bx bx-error-circle me-2 fs-5"></i>
                                <div><?= $error ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="text-center py-4">
                                <div class="avatar avatar-xl bg-success-subtle text-success mb-3">
                                    <i class="bx bx-envelope fs-1"></i>
                                </div>
                                <h5 class="fw-bold text-success"><?= $success ?></h5>
                                <p class="small text-secondary px-3">We have sent a link to your email to verify your account.</p>
                                <a href="/signin" class="btn btn-warning rounded-pill px-4 mt-3">Back to Sign In</a>
                            </div>
                        <?php else: ?>
                            <form method="POST">
                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <label class="small fw-bold text-secondary mb-1">FIRST NAME</label>
                                        <div class="input-group">
                                            <input type="text" name="first_name" class="form-control" placeholder="Tom" required>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <label class="small fw-bold text-secondary mb-1">LAST NAME</label>
                                        <div class="input-group">
                                            <input type="text" name="last_name" class="form-control" placeholder="Lab">
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="small fw-bold text-secondary mb-1">USERNAME</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-transparent border-end-0">
                                            <i class="bx bx-user"></i>
                                        </span>
                                        <input type="text" name="username" class="form-control border-start-0 ps-0" placeholder="TomLab" required>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="small fw-bold text-secondary mb-1">EMAIL ADDRESS</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-transparent border-end-0">
                                            <i class="bx bx-envelope"></i>
                                        </span>
                                        <input type="email" name="email" class="form-control border-start-0 ps-0" required>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label class="small fw-bold text-secondary mb-1">PASSWORD</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-transparent border-end-0">
                                            <i class="bx bx-lock-alt"></i>
                                        </span>
                                        <input type="password" name="password" class="form-control border-start-0 ps-0" required>
                                    </div>
                                    <div class="mt-2 text-secondary" style="font-size: 0.7rem;">
                                        Use at least 8 characters with a mix of letters and numbers.
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-warning btn-lg w-100 mb-3 fw-bold">
                                    Create account
                                </button>
                            </form>

                            <div class="d-flex align-items-center my-4">
                                <hr class="flex-grow-1 opacity-25">
                                <span class="mx-3 small text-secondary">or sign up with</span>
                                <hr class="flex-grow-1 opacity-25">
                            </div>

                            <a href="<?= $google_url ?>" 
                               class="btn btn-outline-secondary btn-lg w-100 d-flex align-items-center justify-content-center fw-semibold">
                                <img src="https://www.gstatic.com/images/branding/product/1x/gsa_512dp.png" width="18" class="me-2" alt="Google Logo">
                                Google
                            </a>
                        <?php endif; ?>

                        <p class="text-center mt-4 mb-0 small text-secondary">
                            Already have an account?
                            <a href="/signin" class="text-warning fw-bold text-decoration-none">
                                Sign in
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>