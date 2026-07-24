<?php
define('IS_LOGIN_PAGE', true);
require_once '../src/load.php';
use Auth\EmailAuth;

$error = null;
$success = null;

if (isset($_POST['email'])) {
    // CSRF validation
    if (!Session::validateCsrf($_POST['_csrf_token'] ?? '')) {
        $error = "Invalid security token. Please try again.";
    } else {
        $email = trim($_POST['email']);
        $auth = new EmailAuth();
        $token = $auth->requestReset($email);
        $success = "If an account exists with that email or username, password reset instructions have been generated.";
    }
}

Session::$pageTitle = "Forgot Password | Tom Labs";
?>
<!DOCTYPE html>
<html lang="en" data-coreui-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <title><?= Session::$pageTitle ?></title>
    <link rel="icon" type="image/png" href="<?= Session::cdn3('logo/favicon.png') ?>">
    <link href="https://cdn.jsdelivr.net/npm/@coreui/coreui@5.0.2/dist/css/coreui.min.css" rel="stylesheet">
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
<body data-no-boost="true" hx-boost="false">

<div class="min-vh-100 d-flex align-items-center justify-content-center py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-7 col-lg-5">
                <div class="card shadow-lg border-0 rounded-4 p-4" style="background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.1) !important;">
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <img src="<?= Session::cdn3('logo/logo.png') ?>" width="60" class="mb-3" alt="Logo">
                            <h3 class="fw-bold mb-2 text-white">Reset Access</h3>
                            <p class="text-secondary small mb-0">Enter your email or username to receive a secure reset link.</p>
                        </div>

                        <?php if (isset($success)): ?>
                        <div class="alert alert-success border-0 text-white bg-success bg-opacity-75 rounded-3 py-3 small mb-4">
                            <i class='bx bx-check-circle me-1'></i> <?= htmlspecialchars($success) ?>
                        </div>
                        <?php endif; ?>

                        <?php if (isset($error)): ?>
                        <div class="alert alert-danger border-0 text-white bg-danger bg-opacity-75 rounded-3 py-3 small mb-4">
                            <i class='bx bx-error-circle me-1'></i> <?= htmlspecialchars($error) ?>
                        </div>
                        <?php endif; ?>

                        <form method="POST">
                            <?= Session::csrfField() ?>
                            <div class="mb-4">
                                <label class="small fw-bold text-secondary mb-1">EMAIL OR USERNAME</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-transparent border-end-0 text-secondary">
                                        <i class="bx bx-envelope"></i>
                                    </span>
                                    <input class="form-control border-start-0 ps-0 text-white" type="text" name="email" required placeholder="name@company.com" style="background: transparent;">
                                </div>
                            </div>
                            <button class="btn btn-warning btn-lg w-100 fw-bold py-3 shadow-sm rounded-3" type="submit" style="background: linear-gradient(135deg, #fb923c, #f97316); border: none; color: #fff;">
                                <span>SEND RESET LINK</span>
                            </button>
                        </form>
                        <div class="text-center mt-4 pt-2 border-top border-secondary border-opacity-25">
                            <a href="/signin" data-no-boost="true" class="small text-warning fw-bold text-decoration-none d-inline-flex align-items-center gap-1">
                                <i class='bx bx-left-arrow-alt fs-5'></i> Back to Sign In
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>