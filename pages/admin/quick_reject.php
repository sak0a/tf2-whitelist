<?php
// Start session
session_start();

// Include configuration and admin authentication check
require_once '../config.php';
require_once 'auth_check.php'; // This file should check if admin is logged in

// Get application ID from the URL
$application_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($application_id <= 0) {
    $_SESSION['admin_error'] = 'Invalid application ID';
    header('Location: applications.php');
    exit;
}

$admin_id = $_SESSION['admin_id'];
$ip_address = $_SERVER['REMOTE_ADDR'];

try {
    $pdo = getDatabaseConnection();

    // First check if the application exists and is in pending status
    $stmt = $pdo->prepare("SELECT id, status FROM whitelist_applications WHERE id = :id");
    $stmt->execute([':id' => $application_id]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$application) {
        $_SESSION['admin_error'] = 'Application not found';
        header('Location: applications.php');
        exit;
    }

    if ($application['status'] !== 'pending') {
        $_SESSION['admin_error'] = 'Only pending applications can be quickly rejected';
        header('Location: applications.php');
        exit;
    }

    // Call the quick rejection procedure if available, otherwise do it directly
    if (function_exists('mysqli_connect')) {
        // Try to call stored procedure using mysqli
        $mysqli = new mysqli(
            explode(':', DB_HOST)[0],
            DB_USER,
            DB_PASS,
            DB_NAME,
            isset(explode(':', DB_HOST)[1]) ? explode(':', DB_HOST)[1] : 3306
        );

        if ($mysqli->connect_error) {
            throw new Exception('MySQL connection failed: ' . $mysqli->connect_error);
        }

        $stmt = $mysqli->prepare("CALL QuickReject(?, ?, ?)");
        $stmt->bind_param("iis", $application_id, $admin_id, $ip_address);
        $stmt->execute();
        $mysqli->close();
    } else {
        // Use PDO for direct approach
        // Update application status
        $stmt = $pdo->prepare("UPDATE whitelist_applications SET status = 'rejected', rejected_at = NOW(), updated_at = NOW() WHERE id = :id");
        $stmt->execute([':id' => $application_id]);

        // Log the action
        $stmt = $pdo->prepare("INSERT INTO activity_log (admin_id, application_id, application_status, action, details, ip_address, timestamp) 
                              VALUES (:admin_id, :app_id, 'rejected', 'reject', 'Application quickly rejected', :ip, NOW())");
        $stmt->execute([
            ':admin_id' => $admin_id,
            ':app_id' => $application_id,
            ':ip' => $ip_address
        ]);
    }

    $_SESSION['admin_success'] = "Application #$application_id has been rejected";

} catch (Exception $e) {
    $_SESSION['admin_error'] = "Error updating application: " . $e->getMessage();
    logMessage('admin_error.log', "Error quick rejecting application $application_id: " . $e->getMessage());
}

// Redirect back to the applications list
header('Location: applications.php');
exit;