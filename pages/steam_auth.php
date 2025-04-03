<?php
// Start session
session_start();

// Include configuration
require_once 'config.php';

// Debug mode
define('DEBUG_MODE', true);

// Steam OpenID Configuration - using constant from config
$steam_api_key = STEAM_API_KEY;
$steam_login_url = 'https://steamcommunity.com/openid/login';
$return_url = STEAM_REDIRECT_URI;

// Log the configuration being used
if (DEBUG_MODE) {
    logMessage('steam_debug.log', "Steam auth initiated with API key: " . substr($steam_api_key, 0, 5) . "... and redirect URL: " . $return_url);
}

// Ensure the return URL is properly formatted and accessible
$parsed_url = parse_url($return_url);
if (!$parsed_url || !isset($parsed_url['scheme']) || !isset($parsed_url['host'])) {
    $_SESSION['error_message'] = 'Invalid return URL configuration.';
    logMessage('steam_auth.log', "Invalid return URL: " . $return_url);
    header('Location: /');
    exit;
}

// Parameters for Steam OpenID
$params = [
    'openid.ns'         => 'http://specs.openid.net/auth/2.0',
    'openid.mode'       => 'checkid_setup',
    'openid.return_to'  => $return_url,
    'openid.realm'      => (!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'],
    'openid.identity'   => 'http://specs.openid.net/auth/2.0/identifier_select',
    'openid.claimed_id' => 'http://specs.openid.net/auth/2.0/identifier_select',
];

// Generate state parameter to prevent CSRF (optional but recommended)
$state = bin2hex(random_bytes(16));
$_SESSION['steam_oauth_state'] = $state;
$params['openid.state'] = $state;

// Log the parameters we're sending
if (DEBUG_MODE) {
    logMessage('steam_debug.log', "Steam auth parameters: " . json_encode($params));
}

// Build the authentication URL
$auth_url = $steam_login_url . '?' . http_build_query($params);

// Log the full auth URL
if (DEBUG_MODE) {
    logMessage('steam_debug.log', "Full Steam auth URL: " . $auth_url);
}

// Redirect to Steam's authorization page
header('Location: ' . $auth_url);
exit;
?>