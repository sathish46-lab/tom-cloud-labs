<?php
require_once '../src/load.php';

$token = $_GET['token'] ?? null;
$status = "error";
$message = "The verification link is invalid or has expired.";

if ($token) {
    $db = DatabaseConnection::getDefaultDatabase();
    
    // Find user by verification token
    $user = $db->users->findOne(['verification_token' => $token]);

    if ($user) {
        // Activate account and clear the token so it can't be used twice
        $db->users->updateOne(
            ['_id' => $user['_id']],
            ['$set' => [
                'is_verified' => true,
                'state' => 'active',
                'verification_token' => null 
            ]]
        );
        $status = "success";
        $message = "Your account has been verified! You can now sign in.";
    }
}

// Redirect back to signin with the result
header("Location: /signin?status=$status&msg=" . urlencode($message));
exit;