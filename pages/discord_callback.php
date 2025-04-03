<?php
// Start session
session_start();

// Include configuration
require_once 'config.php';

// Discord OAuth2 Configuration - using constants from config
$client_id = DISCORD_CLIENT_ID;
$client_secret = DISCORD_CLIENT_SECRET;
$redirect_uri = DISCORD_REDIRECT_URI;

// Verify state parameter to prevent CSRF
if (!isset($_GET['state']) || $_GET['state'] !== $_SESSION['discord_oauth_state']) {
    $_SESSION['error_message'] = 'Invalid state parameter. Authentication failed.';
    logMessage('discord_auth.log', "Discord authentication failed: Invalid state parameter");
    header('Location: /');
    exit;
}

// Check if the authorization code is present
if (!isset($_GET['code'])) {
    $_SESSION['error_message'] = 'Authorization code not found.';
    logMessage('discord_auth.log', "Discord authentication failed: No code provided");
    header('Location: index.php');
    exit;
}

$code = $_GET['code'];

// Exchange the authorization code for an access token
$token_url = 'https://discord.com/api/oauth2/token';
$token_data = [
    'client_id' => $client_id,
    'client_secret' => $client_secret,
    'grant_type' => 'authorization_code',
    'code' => $code,
    'redirect_uri' => $redirect_uri,
];

// Initialize cURL session for token request
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $token_url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($token_data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

$token_response = curl_exec($ch);

// Check for cURL errors
if (curl_errno($ch)) {
    $_SESSION['error_message'] = 'Error requesting access token: ' . curl_error($ch);
    logMessage('discord_auth.log', "Token request error: " . curl_error($ch));
    curl_close($ch);
    header('Location: /');
    exit;
}

curl_close($ch);

// Parse token response
$token_data = json_decode($token_response, true);

if (!isset($token_data['access_token'])) {
    $_SESSION['error_message'] = 'Failed to retrieve access token.';
    logMessage('discord_auth.log', "Failed to retrieve access token: " . json_encode($token_data));
    header('Location: /');
    exit;
}

$access_token = $token_data['access_token'];

// Fetch user information from Discord API
$api_url = 'https://discord.com/api/users/@me';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $access_token
]);

$api_response = curl_exec($ch);

// Check for cURL errors
if (curl_errno($ch)) {
    $_SESSION['error_message'] = 'Error fetching user data: ' . curl_error($ch);
    logMessage('discord_auth.log', "API request error: " . curl_error($ch));
    curl_close($ch);
    header('Location: /');
    exit;
}

curl_close($ch);

// Parse user data
$user_data = json_decode($api_response, true);

if (!isset($user_data['id'])) {
    $_SESSION['error_message'] = 'Failed to retrieve user information.';
    logMessage('discord_auth.log', "Failed to retrieve user information: " . json_encode($user_data));
    header('Location: /');
    exit;
}

// Store Discord user information in session
$_SESSION['discord_id'] = $user_data['id'];
$_SESSION['discord_username'] = $user_data['username'] . ($user_data['discriminator'] != '0' ? '#' . $user_data['discriminator'] : '');
$_SESSION['discord_email'] = $user_data['email'] ?? '';
$_SESSION['discord_avatar'] = $user_data['avatar'] ?? '';
$_SESSION['discord_verified'] = true;

// Log successful authentication
logMessage('discord_auth.log', "Discord authentication successful - ID: {$user_data['id']}, Username: {$_SESSION['discord_username']}");
$applicationData = checkPendingApplication('discord', $_SESSION['discord_id']);
if ($applicationData) {
    $_SESSION['has_pending_application'] = true;
    $_SESSION['pending_application_data'] = $applicationData;
}

// Redirect back to the whitelist form
header('Location: /');
exit;
?>