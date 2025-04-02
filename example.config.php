<?php
// Include PHPMailer loader
if (file_exists(__DIR__ . '/phpmailer-loader.php')) {
    require_once __DIR__ . '/phpmailer-loader.php';
}


/**
 * Configuration file for Dodgeball Whitelist Application
 * This file should be included in all PHP scripts that need configuration values
 */

// Database Configuration
define('DB_HOST', '11.11.11.11:3306');
define('DB_NAME', 'whitelist');
define('DB_USER', 'admin');
define('DB_PASS', 'password');

// Discord OAuth2 Configuration
define('DISCORD_CLIENT_ID', '123456789');
define('DISCORD_CLIENT_SECRET', 'IXKDSKC4');
define('DISCORD_REDIRECT_URI', 'http://example.com/discord_callback.php');

// Steam API Configuration
define('STEAM_API_KEY', 'VBKWSKKSDSKD');
define('STEAM_REDIRECT_URI', 'http://example.com/steam_callback.php');


// SMTP Email Configuration - Add these new constants
define('SMTP_HOST', 'mail.example.com');  // Your SMTP server address
define('SMTP_PORT', 465);                 // Common SMTP ports: 25, 465 (SSL), 587 (TLS)
define('SMTP_SECURE', 'ssl');             // Options: '', 'ssl', 'tls'
define('SMTP_USERNAME', 'noreply@example.com'); // Your SMTP username
define('SMTP_PASSWORD', 'password');  // Your SMTP password
define('SMTP_AUTH', true);                // Whether to use SMTP authentication

// Email Configuration
define('EMAIL_FROM', 'noreply@example.com');
define('EMAIL_FROM_NAME', "saka Dodgeball Whitelist");

// Security
define('VERIFICATION_CODE_EXPIRY', 600); // 10 minutes in seconds
define('VERIFICATION_CODE_LENGTH', 6); // Default code length
define('VERIFICATION_EMAIL_SUBJECT', 'Dodgeball Whitelist - Email Verification');

// Application Settings
define('LOG_DIRECTORY', __DIR__ . '/logs/');


define('DEBUG_MODE', false);


/**
 * Gets the email verification template from file
 * @return string The email template
 */
function getVerificationEmailTemplate() {
    $template_file = __DIR__ . '/templates/verification_email.html';
    if (file_exists($template_file)) {
        return file_get_contents($template_file);
    }
    // Default template if file doesn't exist
    return '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <div style="background-color: #000; color: #fff; padding: 20px; text-align: center;">
            <h1 style="margin: 0;">Dodgeball Whitelist</h1>
        </div>
        <div style="padding: 20px; background-color: #f9f9f9;">
            <h2>Email Verification</h2>
            <p>Thank you for your interest in joining my Dodgeball server.</p>
            <p>Please use the following verification code to verify your email address:</p>
            <div style="font-size: 24px; letter-spacing: 5px; text-align: center; padding: 15px; background-color: #eee; margin: 20px 0; font-weight: bold;">{verificationCode}</div>
            <p>This code will expire in 10 minutes.</p>
            <p>If you did not request this code, please ignore this email.</p>
        </div>
        <div style="padding: 20px; text-align: center; font-size: 12px; color: #777;">
            <p>This is an automated message, please do not reply to this email.</p>
        </div>
    </div>
</body>
</html>';
}

/**
 * Helper function to get the base URL of the application
 * @return string The base URL
 */
function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $domainName = $_SERVER['HTTP_HOST'];
    return $protocol . $domainName;
}

/**
 * Helper function to log messages
 * @param string $file The log file name
 * @param string $message The message to log
 * @return bool True if successful, false otherwise
 */
function logMessage($file, $message) {
    if (!is_dir(LOG_DIRECTORY)) {
        mkdir(LOG_DIRECTORY, 0755, true);
    }

    $log_data = date('Y-m-d H:i:s') . " - " . $message . "\n";
    return file_put_contents(LOG_DIRECTORY . $file, $log_data, FILE_APPEND);
}

/**
 * Helper function to connect to the database
 * @return PDO|null The PDO database connection or null on failure
 */
function getDatabaseConnection() {
    try {
        $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        logMessage('database_error.log', 'Connection failed: ' . $e->getMessage());
        return null;
    }
}

/**
 * Helper function to generate a secure random verification code
 * @param int $length The length of the code
 * @return string The generated code
 */
function generateVerificationCode($length = 6) {
    return sprintf("%0{$length}d", mt_rand(0, pow(10, $length) - 1));
}

/**
 * Helper function to send an email using PHPMailer (if available) or fallback to mail()
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $message Email body (HTML)
 * @return bool True if email sent, false otherwise
 */
function sendEmail($to, $subject, $message) {
    try {
        // Check if PHPMailer is available through autoloading
        if (class_exists('PHPMailer\PHPMailer\PHPMailer', true)) {
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
            $mail->Encoding = "quoted-printable";
            $mail->addCustomHeader("Content-Disposition", "inline");
            $mail->addCustomHeader("Content-Type", "text/html; charset=UTF-8");


            // Recipients
            $mail->setFrom(defined('EMAIL_FROM') ? EMAIL_FROM : 'noreply@example.com',
                defined('EMAIL_FROM_NAME') ? EMAIL_FROM_NAME : 'No Reply');
            $mail->addAddress($to);
            $mail->clearReplyTos();

            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $message;
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $message));

            // Debug output
            $mail->SMTPDebug = 2; // No debug output

            // Send the email
            $mail_sent = $mail->send();

            // Log the email sending attempt
            if ($mail_sent) {
                logMessage('email.log', "Email sent to {$to} with subject: {$subject} using PHPMailer");
            } else {
                logMessage('email_error.log', "Failed to send email to {$to} with subject: {$subject} using PHPMailer: " . $mail->ErrorInfo);
            }

            return $mail_sent;
        } else {
            // Fall back to basic mail function if PHPMailer is not available
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";

            // From header
            $from_email = defined('EMAIL_FROM') ? EMAIL_FROM : 'noreply@example.com';
            $from_name = defined('EMAIL_FROM_NAME') ? EMAIL_FROM_NAME : 'No Reply';
            $headers .= "From: " . $from_name . " <" . $from_email . ">" . "\r\n";

            // Send the email
            $mail_sent = mail($to, $subject, $message, $headers);

            // Log the email sending attempt
            if ($mail_sent) {
                logMessage('email.log', "Email sent to {$to} with subject: {$subject} using PHP mail()");
            } else {
                logMessage('email_error.log', "Failed to send email to {$to} with subject: {$subject} using PHP mail()");
                logMessage('email_error.log', "Consider installing PHPMailer for better email delivery");
            }

            return $mail_sent;
        }
    } catch (Exception $e) {
        // Log the exception
        logMessage('email_error.log', "Exception while sending email to {$to}: " . $e->getMessage());
        return false;
    }
}



/**
 * Helper function to check if a user has a pending application
 * @param string $authMethod The authentication method (steam, discord, email)
 * @param string $userId The user's ID
 * @return array|false The application data if found, false otherwise
 */
function checkPendingApplication($authMethod, $userId) {
    try {
        $pdo = getDatabaseConnection();

        if ($pdo) {
            $column = '';

            if ($authMethod === 'steam') {
                $column = 'steam_id';
            } elseif ($authMethod === 'discord') {
                $column = 'discord_id';
            } elseif ($authMethod === 'email') {
                $column = 'email';
            } else {
                return false;
            }

            $stmt = $pdo->prepare("SELECT * FROM whitelist_applications 
                                WHERE {$column} = :id AND status = 'pending'
                                ORDER BY submission_date DESC LIMIT 1");
            $stmt->execute([':id' => $userId]);

            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        logMessage('application_error.log', "Error checking pending applications: " . $e->getMessage());
    }

    return false;
}