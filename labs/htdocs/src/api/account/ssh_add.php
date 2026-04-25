<?php
require_once __DIR__ . '/../../../src/load.php';

header('Content-Type: application/json');
if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    echo json_encode(['status' => 'error', 'error' => 'Unauthorized']); exit;
}

$user = Session::getUser();
$db = DatabaseConnection::getDefaultDatabase();

$title = trim($_POST['title'] ?? '');
$publicKey = trim($_POST['key'] ?? '');
$expiresAt = !empty($_POST['expiration_date']) ? strtotime($_POST['expiration_date']) : null;

// 1. Validation for standard SSH formats
if (!preg_match('/^(ssh-rsa|ssh-ed25519|ecdsa-sha2-nistp(256|384|521))/', $publicKey)) {
    echo json_encode(['status' => 'error', 'error' => 'Invalid SSH key format.']); exit;
}

// 2. Generate SHA256 Fingerprint for the table
$keyParts = explode(' ', $publicKey);
$fingerprint = 'SHA256:' . base64_encode(hash('sha256', base64_decode($keyParts[1]), true));

// 3. Save to MongoDB 'ssh_keys' collection
try {
    $db->ssh_keys->insertOne([
        'user_id'     => $user->getUserId(),
        'username'    => $user->getUsername(),
        'email'       => $user->getEmail(),
        'title'       => $title,
        'public_key'  => $publicKey,
        'fingerprint' => $fingerprint,
        'created_at'  => time(),
        'expires_at'  => $expiresAt
    ]);

    // 4. SYNC TRIGGER: Inject key into the container
    // This calls the 'syncuser' command we added to Lab.py
    shell_exec("sudo labsctl syncuser " . escapeshellarg($user->getUsername()));

    echo json_encode(['status' => 'success']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'error' => 'Database error.']);
}
