<?php
// save_code_state.php - Script to save the verification code sent state

// Start session
session_start();

// Get the email from the request
$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);

if (!$email) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
    exit;
}

// Save in session that we've sent a code to this email
$_SESSION['code_sent'] = true;
$_SESSION['code_sent_email'] = $email;

// Log the action
if (function_exists('logMessage')) {
    logMessage('email_verification.log', "Code sent state saved for email: $email");
}

// Return success response
echo json_encode(['success' => true, 'message' => 'Code sent state saved.']);
?>