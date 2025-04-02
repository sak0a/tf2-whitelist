<?php
// Start session
session_start();

// Include configuration
require_once 'config.php';

// Discord OAuth2 Configuration - pulling from config constants
$client_id = DISCORD_CLIENT_ID;
$client_secret = DISCORD_CLIENT_SECRET;
$redirect_uri = DISCORD_REDIRECT_URI;

// Generate state parameter to prevent CSRF
$state = bin2hex(random_bytes(16));
$_SESSION['discord_oauth_state'] = $state;

// Set required scopes
$scope = 'identify email';

// Build the authorization URL
$auth_url = 'https://discord.com/api/oauth2/authorize';
$auth_params = [
    'client_id' => $client_id,
    'redirect_uri' => $redirect_uri,
    'response_type' => 'code',
    'scope' => $scope,
    'state' => $state
];

// Log the authentication attempt
logMessage('discord_auth.log', "Discord authentication initiated. State: {$state}");

// Redirect to Discord's authorization page
header('Location: ' . $auth_url . '?' . http_build_query($auth_params));
exit;
?>