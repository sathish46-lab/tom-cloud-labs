<?php
require_once '../src/load.php';
use Auth\EmailAuth;

if (isset($_POST['email'])) {
    $auth = new EmailAuth();
    $token = $auth->requestReset($_POST['email']);
    // In production, send $token via PHPMailer here
    $success = "If the email exists, a reset link has been generated.";
}

Session::$pageTitle = "Forgot Password | Tom Labs";
ob_start();
?>
<div class="min-vh-100 d-flex align-items-center" style="background-color: #ebedef;">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-5">
                <div class="card shadow-lg rounded-4 border-0" style="background-color: #181924;">
                    <div class="card-body p-5">
                        <h1 class="text-white fw-bold">Reset Access</h1>
                        <p class="text-white-50 mb-4">Enter your email to receive a reset link.</p>

                        <?php if (isset($success)): ?>
                        <div class="alert alert-success border-0 text-white bg-success"><?= $success ?></div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-4">
                                <label class="form-label small fw-bold text-white-50">EMAIL ADDRESS</label>
                                <input class="form-control form-control-lg border-0 text-white" type="email"
                                    name="email" required style="background-color: #2a2b36;">
                            </div>
                            <button class="btn btn-lg w-100 text-white fw-bold" type="submit"
                                style="background-color: #3c4b64;">SEND LINK</button>
                        </form>
                        <div class="text-center mt-4">
                            <a href="login.php" class="small text-primary fw-bold text-decoration-none">Back to
                                Login</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
Session::set('page_content', ob_get_clean());
Session::loadMaster();