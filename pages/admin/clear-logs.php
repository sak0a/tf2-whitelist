<?php
// Start session
global $pdo;
session_start();

// Include configuration and admin authentication check
require_once 'config.php';
require_once 'auth_check.php'; // This file should check if admin is logged in

// Get application ID from the URL
$application_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($application_id <= 0) {
    $_SESSION['admin_error'] = 'Invalid application ID';
    header('Location: /admin/applications');
    exit;
}

// Check for confirmation to prevent accidental deletion
if (!isset($_GET['confirm']) || $_GET['confirm'] !== 'yes') {
    // Redirect back with a confirmation dialog
    $_SESSION['admin_warning'] = "Are you sure you want to clear the activity log for application #$application_id? <a href='/admin/clear-logs?id=$application_id&confirm=yes' class='font-bold text-red-700 hover:underline'>Yes, clear activity logs</a>";
    header("Location: /admin/view-application/$application_id");
    exit;
}

// Process the clear request
try {
    $pdo = getDatabaseConnection();

    // First check if the application exists and get its current status
    $stmt = $pdo->prepare("SELECT id, status FROM whitelist_applications WHERE id = :id");
    $stmt->execute([':id' => $application_id]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$application) {
        $_SESSION['admin_error'] = 'Application not found';
        header('Location: /admin/applications');
        exit;
    }

    // Get the admin ID for logging
    $admin_id = $_SESSION['admin_id'];

    // Get count of logs to be deleted
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM activity_log WHERE application_id = :app_id");
    $stmt->execute([':app_id' => $application_id]);
    $count = $stmt->fetchColumn();

    // Begin transaction to ensure atomicity
    $pdo->beginTransaction();

    // If the application status is not pending, preserve the last status change entry
    if ($application['status'] !== 'pending') {
        // Find the most recent status change entry matching the current status
        $stmt = $pdo->prepare("
            SELECT * FROM activity_log 
            WHERE application_id = :app_id 
            AND action = :status
            ORDER BY timestamp DESC 
            LIMIT 1
        ");
        $stmt->execute([
            ':app_id' => $application_id,
            ':status' => $application['status']
        ]);
        $lastStatusEntry = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($lastStatusEntry) {
            // Delete all logs except the last status change entry
            $stmt = $pdo->prepare("
                DELETE FROM activity_log 
                WHERE application_id = :app_id 
                AND id != :last_entry_id
            ");
            $stmt->execute([
                ':app_id' => $application_id,
                ':last_entry_id' => $lastStatusEntry['id']
            ]);
        } else {
            // No matching status entry found, but we need to preserve the status
            // So delete all logs
            $stmt = $pdo->prepare("DELETE FROM activity_log WHERE application_id = :app_id");
            $stmt->execute([':app_id' => $application_id]);

            // And create a new log entry for the current status
            $stmt = $pdo->prepare("
                INSERT INTO activity_log 
                (admin_id, application_id, action, details, ip_address, timestamp) 
                VALUES (:admin_id, :app_id, :action, :details, :ip, NOW())
            ");
            $stmt->execute([
                ':admin_id' => $admin_id,
                ':app_id' => $application_id,
                ':action' => $application['status'],
                ':details' => "Application {$application['status']}",
                ':ip' => $_SERVER['REMOTE_ADDR']
            ]);
        }
    } else {
        // For pending applications, delete all logs
        $stmt = $pdo->prepare("DELETE FROM activity_log WHERE application_id = :app_id");
        $stmt->execute([':app_id' => $application_id]);
    }

    // Add a log entry for the admin indicating logs were cleared
    // This entry will be visible to admins but not to users (filtered in status.php)
    $stmt = $pdo->prepare("
        INSERT INTO activity_log 
        (admin_id, application_id, action, details, ip_address, timestamp) 
        VALUES (:admin_id, :app_id, 'clear_logs', :details, :ip, NOW())
    ");
    $stmt->execute([
        ':admin_id' => $admin_id,
        ':app_id' => $application_id,
        ':details' => "Admin cleared $count activity log entries",
        ':ip' => $_SERVER['REMOTE_ADDR']
    ]);

    // Commit the transaction
    $pdo->commit();

    // Set success message
    $_SESSION['admin_success'] = "Activity log for application #$application_id has been cleared";

} catch (PDOException $e) {
    // Roll back the transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $_SESSION['admin_error'] = "Database error: " . $e->getMessage();
    logMessage('admin_error.log', "Error clearing logs for application $application_id: " . $e->getMessage());
}

// Redirect back to the application view
header("Location: /admin/view-application/$application_id");
exit;
?>