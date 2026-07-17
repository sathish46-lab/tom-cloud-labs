<?php
require_once __DIR__ . '/../../load.php';

$type = $_GET['type'] ?? '';
$userId = $_GET['user_id'] ?? '';
$filename = $_GET['filename'] ?? '';

if (empty($type) || empty($userId) || empty($filename)) {
    http_response_code(400);
    exit('Bad Request');
}

// Security: Validate the type
if ($type !== 'avatar' && $type !== 'private') {
    http_response_code(400);
    exit('Invalid file type');
}

// Authorization check for private files
if ($type === 'private') {
    if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
        http_response_code(401);
        exit('Unauthorized');
    }
    
    $currentUser = Session::getUser();
    if ($currentUser->getUserId() !== $userId) {
        http_response_code(403);
        exit('Forbidden');
    }
}

// Define the MinIO object key based on type
$s3Path = "labassets/uploads/{$userId}/{$filename}";
if ($type === 'avatar') {
    $s3Path = "avatars/{$userId}/{$filename}";
}

try {
    $client = Storage::getClient();
    $config = get_config('s3');
    
    // Check if object exists and get metadata
    $result = $client->getObject([
        'Bucket' => $config['bucket'],
        'Key'    => $s3Path
    ]);
    
    // Set proper HTTP headers
    header('Content-Type: ' . $result['ContentType']);
    header('Content-Length: ' . $result['ContentLength']);
    
    if ($type === 'avatar') {
        // Avatars can be cached for a long time
        header('Cache-Control: public, max-age=86400');
    } else {
        // Private files shouldn't be heavily cached
        header('Cache-Control: private, max-age=0, must-revalidate');
    }
    
    // Stream the body directly to the output buffer
    echo $result['Body'];
    
} catch (Aws\S3\Exception\S3Exception $e) {
    if ($e->getStatusCode() === 404) {
        http_response_code(404);
        if ($type === 'avatar') {
            // Serve a default placeholder for avatars if not found
            header('Content-Type: image/png');
            readfile(__DIR__ . '/../../../assets/images/avatars/default.png');
            exit;
        }
        exit('File not found');
    }
    
    error_log("MinIO GetObject Error: " . $e->getMessage());
    http_response_code(500);
    exit('Internal Server Error');
}
