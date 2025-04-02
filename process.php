<?php
// Start session for handling form data across steps
session_start();

// Include configuration
require_once 'config.php';

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // At the beginning of process.php after session_start():
    $debugLog = "Form submission received: " . date('Y-m-d H:i:s') . "\n";
    $debugLog .= "POST data: " . print_r($_POST, true) . "\n";
    $debugLog .= "SESSION data: " . print_r($_SESSION, true) . "\n";
    logMessage('form_debug.log', $debugLog);
    // Collect all form data
    $formData = [
        // Authentication Information
        'discord_verified' => isset($_POST['discord_verified']) ? true : false,
        'verified_email' => htmlspecialchars($_POST['verified_email'] ?? ''),
        'steam_url' => htmlspecialchars($_POST['steam_url'] ?? ''),
        'steam_id3' => htmlspecialchars($_POST['steam_id3'] ?? ''),

        // Account History
        'main_account' => htmlspecialchars($_POST['main_account'] ?? ''),
        'other_accounts' => htmlspecialchars($_POST['other_accounts'] ?? ''),
        'vac_ban' => htmlspecialchars($_POST['vac_ban'] ?? ''),
        'vac_ban_reason' => htmlspecialchars($_POST['vac_ban_reason'] ?? ''),

        // Additional Information
        'referral' => htmlspecialchars($_POST['referral'] ?? ''),
        'experience' => htmlspecialchars($_POST['experience'] ?? ''),
        'comments' => htmlspecialchars($_POST['comments'] ?? ''),
        'agreements' => isset($_POST['agreements']) ? $_POST['agreements'] : []
    ];

    // Validate required fields
    $requiredFields = [
        'steam_url',
        'steam_id3',
        'main_account',
        'vac_ban'
    ];

    $errors = [];
    foreach ($requiredFields as $field) {
        if (empty($formData[$field])) {
            $errors[] = "The field '$field' is required.";
        }
    }

    // Verify authentication was completed
    if (!isset($_SESSION['steam_verified']) || $_SESSION['steam_verified'] !== true) {
        $errors[] = "Steam authentication is required.";
    }

    if ((!isset($_SESSION['discord_verified']) || $_SESSION['discord_verified'] !== true) &&
        (!isset($_SESSION['email_verified']) || $_SESSION['email_verified'] !== true)) {
        $errors[] = "Either Discord authentication or email verification is required.";
    }

    // Validate conditional required fields
    if ($formData['main_account'] == 'no' && empty($formData['other_accounts'])) {
        $errors[] = "Please list your other accounts.";
    }

    if ($formData['vac_ban'] == 'yes' && empty($formData['vac_ban_reason'])) {
        $errors[] = "Please provide the reason for your VAC ban.";
    }

    // Check agreements
    $requiredAgreements = ['privacy', 'truthful', 'guarantee'];
    foreach ($requiredAgreements as $agreement) {
        if (!in_array($agreement, $formData['agreements'])) {
            $errors[] = "You must agree to all terms to submit the form.";
            break;
        }
    }

    // If there are errors, redirect back to the form with error messages
    if (!empty($errors)) {
        $_SESSION['form_errors'] = $errors;
        $_SESSION['form_data'] = $formData;
        logMessage('form_errors.log', "Form submission errors: " . implode(', ', $errors));
        header("Location: index.php");
        exit;
    }

    // Process the valid form submission

    // Prepare data for database
    $submission = [
        // User identification info
        'steam_id' => $_SESSION['steam_id'] ?? '',
        'steam_id3' => $_SESSION['steam_id3'] ?? '',
        'steam_username' => $_SESSION['steam_username'] ?? '',
        'steam_profile' => $_SESSION['steam_profile'] ?? '',

        // Contact info
        'discord_id' => $_SESSION['discord_id'] ?? null,
        'discord_username' => $_SESSION['discord_username'] ?? null,
        'discord_email' => $_SESSION['discord_email'] ?? null,
        'email' => $_SESSION['verified_email'] ?? null,

        // Form data
        'main_account' => $formData['main_account'],
        'other_accounts' => $formData['other_accounts'],
        'vac_ban' => $formData['vac_ban'],
        'vac_ban_reason' => $formData['vac_ban_reason'],
        'referral' => $formData['referral'],
        'experience' => $formData['experience'],
        'comments' => $formData['comments'],

        // Metadata
        'ip_address' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT'],
        'submission_date' => date('Y-m-d H:i:s'),
        'status' => 'pending', // Initial status
    ];

    // Save to database
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            logMessage('database_error.log', "Failed to get database connection - connection returned null");
        } else {
            logMessage('database_success.log', "Database connection successful");
        }

        if ($pdo) {
            $stmt = $pdo->prepare("INSERT INTO whitelist_applications (
                steam_id, steam_id3, steam_username, steam_profile,
                discord_id, discord_username, discord_email, email,
                main_account, other_accounts, vac_ban, vac_ban_reason,
                referral, experience, comments, ip_address, user_agent, submission_date, status
            ) VALUES (
                :steam_id, :steam_id3, :steam_username, :steam_profile,
                :discord_id, :discord_username, :discord_email, :email,
                :main_account, :other_accounts, :vac_ban, :vac_ban_reason,
                :referral, :experience, :comments, :ip_address, :user_agent, :submission_date, :status
            )");

            $stmt->execute($submission);

            // Get the ID of the newly inserted record
            $application_id = $pdo->lastInsertId();
            $_SESSION['application_id'] = $application_id;

            // Log successful DB insertion
            logMessage('applications.log', "Application ID {$application_id} successfully stored in database");
        } else {
            // Fall back to file logging if database connection failed
            $log_file = LOG_DIRECTORY . 'applications.log';
            $log_dir = dirname($log_file);
            if (!is_dir($log_dir)) {
                mkdir($log_dir, 0755, true);
            }

            $log_data = "=== New Application " . date('Y-m-d H:i:s') . " ===\n";
            foreach ($submission as $key => $value) {
                if (is_array($value)) {
                    $value = implode(', ', $value);
                }
                $log_data .= "{$key}: {$value}\n";
            }
            $log_data .= "==========================================\n\n";

            file_put_contents($log_file, $log_data, FILE_APPEND);

            logMessage('database_error.log', "Used file logging as fallback because database connection failed");
        }
    } catch (PDOException $e) {
        // Log error and fall back to file logging
        logMessage('database_error.log', 'Database error: ' . $e->getMessage());

        $log_file = LOG_DIRECTORY . 'applications.log';
        $log_dir = dirname($log_file);
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }

        $log_data = "=== New Application " . date('Y-m-d H:i:s') . " ===\n";
        foreach ($submission as $key => $value) {
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            $log_data .= "{$key}: {$value}\n";
        }
        $log_data .= "==========================================\n\n";

        file_put_contents($log_file, $log_data, FILE_APPEND);
    }

    // Store success message and redirect to thank you page
    $_SESSION['form_success'] = true;

    // Clear authentication session data
    unset($_SESSION['steam_verified']);
    unset($_SESSION['discord_verified']);
    unset($_SESSION['email_verified']);
    unset($_SESSION['steam_id']);
    unset($_SESSION['steam_id3']);
    unset($_SESSION['steam_username']);
    unset($_SESSION['steam_profile']);
    unset($_SESSION['discord_id']);
    unset($_SESSION['discord_username']);
    unset($_SESSION['discord_email']);
    unset($_SESSION['verified_email']);

    // Redirect to thank you page
    header("Location: thank_you.php");
    exit;
} else {
    // If not POST request, redirect to the form
    header("Location: index.php");
    exit;
}
?>