<?php
require_once '../src/load.php';

// Prevent direct access without a Google session
if (!isset($_SESSION['pending_user'])) {
    header("Location: /signin");
    exit;
}

// Prevent BFCache and caching
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

$error = null;
$pending = $_SESSION['pending_user'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'])) {
    // CSRF validation
    if (!Session::validateCsrf($_POST['_csrf_token'] ?? '')) {
        $error = "Invalid security token. Please try again.";
    } else {
        $db = DatabaseConnection::getDefaultDatabase();
        $username = trim($_POST['username']);

        // Username validation (same rules as signup)
        if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
            $error = "Username must be 3-20 characters and contain only letters, numbers, and underscores.";
        } else {
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
                    'is_verified'   => true,
                    'state'         => 'active',
                    'created_at'    => time(),
                    'last_login'    => time(),
                    'ip_address'    => $_SERVER['REMOTE_ADDR'],
                    'theme_preferences' => [
                        'mode' => 'spiderman',
                        'plain_color' => '#010d12',
                        'accent_color' => '#8b91f9',
                        'custom_slots' => [],
                        'custom_themes' => []
                    ]
                ]);

                // Clean up and Log in
                unset($_SESSION['pending_user']);
                session_regenerate_id(true);
                $_SESSION['username'] = $username;
                $_SESSION['auth_status'] = Constants::STATUS_LOGGEDIN;
                
                header("Location: /home");
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-coreui-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <title>Choose Username — Tom Labs</title>
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
<body data-no-boost="true" hx-boost="false">

<div class="min-vh-100 d-flex align-items-center">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-5 d-none d-lg-block pe-lg-5 text-white" style="text-shadow: 0 2px 10px rgba(0,0,0,0.5);">
                <img src="<?= Session::cdn3('logo/logo.png') ?>" width="80" class="mb-4" alt="Logo" style="filter: drop-shadow(0 0 10px rgba(15,159,248,0.5));">
                <h1 class="display-4 fw-bolder mb-4" style="line-height: 1.1; letter-spacing: -1px;">
                    Almost <span style="color: #fb923c;">There!</span><br>
                    Choose Your <span style="color: #38bdf8;">Identity.</span>
                </h1>
                
                <div class="p-4 rounded-4" style="background: rgba(0, 0, 0, 0.4); border: 1px solid rgba(255, 255, 255, 0.1); backdrop-filter: blur(10px); box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
                    <i class='bx bxs-quote-alt-left fs-2 mb-3 opacity-75' style="color: #fb923c;"></i>
                    <p class="fs-5 fst-italic text-white mb-4" style="line-height: 1.6; text-shadow: 0 1px 2px rgba(0,0,0,0.8);">
                        "Your username is your identity in Tom Labs. Pick something that represents you."
                    </p>
                    <a href="#" class="d-flex align-items-center gap-3 text-decoration-none text-light" style="transition: opacity 0.2s;" onmouseover="this.style.opacity='0.8'" onmouseout="this.style.opacity='1'">
                        <img src="<?= Session::cdn3('author/sathish.png') ?>" width="50" height="50" class="rounded-circle border border-2" style="border-color: #9ca3af; object-fit: cover;" alt="Avatar">
                        <div>
                            <div class="fw-bold fs-6 text-white" style="text-shadow: 0 1px 2px rgba(0,0,0,0.8);">Sathish46</div>
                            <div class="small" style="color: rgba(255, 255, 255, 0.9); text-shadow: 0 1px 2px rgba(0,0,0,0.8);">CEO & Founder, Tom Labs</div>
                        </div>
                    </a>
                </div>
            </div>

            <div class="col-md-7 col-lg-4">
                <div class="card shadow-lg border-0 rounded-4 p-4">
                    <div class="card-body">
                        <div class="text-center mb-4 d-lg-none">
                            <img src="<?= Session::cdn3('logo/logo.png') ?>" width="50" class="mb-3" alt="Tom Labs Logo">
                        </div>

                        <div class="text-center mb-4">
                            <img src="<?= htmlspecialchars($pending['avatar']) ?>" class="rounded-circle shadow mb-3" width="80" alt="Avatar">
                        </div>
                        
                        <h3 class="fw-bold mb-1 text-body text-center">Almost There!</h3>
                        <p class="small text-secondary text-center mb-4">Choose a unique username for your lab profile.</p>

                        <?php if ($error): ?>
                            <div class="alert alert-danger small py-2"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>

                        <form method="POST">
                            <?= Session::csrfField() ?>
                            <div class="mb-4">
                                <label class="small fw-bold text-secondary mb-1">USERNAME</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-transparent border-end-0">
                                        <i class="bx bx-user"></i>
                                    </span>
                                    <input type="text" name="username" class="form-control border-start-0 ps-0" 
                                           placeholder="e.g. tomlab" required autocomplete="off">
                                </div>
                            </div>

                            <button type="submit" class="btn btn-warning btn-lg w-100 mb-3 fw-bold">
                                Complete Registration
                            </button>
                        </form>

                        <p class="text-center mt-3 mb-0 small text-secondary">
                            <a href="/signin" class="text-warning fw-bold text-decoration-none">Back to Sign In</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
