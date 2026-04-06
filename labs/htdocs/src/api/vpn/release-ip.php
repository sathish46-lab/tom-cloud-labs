<?php
// /var/www/labs/htdocs/src/api/vpn/release-ip.php

require_once __DIR__ . '/../../../src/load.php';
use TomLabs\Labs\IPManager;

header('Content-Type: application/json');

// 1. Authentication Check
if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    // 2. Parse Input
    $input = json_decode(file_get_contents('php://input'), true);
    $ipToRemove = $input['ip'] ?? null;

    if (!$ipToRemove) {
        throw new Exception("IP address is required");
    }

    $user = Session::getUser();
    $email = $user->getEmail();

    // 3. Perform Release
    $ipManager = new IPManager();
    
    // release() returns a MongoDB UpdateResult or similar
    // We pass the IP and the user's EMAIL to ensure they own it
    $result = $ipManager->release($ipToRemove, $email);

    if ($result->getModifiedCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        // If 0 modified, either IP doesn't exist or doesn't belong to user
        // We can still consider it "success" (idempotent) or warn
        // But for UI feedback, let's say success if it's not there anymore, 
        // or strictly checking if it was actually reserved.
        // For safety, let's assume if match count was 0, it wasn't theirs.
        
        if ($result->getMatchedCount() === 0) {
             throw new Exception("IP not found or not owned by you.");
        }
        
        echo json_encode(['success' => true]);
    }

} catch (Exception $e) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
