<?php
require_once '../src/load.php';
use Auth\GoogleAuth;

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
    if ($existingUser) {
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
            'ip_address' => $_SERVER['REMOTE_ADDR']
        ]);

        if (send_verification_email($email, $username, $token)) {
            $success = "Registration successful! Please check your email.";
        }
    }
}

define('IS_LOGIN_PAGE', true);
Session::$pageTitle = "Register";
ob_start();
?>



<div class="min-vh-100 d-flex align-items-center">
    <div class="container">
        <div class="row justify-content-center align-items-center">
            
            <div class="col-lg-5 d-none d-lg-block pe-lg-5">
                <img src="/assets/logo/logo.png" width="60" class="mb-4" alt="Logo">
                <h1 class="display-5 fw-bold mb-3 text-body">Join the Hub.</h1>
                <p class="lead text-secondary">
                    Create an account to start deploying your own automated
                    lab environments today. Join a community of modern engineers
                    at Tom Labs.
                </p>
            </div>

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
                                            <input type="text" name="first_name" class="form-control" placeholder="Jane" required>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <label class="small fw-bold text-secondary mb-1">LAST NAME</label>
                                        <div class="input-group">
                                            <input type="text" name="last_name" class="form-control" placeholder="Doe">
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="small fw-bold text-secondary mb-1">USERNAME</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-transparent border-end-0">
                                            <i class="bx bx-user"></i>
                                        </span>
                                        <input type="text" name="username" class="form-control border-start-0 ps-0" placeholder="janedoe" required>
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
                                <img src="https://www.gstatic.com/images/branding/product/1x/gsa_512dp.png" width="18" class="me-2">
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

<?php
Session::set('page_content', ob_get_clean());
Session::loadMaster();