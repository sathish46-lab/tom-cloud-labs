<?php
session_start();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = new SQLite3('db.sqlite');
    
    $username = $_POST['username'];
    $password = $_POST['password'];

    $query = "SELECT * FROM users WHERE username = '$username' AND password = '$password'";
    $result = $db->query($query);
    
    if ($result && $row = $result->fetchArray()) {
        $_SESSION['loggedin'] = true;
        
        // ==========================================================
        // ✅ START OF FIX
        // ==========================================================
        // Redirect to the *local* admin page, not the main website
        header('Location: /admin.php');
        // ==========================================================
        // ✅ END OF FIX
        // ==========================================================
        
        exit;
    } else {
        $error = 'Invalid username or password.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CTF Challenge: Log In</title>
    <style>
        body { background-color: #091723; color: #cdd6f4; font-family: 'Inter', sans-serif; display: grid; place-items: center; min-height: 100vh; }
        .login-box { background: #1e1e2e; padding: 2rem; border-radius: 8px; border: 1px solid #313244; }
        h2 { text-align: center; margin-top: 0; }
        form { display: flex; flex-direction: column; gap: 1rem; }
        input { padding: 0.5rem; border: 1px solid #494d64; background: #1e1e2e; color: #cdd6f4; border-radius: 4px; }
        button { padding: 0.75rem; background: #0f9ff8; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .error { color: #f38ba8; text-align: center; }
        .hint { color: #7f849c; font-size: 0.9em; text-align: center; margin-top: 1rem; }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>Admin Login</h2>
        <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <form action="/" method="POST">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Log In</button>
        </form>
        <p class="hint">Hint: Only the 'admin' user can log in.</p>
    </div>
</body>
</html>