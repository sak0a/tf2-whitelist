<?php
// Start session
session_start();

// Include configuration
require_once 'config.php';

// Debug mode - set to true to see detailed error information
define('DEBUG_MODE', true);

// Steam OpenID and API Configuration
$steam_api_key = STEAM_API_KEY;

// Log all incoming parameters for debugging
if (DEBUG_MODE) {
    $debug_info = "Steam callback parameters: " . json_encode($_GET);
    logMessage('steam_debug.log', $debug_info);
}

// Check if we have the required parameters
if (!isset($_GET['openid_claimed_id']) || !isset($_GET['openid_assoc_handle']) ||
    !isset($_GET['openid_signed']) || !isset($_GET['openid_sig'])) {
    $_SESSION['error_message'] = 'Missing required OpenID parameters';
    logMessage('steam_auth.log', "Authentication failed - missing parameters: " . json_encode($_GET));
    header('Location: /');
    exit;
}

// Verify the response from Steam
$params = [
    'openid.assoc_handle' => $_GET['openid_assoc_handle'],
    'openid.signed'       => $_GET['openid_signed'],
    'openid.sig'          => $_GET['openid_sig'],
    'openid.ns'           => $_GET['openid_ns'],
];

// Copy all openid parameters to an array for validation
foreach ($_GET as $key => $value) {
    $key = str_replace('openid_', 'openid.', $key);
    $params[$key] = $value;
}

// Change mode to 'check_authentication'
$params['openid.mode'] = 'check_authentication';

// Log the validation parameters
if (DEBUG_MODE) {
    logMessage('steam_debug.log', "Validation parameters: " . json_encode($params));
}

// Send validation request to Steam
$validation_url = 'https://steamcommunity.com/openid/login';
$data = http_build_query($params);

// Initialize cURL for validation
$curl = curl_init($validation_url);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_POST, true);
curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
curl_setopt($curl, CURLOPT_VERBOSE, DEBUG_MODE);

// Capture curl debug if enabled
if (DEBUG_MODE) {
    $curl_debug = fopen('php://temp', 'w+');
    curl_setopt($curl, CURLOPT_STDERR, $curl_debug);
}

$response = curl_exec($curl);

// Log the raw response
if (DEBUG_MODE) {
    logMessage('steam_debug.log', "Steam validation response: " . $response);

    // Log curl debug info
    rewind($curl_debug);
    $curl_debug_log = stream_get_contents($curl_debug);
    fclose($curl_debug);
    logMessage('steam_debug.log', "cURL debug info: " . $curl_debug_log);

    // Log more info about the curl request
    logMessage('steam_debug.log', "cURL info: " . json_encode(curl_getinfo($curl)));
}

// Check for cURL errors
if (curl_errno($curl)) {
    $error = curl_error($curl);
    $_SESSION['error_message'] = 'Error validating authentication: ' . $error;
    logMessage('steam_auth.log', "Validation error: " . $error);
    curl_close($curl);
    header('Location: /');
    exit;
}

curl_close($curl);

// Check if the response is valid
if (strpos($response, 'is_valid:true') === false) {
    $_SESSION['error_message'] = 'Steam authentication failed validation.';
    logMessage('steam_auth.log', "Authentication failed validation: " . $response);
    header('Location: /p');
    exit;
}

// Extract Steam ID from the response
if (!preg_match('/^https?:\/\/steamcommunity\.com\/openid\/id\/(7[0-9]{15,25})$/i', $_GET['openid_claimed_id'], $matches)) {
    $_SESSION['error_message'] = 'Failed to extract Steam ID from authentication response.';
    logMessage('steam_auth.log', "Failed to extract Steam ID from response: " . $_GET['openid_claimed_id']);
    header('Location: /');
    exit;
}

$steam_id = $matches[1]; // This is the Steam64 ID

// Fetch user info from Steam API
$steam_user_api = "https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key={$steam_api_key}&steamids={$steam_id}";

// Log API request URL
if (DEBUG_MODE) {
    logMessage('steam_debug.log', "Steam API request URL: " . $steam_user_api);
}

// Initialize cURL for Steam API
$curl = curl_init($steam_user_api);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');

$api_response = curl_exec($curl);

// Log API response
if (DEBUG_MODE) {
    logMessage('steam_debug.log', "Steam API response: " . $api_response);
}

// Check for cURL errors
if (curl_errno($curl)) {
    $error = curl_error($curl);
    $_SESSION['error_message'] = 'Error fetching Steam user data: ' . $error;
    logMessage('steam_auth.log', "API request error: " . $error);
    curl_close($curl);
    header('Location: /');
    exit;
}

curl_close($curl);

// Parse user data
$user_data = json_decode($api_response, true);

if (!isset($user_data['response']['players'][0])) {
    $_SESSION['error_message'] = 'Failed to retrieve Steam user information.';
    logMessage('steam_auth.log', "Failed to retrieve Steam user information: " . json_encode($user_data));
    header('Location: /');
    exit;
}

$player = $user_data['response']['players'][0];

// Convert Steam64 ID to SteamID3 format
// SteamID3 format is [U:1:XXXXXXX] where XXXXXXX = (Steam64 - 76561197960265728)
$steam_id3_value = $steam_id - 76561197960265728;
$steam_id3 = "[U:1:{$steam_id3_value}]";

// Store Steam user information in session
$_SESSION['steam_id'] = $steam_id;
$_SESSION['steam_id3'] = $steam_id3;
$_SESSION['steam_username'] = $player['personaname'] ?? '';
$_SESSION['steam_profile'] = $player['profileurl'] ?? '';
$_SESSION['steam_avatar'] = $player['avatarfull'] ?? '';
$_SESSION['steam_verified'] = true;

// Log successful authentication
logMessage('steam_auth.log', "Steam authentication successful - ID: {$steam_id}, Steam ID3: {$steam_id3}, Username: {$_SESSION['steam_username']}");

$applicationData = checkPendingApplication('steam', $steam_id);
if ($applicationData) {
    $_SESSION['has_pending_application'] = true;
    $_SESSION['pending_application_data'] = $applicationData;
}
// Redirect back to the whitelist form
header('Location: /');
exit;
?>