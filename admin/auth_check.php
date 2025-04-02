<?php
/**
 * Admin Authentication Check
 *
 * This file is included in all admin pages to ensure
 * only authenticated administrators can access them.
 */

// Make sure session is started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // Not logged in, redirect to login page
    $_SESSION['admin_error'] = 'Please log in to access the admin area';

    // Log attempted unauthorized access
    if (function_exists('logMessage')) {
        logMessage('admin_security.log', "Unauthorized access attempt from IP: {$_SERVER['REMOTE_ADDR']}");
    }

    // Determine the login page location
    $login_page = 'login.php';

    // If we're in a subdirectory, adjust path to login
    if (!file_exists($login_page)) {
        $login_page = 'admin/login.php';

        // If we're already in the admin directory
        if (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) {
            $login_page = 'login.php';
        }
    }

    // Redirect to login page
    header("Location: $login_page");
    exit;
}

// Check admin role and permission (optional additional security)
if (isset($_SESSION['admin_role'])) {
    $current_page = basename($_SERVER['PHP_SELF']);
    $restricted_pages = [
        'users.php' => ['admin'],       // Only full admins can manage users
        'settings.php' => ['admin'],    // Only full admins can change settings
        'logs.php' => ['admin']         // Only full admins can view logs
    ];

    // If current page is restricted and user doesn't have the required role
    if (isset($restricted_pages[$current_page]) &&
        !in_array($_SESSION['admin_role'], $restricted_pages[$current_page])) {

        // Log unauthorized access attempt
        if (function_exists('logMessage')) {
            logMessage('admin_security.log', "Insufficient permissions: User {$_SESSION['admin_username']} (role: {$_SESSION['admin_role']}) attempted to access $current_page");
        }

        // Redirect to dashboard with error
        $_SESSION['admin_error'] = 'You do not have permission to access that page';
        header('Location: dashboard.php');
        exit;
    }
}

// Set last activity time for session timeout
$_SESSION['admin_last_activity'] = time();

// Check for inactivity timeout (30 minutes)
$session_timeout = 30 * 60; // 30 minutes in seconds
if (isset($_SESSION['admin_last_activity']) &&
    (time() - $_SESSION['admin_last_activity'] > $session_timeout)) {

    // Log session timeout
    if (function_exists('logMessage')) {
        logMessage('admin_activity.log', "Session timeout for admin: {$_SESSION['admin_username']}");
    }

    // Clear all session variables
    session_unset();

    // Destroy the session
    session_destroy();

    // Redirect to login page with timeout message
    session_start();
    $_SESSION['admin_error'] = 'Your session has expired due to inactivity. Please log in again.';
    header('Location: login.php');
    exit;
}
?>