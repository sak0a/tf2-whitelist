<?php
// Start session
session_start();

// Include configuration
require_once 'config.php';

// Get service parameter
$service = isset($_GET['service']) ? $_GET['service'] : '';

// Log the logout action
logMessage('auth.log', "Logout request for service: {$service}");

// Handle service-specific logout
switch ($service) {
    case 'steam':
        // Unset only Steam-related session variables
        unset($_SESSION['steam_verified']);
        unset($_SESSION['steam_id']);
        unset($_SESSION['steam_id3']);
        unset($_SESSION['steam_username']);
        unset($_SESSION['steam_profile']);
        unset($_SESSION['steam_avatar']);

        logMessage('auth.log', "Steam session data cleared");
        break;

    case 'discord':
        // Unset only Discord-related session variables
        unset($_SESSION['discord_verified']);
        unset($_SESSION['discord_id']);
        unset($_SESSION['discord_username']);
        unset($_SESSION['discord_email']);
        unset($_SESSION['discord_avatar']);

        logMessage('auth.log', "Discord session data cleared");
        break;

    case 'email':
        // Unset only email-related session variables
        unset($_SESSION['email_verified']);
        unset($_SESSION['verified_email']);

        logMessage('auth.log', "Email verification data cleared");
        break;

    case 'all':
        // Clear all authentication data (but preserve other session data)
        unset($_SESSION['steam_verified']);
        unset($_SESSION['steam_id']);
        unset($_SESSION['steam_id3']);
        unset($_SESSION['steam_username']);
        unset($_SESSION['steam_profile']);
        unset($_SESSION['steam_avatar']);
        unset($_SESSION['discord_verified']);
        unset($_SESSION['discord_id']);
        unset($_SESSION['discord_username']);
        unset($_SESSION['discord_email']);
        unset($_SESSION['discord_avatar']);
        unset($_SESSION['email_verified']);
        unset($_SESSION['verified_email']);
        unset($_SESSION['has_pending_application']);
        unset($_SESSION['pending_application_data']);

        logMessage('auth.log', "All authentication data cleared");
        break;

    default:
        // If no valid service specified, don't clear anything
        $_SESSION['error_message'] = 'Invalid logout request.';
        logMessage('auth.log', "Invalid logout request");
        break;
}

// Redirect back to index page
header('Location: /?clear_form=1');
exit;
?>