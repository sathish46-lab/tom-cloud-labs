<?php
// docker/ctf-web-01/admin.php

session_start();
// Protect this page
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: /index.php');
    exit;
}

// ==========================================================
// ✅ START OF BUG FIX 2: Read flag from file
// ==========================================================
$flag_file = '/var/www/html/flag.txt';
$flag = 'CTF{flag_not_found_contact_admin}'; // Default

if (file_exists($flag_file)) {
    $flag = trim(file_get_contents($flag_file));
}
// ==========================================================
// ✅ END OF BUG FIX 2
// ==========================================================

// Unset the session so they can't just re-visit this page
session_destroy();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CTF Challenge: Admin Panel</title>
    <style>
        body { background-color: #091723; color: #cdd6f4; font-family: 'Inter', sans-serif; display: grid; place-items: center; min-height: 100vh; text-align: center; }
        .flag-box { background: #1e1e2e; padding: 2rem; border-radius: 8px; border: 1px solid #313244; }
        h1 { color: #a6e3a1; }
        code { background: #111; padding: 0.5rem 1rem; border-radius: 4px; color: #fab387; display: inline-block; margin-top: 1rem; }
    </style>
</head>
<body>
    <div class="flag-box">
        <h1>Congratulations, Admin!</h1>
        <p>You bypassed the authentication. Here is your unique flag:</p>
        <code><?= htmlspecialchars($flag) ?></code>
        <p style="margin-top: 2rem; font-size: 0.9em; color: #7f849c;">Now, copy this flag and submit it on the main lab website.</p>
    </div>
</body>
</html>