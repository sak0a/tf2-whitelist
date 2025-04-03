<?php
// Start session
session_start();

// Include configuration and admin authentication check
require_once 'config.php';
require_once 'auth_check.php'; // This file should check if admin is logged in

// Include PHPMailer setup
require_once 'phpmailer-setup.php';

// Check if PHPMailer is installed
$phpmailer_installed = checkPHPMailer();

// Force PHPMailer check and loading
if (file_exists('../phpmailer-loader.php')) {
    include_once '../phpmailer-loader.php';
    $phpmailer_installed = class_exists('PHPMailer\PHPMailer\PHPMailer', true);
}

// Check if we need to save SMTP settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_smtp'])) {
    // Get settings from the form
    $smtp_host = trim($_POST['smtp_host']);
    $smtp_port = intval($_POST['smtp_port']);
    $smtp_secure = trim($_POST['smtp_secure']);
    $smtp_user = trim($_POST['smtp_user']);
    $smtp_pass = trim($_POST['smtp_pass']);
    $smtp_auth = isset($_POST['smtp_auth']) ? true : false;
    $email_from = trim($_POST['email_from']);
    $email_name = trim($_POST['email_name']);

    // Validate basic inputs
    if (empty($smtp_host) || empty($smtp_user) || empty($email_from) || empty($email_name)) {
        $_SESSION['admin_error'] = 'All required fields must be filled out.';
    } else {
        // Update config.php with new settings
        try {
            // Read the current config.php
            $config_file = file_get_contents('../config.php');

            // Check if SMTP settings already exist
            if (strpos($config_file, "define('SMTP_HOST'") === false) {
                // SMTP settings don't exist, add them after EMAIL_FROM_NAME
                $smtp_settings = "\n// SMTP Email Configuration\n";
                $smtp_settings .= "define('SMTP_HOST', '$smtp_host'); // SMTP server\n";
                $smtp_settings .= "define('SMTP_PORT', $smtp_port); // SMTP port\n";
                $smtp_settings .= "define('SMTP_SECURE', '$smtp_secure'); // SMTP security\n";
                $smtp_settings .= "define('SMTP_USERNAME', '$smtp_user'); // SMTP username\n";
                $smtp_settings .= "define('SMTP_PASSWORD', '$smtp_pass'); // SMTP password\n";
                $smtp_settings .= "define('SMTP_AUTH', " . ($smtp_auth ? 'true' : 'false') . "); // SMTP authentication\n";

                // Insert after EMAIL_FROM_NAME
                $config_file = preg_replace("/(define\('EMAIL_FROM_NAME',.*?\);)/", "$1\n$smtp_settings", $config_file);
            } else {
                // Update existing SMTP settings
                $config_file = preg_replace('/define\(\'SMTP_HOST\',\s*\'.*?\'\);/', "define('SMTP_HOST', '$smtp_host');", $config_file);
                $config_file = preg_replace('/define\(\'SMTP_PORT\',\s*\d+\);/', "define('SMTP_PORT', $smtp_port);", $config_file);
                $config_file = preg_replace('/define\(\'SMTP_SECURE\',\s*\'.*?\'\);/', "define('SMTP_SECURE', '$smtp_secure');", $config_file);
                $config_file = preg_replace('/define\(\'SMTP_USERNAME\',\s*\'.*?\'\);/', "define('SMTP_USERNAME', '$smtp_user');", $config_file);
                $config_file = preg_replace('/define\(\'SMTP_PASSWORD\',\s*\'.*?\'\);/', "define('SMTP_PASSWORD', '$smtp_pass');", $config_file);
                $config_file = preg_replace('/define\(\'SMTP_AUTH\',\s*\w+\);/', "define('SMTP_AUTH', " . ($smtp_auth ? 'true' : 'false') . ");", $config_file);
            }

            // Update email settings
            $config_file = preg_replace('/define\(\'EMAIL_FROM\',\s*\'.*?\'\);/', "define('EMAIL_FROM', '$email_from');", $config_file);
            $config_file = preg_replace('/define\(\'EMAIL_FROM_NAME\',\s*\'.*?\'\);/', "define('EMAIL_FROM_NAME', '$email_name');", $config_file);

            // Write updated config back to file
            if (file_put_contents('../config.php', $config_file)) {
                $_SESSION['admin_success'] = 'SMTP settings updated successfully.';
            } else {
                $_SESSION['admin_error'] = 'Failed to write settings to config.php. Check file permissions.';
            }
        } catch (Exception $e) {
            $_SESSION['admin_error'] = 'Error updating settings: ' . $e->getMessage();
        }
    }

    // Redirect to refresh the page and avoid resubmission
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Check if we need to send a test email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_test'])) {
    $test_email = trim($_POST['test_email']);

    if (empty($test_email) || !filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['admin_error'] = 'Please enter a valid email address for testing.';
    } else {
        // Check if PHPMailer is installed
        if (!$phpmailer_installed) {
            $_SESSION['admin_warning'] = 'PHPMailer is not installed or not properly loaded. Using PHP mail() function for test.';
        }

        // Send a test email
        $subject = 'Dodgeball Whitelist - Test Email';
        $message = "
            <html>
            <head>
                <title>Test Email</title>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background-color: #000; color: #fff; padding: 20px; text-align: center; }
                    .content { padding: 20px; background-color: #f9f9f9; }
                    .footer { padding: 20px; text-align: center; font-size: 12px; color: #777; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>Dodgeball Whitelist</h1>
                    </div>
                    <div class='content'>
                        <h2>Test Email</h2>
                        <p>This is a test email from your Dodgeball Whitelist system.</p>
                        <p>If you received this email, your email settings are working correctly.</p>
                        <p>Sent at: " . date('Y-m-d H:i:s') . "</p>
                    </div>
                    <div class='footer'>
                        <p>This is an automated message from your Dodgeball Whitelist system.</p>
                    </div>
                </div>
            </body>
            </html>
        ";

        if (sendEmail($test_email, $subject, $message)) {
            $_SESSION['admin_success'] = 'Test email sent successfully. Please check your inbox.';
        } else {
            $_SESSION['admin_error'] = 'Failed to send test email. Check your SMTP settings and logs.';
        }
    }

    // Redirect to refresh the page and avoid resubmission
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Check if PHPMailer needs to be installed
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install_phpmailer'])) {
    $setup_result = setupPHPMailer();
    if (strpos($setup_result, 'successfully') !== false) {
        $_SESSION['admin_success'] = $setup_result;
    } else {
        $_SESSION['admin_error'] = $setup_result;
    }

    // Redirect to refresh the page and avoid resubmission
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_verification_settings'])) {
    // Get settings from the form
    $verification_code_length = intval($_POST['verification_code_length']);
    $verification_email_subject = trim($_POST['verification_email_subject']);
    $verification_email_template = trim($_POST['verification_email_template']);

    // Validate basic inputs
    if (empty($verification_email_subject) || empty($verification_email_template)) {
        $_SESSION['admin_error'] = 'Email subject and template are required.';
    } elseif ($verification_code_length < 4 || $verification_code_length > 10) {
        $_SESSION['admin_error'] = 'Verification code length must be between 4 and 10 digits.';
    } else {
        // Update config.php with new settings
        try {
            // Read the current config.php
            $config_file = file_get_contents('../config.php');

            // Check if verification settings already exist
            $has_verification_code_length = strpos($config_file, "define('VERIFICATION_CODE_LENGTH'") !== false;
            $has_verification_email_subject = strpos($config_file, "define('VERIFICATION_EMAIL_SUBJECT'") !== false;

            // Prepare the new constants
            $new_constants = "";

            if (!$has_verification_code_length) {
                $new_constants .= "define('VERIFICATION_CODE_LENGTH', $verification_code_length); // Verification code length\n";
            } else {
                $config_file = preg_replace('/define\(\'VERIFICATION_CODE_LENGTH\',\s*\d+\);/', "define('VERIFICATION_CODE_LENGTH', $verification_code_length);", $config_file);
            }

            if (!$has_verification_email_subject) {
                $new_constants .= "define('VERIFICATION_EMAIL_SUBJECT', '$verification_email_subject'); // Verification email subject\n";
            } else {
                $config_file = preg_replace('/define\(\'VERIFICATION_EMAIL_SUBJECT\',\s*\'.*?\'\);/', "define('VERIFICATION_EMAIL_SUBJECT', '$verification_email_subject');", $config_file);
            }

            // For the template, we'll create a custom function that loads it from a separate file
            if (strpos($config_file, "function getVerificationEmailTemplate()") === false) {
                // Add the function to fetch the template from a file
                $template_function = "
/**
 * Gets the email verification template from file
 * @return string The email template
 */
function getVerificationEmailTemplate() {
    \$template_file = __DIR__ . '/templates/verification_email.html';
    if (file_exists(\$template_file)) {
        return file_get_contents(\$template_file);
    }
    // Default template if file doesn't exist
    return '<!DOCTYPE html>
<html>
<head>
    <meta charset=\"utf-8\">
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
    <title>Email Verification</title>
</head>
<body style=\"font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0;\">
    <div style=\"max-width: 600px; margin: 0 auto; padding: 20px;\">
        <div style=\"background-color: #000; color: #fff; padding: 20px; text-align: center;\">
            <h1 style=\"margin: 0;\">Dodgeball Whitelist</h1>
        </div>
        <div style=\"padding: 20px; background-color: #f9f9f9;\">
            <h2>Email Verification</h2>
            <p>Thank you for your interest in joining our Dodgeball server.</p>
            <p>Please use the following verification code to verify your email address:</p>
            <div style=\"font-size: 24px; letter-spacing: 5px; text-align: center; padding: 15px; background-color: #eee; margin: 20px 0; font-weight: bold;\">{verificationCode}</div>
            <p>This code will expire in 30 minutes.</p>
            <p>If you did not request this code, please ignore this email.</p>
        </div>
        <div style=\"padding: 20px; text-align: center; font-size: 12px; color: #777;\">
            <p>This is an automated message, please do not reply to this email.</p>
        </div>
    </div>
</body>
</html>';
}
";

                // Add this function after all the constants
                if (strpos($config_file, "?>") !== false) {
                    // If there's a closing PHP tag, insert before it
                    $config_file = str_replace("?>", $template_function . "\n?>", $config_file);
                } else {
                    // Otherwise just append to the end
                    $config_file .= "\n" . $template_function;
                }
            }

            // If we have new constants to add, insert them after SMTP settings
            if (!empty($new_constants)) {
                // Add a header if this is the first time adding verification settings
                if (!$has_verification_code_length && !$has_verification_email_subject) {
                    $new_constants = "\n// Email Verification Settings\n" . $new_constants;
                }

                // Insert after SMTP settings or EMAIL_FROM_NAME
                if (strpos($config_file, "define('SMTP_AUTH'") !== false) {
                    $config_file = preg_replace("/(define\('SMTP_AUTH',.*?\);)/", "$1\n$new_constants", $config_file);
                } else {
                    $config_file = preg_replace("/(define\('EMAIL_FROM_NAME',.*?\);)/", "$1\n$new_constants", $config_file);
                }
            }

            // Make sure the templates directory exists
            $template_dir = '../templates';
            if (!is_dir($template_dir)) {
                mkdir($template_dir, 0755, true);
            }

            // Save the template to a file
            $template_file = $template_dir . '/verification_email.html';
            if (file_put_contents($template_file, $verification_email_template)) {
                // If we successfully wrote the template file, also update config.php
                if (file_put_contents('../config.php', $config_file)) {
                    $_SESSION['admin_success'] = 'Verification email settings updated successfully.';
                } else {
                    $_SESSION['admin_error'] = 'Failed to write settings to config.php. Check file permissions.';
                }
            } else {
                $_SESSION['admin_error'] = 'Failed to save template file. Check directory permissions.';
            }
        } catch (Exception $e) {
            $_SESSION['admin_error'] = 'Error updating settings: ' . $e->getMessage();
        }
    }

    // Redirect to refresh the page and avoid resubmission
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}
// Get current SMTP settings from config.php constants
// These will be displayed in the form
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Settings - Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@xz/fonts@1/serve/jetbrains-mono.min.css">
    <style>
        body {
            font-family: 'JetBrains Mono', monospace;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
<!-- Top navigation -->
<header class="bg-black text-white p-4">
    <div class="container mx-auto flex justify-between items-center">
        <h1 class="text-xl font-bold">Dodgeball Whitelist Admin</h1>
        <div class="flex items-center space-x-4">
            <span><?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
            <a href="/admin/logout" class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded text-sm">Logout</a>
        </div>
    </div>
</header>

<!-- Main content -->
<div class="container mx-auto p-4">
    <!-- Breadcrumb navigation -->
    <div class="mb-6">
        <nav class="flex" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3">
                <li class="inline-flex items-center">
                    <a href="/admin/dashboard" class="text-gray-700 hover:text-blue-600">
                        Dashboard
                    </a>
                </li>
                <li aria-current="page">
                    <div class="flex items-center">
                        <span class="mx-2 text-gray-400">/</span>
                        <span class="text-gray-500">Email Settings</span>
                    </div>
                </li>
            </ol>
        </nav>
    </div>

    <?php if (isset($_SESSION['admin_success'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?php
            echo $_SESSION['admin_success'];
            unset($_SESSION['admin_success']);
            ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['admin_error'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?php
            echo $_SESSION['admin_error'];
            unset($_SESSION['admin_error']);
            ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['admin_warning'])): ?>
        <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-4">
            <div class="flex">
                <div class="py-1 mr-2">
                    <svg class="h-6 w-6 text-yellow-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <div>
                    <?php echo $_SESSION['admin_warning']; ?>
                </div>
            </div>
        </div>
        <?php unset($_SESSION['admin_warning']); ?>
    <?php endif; ?>

    <!-- PHPMailer Status Panel -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-6 col-span-1">
        <h2 class="text-xl font-bold mb-4">PHPMailer Status</h2>

        <?php if ($phpmailer_installed): ?>
            <div class="bg-green-100 text-green-700 p-4 rounded mb-4 flex items-start">
                <svg class="h-6 w-6 text-green-500 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <div>
                    <p class="font-semibold">PHPMailer is installed and available.</p>
                    <p class="text-sm mt-1">Your system will use PHPMailer to send emails.</p>

                    <?php if (defined('SMTP_HOST') && !empty(SMTP_HOST)): ?>
                        <p class="text-sm mt-1">SMTP is configured to use: <?php echo SMTP_HOST; ?> (Port: <?php echo SMTP_PORT; ?>)</p>
                    <?php else: ?>
                        <p class="text-sm mt-1 text-yellow-600">SMTP settings are not configured yet. Please configure below.</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-yellow-100 text-yellow-700 p-4 rounded mb-4 flex items-start">
                <svg class="h-6 w-6 text-yellow-500 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <div>
                    <p class="font-semibold">PHPMailer is not installed or not properly loaded.</p>
                    <p class="text-sm mt-1">Your system will fall back to PHP's basic mail() function, which may have delivery issues.</p>

                    <form method="POST" class="mt-3">
                        <button type="submit" name="install_phpmailer" class="bg-blue-500 hover:bg-blue-600 text-white py-1 px-3 rounded text-sm">
                            Install PHPMailer
                        </button>
                    </form>

                    <p class="text-xs mt-2 text-gray-500">Note: This will attempt to install PHPMailer in the root directory of your project.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Email Settings Panel -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- SMTP Settings Form -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h2 class="text-xl font-bold mb-4">SMTP Settings</h2>

            <form method="POST">
                <div class="mb-4">
                    <label for="smtp_host" class="block text-sm font-medium text-gray-700 mb-1">SMTP Host</label>
                    <input type="text" id="smtp_host" name="smtp_host" value="<?php echo defined('SMTP_HOST') ? SMTP_HOST : ''; ?>"
                           class="w-full p-2 border rounded">
                </div>

                <div class="mb-4">
                    <label for="smtp_port" class="block text-sm font-medium text-gray-700 mb-1">SMTP Port</label>
                    <input type="number" id="smtp_port" name="smtp_port" value="<?php echo defined('SMTP_PORT') ? SMTP_PORT : 587; ?>"
                           class="w-full p-2 border rounded">
                    <p class="text-xs text-gray-500 mt-1">Common ports: 25, 465 (SSL), 587 (TLS)</p>
                </div>

                <div class="mb-4">
                    <label for="smtp_secure" class="block text-sm font-medium text-gray-700 mb-1">Encryption</label>
                    <select id="smtp_secure" name="smtp_secure" class="w-full p-2 border rounded">
                        <option value="" <?php echo (!defined('SMTP_SECURE') || SMTP_SECURE === '') ? 'selected' : ''; ?>>None</option>
                        <option value="ssl" <?php echo (defined('SMTP_SECURE') && SMTP_SECURE === 'ssl') ? 'selected' : ''; ?>>SSL</option>
                        <option value="tls" <?php echo (defined('SMTP_SECURE') && SMTP_SECURE === 'tls') ? 'selected' : ''; ?>>TLS</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label for="smtp_user" class="block text-sm font-medium text-gray-700 mb-1">SMTP Username</label>
                    <input type="text" id="smtp_user" name="smtp_user" value="<?php echo defined('SMTP_USERNAME') ? SMTP_USERNAME : ''; ?>"
                           class="w-full p-2 border rounded">
                </div>

                <div class="mb-4">
                    <label for="smtp_pass" class="block text-sm font-medium text-gray-700 mb-1">SMTP Password</label>
                    <input type="password" id="smtp_pass" name="smtp_pass" value="<?php echo defined('SMTP_PASSWORD') ? SMTP_PASSWORD : ''; ?>"
                           class="w-full p-2 border rounded">
                </div>

                <div class="mb-4">
                    <label class="flex items-center">
                        <input type="checkbox" name="smtp_auth" <?php echo (!defined('SMTP_AUTH') || SMTP_AUTH) ? 'checked' : ''; ?>
                               class="h-4 w-4 text-blue-600 border-gray-300 rounded">
                        <span class="ml-2 text-sm text-gray-700">Use SMTP Authentication</span>
                    </label>
                </div>

                <div class="mb-4">
                    <label for="email_from" class="block text-sm font-medium text-gray-700 mb-1">From Email</label>
                    <input type="email" id="email_from" name="email_from" value="<?php echo defined('EMAIL_FROM') ? EMAIL_FROM : ''; ?>"
                           class="w-full p-2 border rounded">
                </div>

                <div class="mb-6">
                    <label for="email_name" class="block text-sm font-medium text-gray-700 mb-1">From Name</label>
                    <input type="text" id="email_name" name="email_name" value="<?php echo defined('EMAIL_FROM_NAME') ? EMAIL_FROM_NAME : ''; ?>"
                           class="w-full p-2 border rounded">
                </div>

                <button type="submit" name="save_smtp" class="w-full bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">
                    Save SMTP Settings
                </button>
            </form>
        </div>

        <!-- Verification Email Settings Form -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h2 class="text-xl font-bold mb-4">Verification Email Settings</h2>

            <form method="POST">
                <div class="mb-4">
                    <label for="verification_code_length" class="block text-sm font-medium text-gray-700 mb-1">Verification Code Length</label>
                    <input type="number" id="verification_code_length" name="verification_code_length" min="4" max="10" value="<?php echo defined('VERIFICATION_CODE_LENGTH') ? VERIFICATION_CODE_LENGTH : 6; ?>"
                           class="w-full p-2 border rounded">
                    <p class="text-xs text-gray-500 mt-1">Number of digits in verification code (4-10)</p>
                </div>

                <div class="mb-4">
                    <label for="verification_email_subject" class="block text-sm font-medium text-gray-700 mb-1">Email Subject</label>
                    <input type="text" id="verification_email_subject" name="verification_email_subject" value="<?php echo defined('VERIFICATION_EMAIL_SUBJECT') ? htmlspecialchars(VERIFICATION_EMAIL_SUBJECT) : 'Dodgeball Whitelist - Email Verification'; ?>"
                           class="w-full p-2 border rounded">
                </div>

                <div class="mb-4">
                    <div class="flex justify-between items-center mb-1">
                        <label for="verification_email_template" class="block text-sm font-medium text-gray-700">Email Template (HTML)</label>
                        <div>
                            <button type="button" id="insertPlainTemplate" class="text-sm text-blue-600 hover:text-blue-800">Insert Plain Template</button>
                            <button type="button" id="insertFancyTemplate" class="text-sm text-blue-600 hover:text-blue-800 ml-3">Insert HTML Template</button>
                        </div>
                    </div>
                    <textarea id="verification_email_template" name="verification_email_template" rows="15"
                              class="w-full p-2 border rounded font-mono text-sm"><?php echo function_exists('getVerificationEmailTemplate') ? htmlspecialchars(getVerificationEmailTemplate()) : ''; ?></textarea>
                    <p class="text-xs text-gray-500 mt-1">Use <code>{verificationCode}</code> as a placeholder for the verification code. For best compatibility with all email clients, consider using a simpler template.</p>
                </div>

                <div class="mb-4">
                    <h3 class="font-bold mb-2">Template Preview</h3>
                    <div class="border rounded p-4 bg-gray-50">
                        <div class="flex justify-between mb-2">
                            <span class="text-sm font-medium">Subject: <?php echo defined('VERIFICATION_EMAIL_SUBJECT') ? htmlspecialchars(VERIFICATION_EMAIL_SUBJECT) : 'Dodgeball Whitelist - Email Verification'; ?></span>
                            <button type="button" id="refreshPreview" class="text-blue-600 hover:text-blue-800 text-sm">Refresh Preview</button>
                        </div>
                        <div class="border bg-white p-2 h-64 overflow-auto">
                            <iframe id="templatePreview" class="w-full h-full" frameborder="0"></iframe>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Note: Email clients display HTML differently. Some may show a simplified version.</p>
                </div>

                <button type="submit" name="save_verification_settings" class="w-full bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">
                    Save Verification Email Settings
                </button>
            </form>
        </div>

        <!-- Send Test Email Form -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h2 class="text-xl font-bold mb-4">Send Test Email</h2>

            <form method="POST">
                <div class="mb-6">
                    <label for="test_email" class="block text-sm font-medium text-gray-700 mb-1">Recipient Email</label>
                    <input type="email" id="test_email" name="test_email"
                           class="w-full p-2 border rounded">
                    <p class="text-xs text-gray-500 mt-1">Enter your email address to receive a test message</p>
                </div>

                <button type="submit" name="send_test" class="w-full bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded">
                    Send Test Email
                </button>
            </form>

            <div class="mt-8">
                <h3 class="font-bold mb-2">Email Logs</h3>
                <div class="flex space-x-4 mt-2">
                    <a href="/admin/view-logs?file=email.log" class="text-blue-600 hover:underline">View Success Log</a>
                    <a href="/admin/view-logs?file=email_error.log" class="text-red-600 hover:underline">View Error Log</a>
                </div>
            </div>

            <!-- PHPMailer Information -->
            <?php if ($phpmailer_installed): ?>
                <div class="mt-6 p-4 bg-gray-50 rounded border">
                    <h3 class="font-bold mb-2">PHPMailer Configuration</h3>
                    <ul class="list-disc list-inside text-sm">
                        <li>PHPMailer is <?php echo $phpmailer_installed ? 'installed' : 'not installed'; ?></li>
                        <li>PHP Version: <?php echo phpversion(); ?></li>
                        <li>Server: <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></li>
                        <li>
                            SMTP Settings:
                            <?php if (defined('SMTP_HOST') && !empty(SMTP_HOST)): ?>
                                <?php echo SMTP_HOST . ':' . SMTP_PORT; ?>
                                (<?php echo defined('SMTP_SECURE') && !empty(SMTP_SECURE) ? strtoupper(SMTP_SECURE) : 'No encryption'; ?>)
                            <?php else: ?>
                                Not configured
                            <?php endif; ?>
                        </li>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Additional Information -->
    <div class="bg-white p-6 rounded-lg shadow-md mt-6">
        <h2 class="text-xl font-bold mb-4">Common SMTP Settings</h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="p-4 bg-gray-50 rounded">
                <h3 class="font-bold mb-2">Gmail</h3>
                <ul class="list-disc list-inside text-sm">
                    <li>SMTP Host: smtp.gmail.com</li>
                    <li>SMTP Port: 587</li>
                    <li>Encryption: TLS</li>
                    <li>Username: your.email@gmail.com</li>
                    <li>Password: Your app password</li>
                    <li class="text-xs mt-2 text-red-600">Note: You may need to enable "Less secure app access" or create an App Password if you use 2FA</li>
                </ul>
            </div>

            <div class="p-4 bg-gray-50 rounded">
                <h3 class="font-bold mb-2">cPanel / Webmail</h3>
                <ul class="list-disc list-inside text-sm">
                    <li>SMTP Host: mail.yourdomain.com</li>
                    <li>SMTP Port: 587 or 465</li>
                    <li>Encryption: TLS or SSL</li>
                    <li>Username: your@yourdomain.com</li>
                    <li>Password: Your email password</li>
                    <li class="text-xs mt-2">Ask your hosting provider for specific settings if needed</li>
                </ul>
            </div>

            <div class="p-4 bg-gray-50 rounded">
                <h3 class="font-bold mb-2">Office 365</h3>
                <ul class="list-disc list-inside text-sm">
                    <li>SMTP Host: smtp.office365.com</li>
                    <li>SMTP Port: 587</li>
                    <li>Encryption: TLS</li>
                    <li>Username: your@office365domain.com</li>
                    <li>Password: Your email password</li>
                </ul>
            </div>
        </div>

        <div class="mt-6">
            <h3 class="font-bold mb-2">Troubleshooting Tips</h3>
            <ul class="list-disc list-inside">
                <li>Make sure your hosting provider allows outgoing SMTP connections</li>
                <li>Some hosts block specific ports (25, 465, 587) for shared hosting</li>
                <li>Check your email error logs if you're having issues sending emails</li>
                <li>Try using your hosting provider's SMTP server if external services don't work</li>
                <li>Make sure your firewall or security settings aren't blocking SMTP connections</li>
            </ul>
        </div>
    </div>
</div>
</body>
</html>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Function to update the preview
        function updatePreview() {
            const template = document.getElementById('verification_email_template').value;
            const iframe = document.getElementById('templatePreview');
            const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;

            // Replace placeholder with sample code
            let previewHtml = template.replace('{verificationCode}', '123456');

            iframeDoc.open();
            iframeDoc.write(previewHtml);
            iframeDoc.close();
        }

        // Update preview on load
        updatePreview();

        // Update preview when refresh button is clicked
        document.getElementById('refreshPreview').addEventListener('click', updatePreview);

        // Also update when template changes (optional, can be resource intensive)
        document.getElementById('verification_email_template').addEventListener('blur', updatePreview);

        // Insert plain text template
        document.getElementById('insertPlainTemplate').addEventListener('click', function() {
            if (confirm('This will replace your current template. Continue?')) {
                document.getElementById('verification_email_template').value =
                    `Your verification code for Dodgeball Whitelist is: {verificationCode}

This code will expire in 30 minutes.

If you did not request this code, please ignore this email.

--
Dodgeball Whitelist Team`;
                updatePreview();
            }
        });

        // Insert fancy HTML template
        document.getElementById('insertFancyTemplate').addEventListener('click', function() {
            if (confirm('This will replace your current template. Continue?')) {
                document.getElementById('verification_email_template').value =
                    `<!DOCTYPE html>
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
            <p>Thank you for your interest in joining our Dodgeball server.</p>
            <p>Please use the following verification code to verify your email address:</p>
            <div style="font-size: 24px; letter-spacing: 5px; text-align: center; padding: 15px; background-color: #eee; margin: 20px 0; font-weight: bold;">{verificationCode}</div>
            <p>This code will expire in 30 minutes.</p>
            <p>If you did not request this code, please ignore this email.</p>
        </div>
        <div style="padding: 20px; text-align: center; font-size: 12px; color: #777;">
            <p>This is an automated message, please do not reply to this email.</p>
        </div>
    </div>
</body>
</html>`;
                updatePreview();
            }
        });
    });
</script>