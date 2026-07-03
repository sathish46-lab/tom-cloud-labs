<?php
require_once __DIR__ . '/../src/load.php';

// Force 404 status code so search engines know it's a real 404
http_response_code(404);

// If the request is for a static asset or source map, don't load the heavy template
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
if (preg_match('#^/workspace/#', $requestUri) || preg_match('#\.(js|css|map|scss|png|jpg|jpeg|gif|ico|webp)$#i', $requestUri)) {
    echo "<!DOCTYPE HTML PUBLIC \"-//IETF//DTD HTML 2.0//EN\">\n<html><head>\n<title>404 Not Found</title>\n</head><body>\n<h1>Not Found</h1>\n<p>The requested URL was not found on this server.</p>\n</body></html>";
    exit;
}

// This tells the frontend template to render the _error.php page
Session::set('error_exception', new Exception("The requested URL was not found on this server.", 404));
Session::loadErrorPage();
