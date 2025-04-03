<?php
// Start session
session_start();

// Include configuration
require_once 'config.php';

// Log the logout
if (isset($_SESSION['admin_username'])) {
    $admin_username = $_SESSION['admin_username'];
    logMessage('admin_auth.log', "Admin logout: $admin_username from IP: {$_SERVER['REMOTE_ADDR']}");
}

// Clear all session variables
session_unset();

// Destroy the session
session_destroy();

// Start a new session to set the logout message
session_start();
$_SESSION['admin_success'] = 'You have been successfully logged out.';

// Redirect to login page
header('Location: /admin/login');
exit;
?>