<?php
require_once '../../load.php';

// Only allow authenticated users
if (!Session::getUser()) {
    http_response_code(401);
    die(json_encode(['error' => 'Unauthorized']));
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/src/config/themes.php';

header('Content-Type: application/json');
echo json_encode($tomThemes);
