<?php
require_once '../src/load.php';

// Prevent direct access without a Google session
if (!isset($_SESSION['pending_user'])) {
    header("Location: /signin");
    exit;
}

$error = null;
$pending = $_SESSION['pending_user'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'])) {
    $db = DatabaseConnection::getDefaultDatabase();
    $username = trim($_POST['username']);

    // Check if username is taken
    $exists = $db->users->findOne(['username' => $username]);
    if ($exists) {
        $error = "That username is already taken.";
    } else {
        // Create full user record - Mark as verified immediately
        $db->users->insertOne([
            'user_id'       => DatabaseConnection::getNextSequence("userid"),
            'username'      => $username,
            'email'         => $pending['email'],
            'first_name'    => $pending['first_name'] ?? '',
            'last_name'     => $pending['last_name'] ?? '',
            'avatar_url'    => $pending['avatar'],
            'google_sub'    => $pending['sub'],
            'is_verified'   => true, // Professional Best Practice: Google users are verified
            'state'         => 'active',
            'created_at'    => time(),
            'last_login'    => time(),
            'ip_address'    => $_SERVER['REMOTE_ADDR']
        ]);

        // Clean up and Log in
        unset($_SESSION['pending_user']);
        $_SESSION['username'] = $username;
        $_SESSION['auth_status'] = Constants::STATUS_LOGGEDIN;
        
        header("Location: /home");
        exit;
    }
}

define('IS_LOGIN_PAGE', true);
Session::$pageTitle = "Choose Username";
ob_start();
?>
<div class="min-vh-100 d-flex align-items-center">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-5">
                <div class="card shadow-lg rounded-4 border-0 p-4" style="background-color: #181924;">
                    <div class="text-center mb-4">
                        <img src="<?= $pending['avatar'] ?>" class="rounded-circle shadow mb-3" width="80">
                        <h2 class="text-white fw-bold">Almost There!</h2>
                        <p class="text-white-50">Choose a unique username for your lab profile.</p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger border-0"><?= $error ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-4">
                            <label class="form-label small fw-bold text-white-50">USERNAME</label>
                            <input class="form-control border-0 text-white" type="text" name="username" 
                                   placeholder="e.g. ninja_dev" required style="background-color: #2a2b36;">
                        </div>
                        <button class="btn btn-lg w-100 text-white fw-bold" type="submit" style="background-color: #3c4b64;">
                            COMPLETE REGISTRATION
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
Session::set('page_content', ob_get_clean());
Session::loadMaster();