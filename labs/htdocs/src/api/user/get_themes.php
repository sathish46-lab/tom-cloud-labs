<?php
require_once '../../load.php';

// Endpoint is public: Themes are required for background rendering on auth pages
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/config/themes.php';

header('Content-Type: application/json');
echo json_encode($tomThemes);
