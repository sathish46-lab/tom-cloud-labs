<?php
/**
 * MCP OAuth 2.1 Authorization Endpoint
 * Validates the authorization request, creates a transaction, and redirects
 * the browser to the consent page at /mcp/consent?txn_id=...
 */

require_once __DIR__ . '/../../load.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get query parameters
$clientId = $_GET['client_id'] ?? ($_POST['client_id'] ?? '');
$redirectUri = $_GET['redirect_uri'] ?? ($_POST['redirect_uri'] ?? '');
$responseType = $_GET['response_type'] ?? '';
// Force friendly scope display for consent page, but preserve original for token
$scopeDisplay = 'openid profile email';
$scope = $_GET['scope'] ?? ($_POST['scope'] ?? 'labs:*');
$state = $_GET['state'] ?? ($_POST['state'] ?? '');
$codeChallenge = $_GET['code_challenge'] ?? ($_POST['code_challenge'] ?? '');
$codeChallengeMethod = $_GET['code_challenge_method'] ?? ($_POST['code_challenge_method'] ?? 'S256');

// Validate required parameters
if (empty($clientId) || empty($redirectUri) || $responseType !== 'code') {
    if (!empty($redirectUri)) {
        $errorUri = $redirectUri . '?' . http_build_query([
            'error' => 'invalid_request',
            'error_description' => 'Missing or invalid required parameters',
            'state' => $state
        ]);
        header('Location: ' . $errorUri);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'invalid_request', 'error_description' => 'Missing or invalid required parameters']);
    }
    exit;
}

// Validate client
require_once __DIR__ . '/../../lib/core/MCPOAuth.class.php';
$client = MCPOAuth::getClient($clientId);

if (!$client) {
    if (!empty($redirectUri)) {
        header('Location: ' . $redirectUri . '?' . http_build_query([
            'error' => 'invalid_client',
            'error_description' => 'Invalid client_id',
            'state' => $state
        ]));
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'invalid_client', 'error_description' => 'Invalid client_id']);
    }
    exit;
}

// Validate redirect URI
if (!MCPOAuth::validateRedirectUri($clientId, $redirectUri)) {
    if (!empty($redirectUri)) {
        header('Location: ' . $redirectUri . '?' . http_build_query([
            'error' => 'invalid_redirect_uri',
            'error_description' => 'Redirect URI does not match client registration',
            'state' => $state
        ]));
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'invalid_redirect_uri', 'error_description' => 'Redirect URI does not match client registration']);
    }
    exit;
}

// Validate PKCE
if (empty($codeChallenge)) {
    if (!empty($redirectUri)) {
        header('Location: ' . $redirectUri . '?' . http_build_query([
            'error' => 'invalid_request',
            'error_description' => 'code_challenge parameter is required',
            'state' => $state
        ]));
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'invalid_request', 'error_description' => 'code_challenge parameter is required']);
    }
    exit;
}

if ($codeChallengeMethod !== 'S256' && $codeChallengeMethod !== 'plain') {
    if (!empty($redirectUri)) {
        header('Location: ' . $redirectUri . '?' . http_build_query([
            'error' => 'invalid_request',
            'error_description' => 'Unsupported code_challenge_method',
            'state' => $state
        ]));
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'invalid_request', 'error_description' => 'Unsupported code_challenge_method']);
    }
    exit;
}

// Create a transaction and redirect to the consent page
$txnId = MCPOAuth::createTransaction(
    $clientId,
    $redirectUri,
    $scope,
    $state,
    $codeChallenge,
    $codeChallengeMethod
);

header('Content-Type: application/json');

$user = AuthMiddleware::requireAuth();

// Already logged in — go straight to consent
header('Location: /mcp/consent?txn_id=' . $txnId);
exit;
