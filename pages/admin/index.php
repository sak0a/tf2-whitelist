<?php
// Start session
session_start();

// Include configuration if needed
require_once 'config.php';

// Check if admin is logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    // User is logged in, redirect to dashboard
    header('Location: /admin/dashboard');
    exit;
} else {
    // User is not logged in, redirect to login page
    header('Location: /admin/login');
    exit;
}
?>