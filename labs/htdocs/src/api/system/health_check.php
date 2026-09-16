<?php
require_once __DIR__ . '/../../load.php';
require_once __DIR__ . '/../../lib/core/HealthCheck.class.php';

// Health check endpoint — accessible to authenticated users
$user = AuthMiddleware::requireAuth();

HealthCheck::jsonResponse();
