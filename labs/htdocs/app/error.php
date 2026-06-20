<?php
require_once __DIR__ . '/../src/load.php';

// Force 404 status code so search engines know it's a real 404
http_response_code(404);

// This tells the frontend template to render the _error.php page
Session::set('error_exception', new Exception("The requested URL was not found on this server.", 404));
Session::loadErrorPage();
