<?php
// send_verification.php - Updated script to use template function

// Include configuration
require_once 'config.php';

// Start session
session_start();

// Enable error reporting for debugging
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Log verification request
logMessage('email_verification.log', "Verification code request received from IP: {$_SERVER['REMOTE_ADDR']}");

// Check if it's a POST request
if ($_SERVER["REQUEST_METHOD"] != "POST") {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// Get the email from the request
$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);

if (!$email) {
    logMessage('email_verification.log', "Invalid email address provided: " . ($_POST['email'] ?? 'none'));
    echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
    exit;
}

// Get code length from config
$code_length = defined('VERIFICATION_CODE_LENGTH') ? VERIFICATION_CODE_LENGTH : 6;

// Generate a random code using helper function from config with custom length
$verification_code = generateVerificationCode($code_length);

// Store the code in session with timestamp for expiry check
$_SESSION['email_verification'] = [
    'email' => $email,
    'code' => $verification_code,
    'timestamp' => time()
];

// Log the verification code (for debugging)
logMessage('email_verification.log', "Generated verification code for {$email}: {$verification_code}");

// If using database, store the verification code
try {
    $pdo = getDatabaseConnection();
    if ($pdo) {
        // Delete any existing codes for this email
        $stmt = $pdo->prepare("DELETE FROM verification_codes WHERE email = :email");
        $stmt->execute([':email' => $email]);

        // Check if the table exists, if not create it
        $stmt = $pdo->query("SHOW TABLES LIKE 'verification_codes'");
        if ($stmt->rowCount() == 0) {
            // Create the table
            $pdo->exec("CREATE TABLE verification_codes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) NOT NULL,
                code VARCHAR(10) NOT NULL,
                expires_at DATETIME NOT NULL,
                used BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
            logMessage('email_verification.log', "Created verification_codes table");
        }

        // Insert the new code
        $stmt = $pdo->prepare("INSERT INTO verification_codes (email, code, expires_at) VALUES (:email, :code, DATE_ADD(NOW(), INTERVAL 30 MINUTE))");
        $stmt->execute([
            ':email' => $email,
            ':code' => $verification_code
        ]);

        logMessage('email_verification.log', "Stored verification code in database for {$email}");
    }
} catch (PDOException $e) {
    // Log error but continue with session-based verification as fallback
    logMessage('email_verification.log', "Database error: " . $e->getMessage());
}

// Get email template and subject from config
$subject = defined('VERIFICATION_EMAIL_SUBJECT') ? VERIFICATION_EMAIL_SUBJECT : "Dodgeball Whitelist - Email Verification";

// Get template using the function from config.php
$htmlTemplate = function_exists('getVerificationEmailTemplate') ? getVerificationEmailTemplate() : "Your verification code is: {verificationCode}";

// Create a plain-text version by stripping HTML tags
$plainTemplate = strip_tags($htmlTemplate);

// If the template appears to be already plain text (no HTML tags), use it directly
if ($plainTemplate === $htmlTemplate) {
    $plainTemplate = $htmlTemplate;
}

// Replace placeholder with actual code
$htmlMessage = str_replace('{verificationCode}', $verification_code, $htmlTemplate);
$plainMessage = str_replace('{verificationCode}', $verification_code, $plainTemplate);

// Email parameters
$to = $email;

// Send email with both HTML and plain text parts
$mail_sent = false;

// Check if PHPMailer is available
if (class_exists('PHPMailer\PHPMailer\PHPMailer', true)) {
    try {
        // Use PHPMailer
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);

        // Server settings
        $mail->isSMTP();
        $mail->Host = defined('SMTP_HOST') ? SMTP_HOST : 'localhost';
        $mail->SMTPAuth = defined('SMTP_AUTH') ? SMTP_AUTH : false;

        if (defined('SMTP_USERNAME') && defined('SMTP_PASSWORD') && $mail->SMTPAuth) {
            $mail->Username = SMTP_USERNAME;
            $mail->Password = SMTP_PASSWORD;
        }

        if (defined('SMTP_SECURE') && !empty(SMTP_SECURE)) {
            $mail->SMTPSecure = SMTP_SECURE;
        }

        if (defined('SMTP_PORT')) {
            $mail->Port = SMTP_PORT;
        } else {
            $mail->Port = 25; // Default SMTP port
        }

        $mail->CharSet = 'UTF-8';

        // Recipients
        $mail->setFrom(defined('EMAIL_FROM') ? EMAIL_FROM : 'noreply@example.com',
            defined('EMAIL_FROM_NAME') ? EMAIL_FROM_NAME : 'No Reply');
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlMessage;
        $mail->AltBody = $plainMessage; // Alternative plain text version

        // Debug output
        $mail->SMTPDebug = 3; // No debug output

        // Send the email
        $mail_sent = $mail->send();

        logMessage('email_verification.log', "Used PHPMailer to send verification email");
    } catch (Exception $e) {
        logMessage('email_error.log', "PHPMailer error: " . $e->getMessage());
        // Will fall back to mail() function below
    }
}

// Fall back to basic mail() if PHPMailer failed or isn't available
if (!$mail_sent) {
    // Set up MIME boundary for mixed content email
    $boundary = md5(time());

    // Email headers
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";

    // From header
    $from_email = defined('EMAIL_FROM') ? EMAIL_FROM : 'noreply@example.com';
    $from_name = defined('EMAIL_FROM_NAME') ? EMAIL_FROM_NAME : 'No Reply';
    $headers .= "From: " . $from_name . " <" . $from_email . ">\r\n";

    // Email body with both plain text and HTML versions
    $body = "--{$boundary}\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $body .= $plainMessage . "\r\n\r\n";

    $body .= "--{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $body .= $htmlMessage . "\r\n\r\n";

    $body .= "--{$boundary}--";

    // Send the email
    $mail_sent = mail($to, $subject, $body, $headers);

    logMessage('email_verification.log', "Used mail() function to send verification email");
}

// Add this after sending the email and before returning success
if ($mail_sent) {
    // Log the verification attempt
    logMessage('email_verification.log', "Verification code sent to: {$email}");

    // Save in session that we've sent a code to this email
    $_SESSION['code_sent'] = true;
    $_SESSION['code_sent_email'] = $email;

    echo json_encode(['success' => true, 'message' => 'Verification code sent successfully.']);
} else {
    logMessage('email_verification.log', "Failed to send verification code to: {$email}");
    echo json_encode(['success' => false, 'message' => 'Failed to send verification code. Please try again later.']);
}
?>