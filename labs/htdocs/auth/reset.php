<?php
require_once '../src/load.php';
use Auth\EmailAuth;

$token = $_GET['token'] ?? null;
if (!$token) header("Location: login.php");

if (isset($_POST['password'])) {
    $auth = new EmailAuth();
    if ($auth->resetPassword($token, $_POST['password'])) {
        header("Location: login.php?reset=success");
        exit;
    } else {
        $error = "Invalid or expired token.";
    }
}

Session::$pageTitle = "New Password | Tom Labs";
ob_start();
?>
<div class="min-vh-100 d-flex align-items-center" style="background-color: #ebedef;">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-5">
                <div class="card shadow-lg rounded-4 border-0" style="background-color: #181924;">
                    <div class="card-body p-5">
                        <h1 class="text-white fw-bold">New Password</h1>
                        <p class="text-white-50 mb-4">Choose a strong enterprise password.</p>

                        <?php if (isset($error)): ?>
                        <div class="alert alert-danger border-0 text-white bg-danger"><?= $error ?></div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-4">
                                <label class="form-label small fw-bold text-white-50">NEW PASSWORD</label>
                                <input class="form-control form-control-lg border-0 text-white" type="password"
                                    name="password" required style="background-color: #2a2b36;">
                            </div>
                            <button class="btn btn-lg w-100 text-white fw-bold" type="submit"
                                style="background-color: #3c4b64;">UPDATE PASSWORD</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
Session::set('page_content', ob_get_clean());
Session::loadMaster();