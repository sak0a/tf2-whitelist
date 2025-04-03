<?php
// verify_code.php - Script to verify the email code

// Include configuration
require_once 'config.php';

// Start session
session_start();

// Enable error reporting for debugging
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Log the verification attempt with all data for debugging
$debug_data = [
    'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'],
    'POST_DATA' => $_POST,
    'SESSION_DATA' => isset($_SESSION['email_verification']) ? $_SESSION['email_verification'] : 'not set'
];
logMessage('email_verification.log', "Verification attempt details: " . json_encode($debug_data));

// Check if it's a POST request
if ($_SERVER["REQUEST_METHOD"] != "POST") {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// Get the email and code from the request
$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
$code = filter_input(INPUT_POST, 'code', FILTER_SANITIZE_STRING);

if (!$email || !$code) {
    echo json_encode(['success' => false, 'message' => 'Missing email or verification code.']);
    exit;
}

// Log the inputs
logMessage('email_verification.log', "Verification attempt for email: $email with code: $code");

// Check if verification data exists in session
if (!isset($_SESSION['email_verification'])) {
    logMessage('email_verification.log', "No verification data in session.");
    echo json_encode(['success' => false, 'message' => 'No verification code found for this email. Please request a new code.']);
    exit;
}

// Check if the email matches
if ($_SESSION['email_verification']['email'] !== $email) {
    logMessage('email_verification.log', "Email mismatch. Session has: {$_SESSION['email_verification']['email']} but received: $email");
    echo json_encode(['success' => false, 'message' => 'Email does not match the one used for verification.']);
    exit;
}

// Check if code has expired
$timestamp = $_SESSION['email_verification']['timestamp'];
$expiry_seconds = defined('VERIFICATION_CODE_EXPIRY') ? VERIFICATION_CODE_EXPIRY : 600; // Default 10 minutes
if ((time() - $timestamp) > $expiry_seconds) {
    logMessage('email_verification.log', "Code expired. Generated at: " . date('Y-m-d H:i:s', $timestamp));
    echo json_encode(['success' => false, 'message' => 'Verification code has expired. Please request a new code.']);
    unset($_SESSION['email_verification']);
    exit;
}

// Check if the code matches
if ($_SESSION['email_verification']['code'] !== $code) {
    logMessage('email_verification.log', "Code mismatch. Expected: {$_SESSION['email_verification']['code']} but received: $code");
    echo json_encode(['success' => false, 'message' => 'Invalid verification code.']);
    exit;
}

// Code is valid, mark the email as verified
$_SESSION['email_verified'] = true;
$_SESSION['verified_email'] = $email;

// Clear the verification data
$verification_data = $_SESSION['email_verification'];
unset($_SESSION['email_verification']);

// Log the successful verification
logMessage('email_verification.log', "Email verification successful: {$email}");

try {
    $pdo = getDatabaseConnection();
    if ($pdo) {
        // Check for banned application
        $stmt = $pdo->prepare("SELECT * FROM whitelist_applications WHERE email = :email AND status = 'banned' ORDER BY submission_date DESC LIMIT 1");
        $stmt->execute([':email' => $email]);
        $bannedApp = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($bannedApp) {
            $_SESSION['is_banned'] = true;
            $_SESSION['banned_application_data'] = $bannedApp;
            logMessage('email_verification.log', "User email {$email} is associated with a banned application");
        } else {
            // Check for approved application
            $stmt = $pdo->prepare("SELECT * FROM whitelist_applications WHERE email = :email AND status = 'approved' ORDER BY submission_date DESC LIMIT 1");
            $stmt->execute([':email' => $email]);
            $approvedApp = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($approvedApp) {
                $_SESSION['is_approved'] = true;
                $_SESSION['approved_application_data'] = $approvedApp;
                logMessage('email_verification.log', "User email {$email} has an approved application");
            } else {
                // Check for pending application
                $stmt = $pdo->prepare("SELECT * FROM whitelist_applications WHERE email = :email AND status = 'pending' ORDER BY submission_date DESC LIMIT 1");
                $stmt->execute([':email' => $email]);
                $pendingApp = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($pendingApp) {
                    $_SESSION['has_pending_application'] = true;
                    $_SESSION['pending_application_data'] = $pendingApp;
                    logMessage('email_verification.log', "User email {$email} has a pending application");
                }
            }
        }
    }
} catch (PDOException $e) {
    logMessage('email_verification.log', "Database error checking application status: " . $e->getMessage());
}


// Check for pending application using our helper function
$applicationData = checkPendingApplication('email', $email);
if ($applicationData) {
    $_SESSION['has_pending_application'] = true;
    $_SESSION['pending_application_data'] = $applicationData;
    logMessage('email_verification.log', "User has a pending application: " . json_encode($_SESSION['pending_application_data']));
}

// Return success
echo json_encode(['success' => true, 'message' => 'Email verified successfully.']);
?>