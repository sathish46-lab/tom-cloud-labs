<?php
require_once __DIR__ . '/../src/load.php';

// Call the professional logout method
UserSession::logout();

// Redirect to landing page
header("Location: /");
exit;