<?php
// Start session
session_start();

// Include configuration and admin authentication check
require_once '../config.php';
require_once 'auth_check.php'; // This file should check if admin is logged in

// Validate the log file requested
$log_file = isset($_GET['file']) ? $_GET['file'] : '';
$log_file = basename($log_file); // Prevent directory traversal

// List of allowed log files
$allowed_logs = [
    'email.log',
    'email_error.log',
    'database_error.log',
    'admin_auth.log',
    'admin_error.log',
    'admin_security.log',
    'admin_activity.log',
    'applications.log',
    'steam_auth.log',
    'steam_debug.log',
    'discord_auth.log',
    'form_errors.log',
    'form_debug.log'
];

if (!in_array($log_file, $allowed_logs)) {
    $_SESSION['admin_error'] = 'Invalid log file requested.';
    header('Location: dashboard.php');
    exit;
}

// Get the full path to the log file
$log_path = LOG_DIRECTORY . $log_file;

// Check if the log file exists
if (!file_exists($log_path)) {
    $_SESSION['admin_warning'] = "Log file $log_file does not exist yet.";
    $log_content = "No entries found.";
} else {
    // Get the log file content
    $log_content = file_get_contents($log_path);

    // Safety check on file size
    if (filesize($log_path) > 1024 * 1024) { // 1MB
        $log_content = "Log file is too large to display in browser. Last 50 lines:\n\n" .
            implode("\n", array_slice(explode("\n", $log_content), -50));
    }
}

// Handle log clearing
if (isset($_GET['clear']) && $_GET['clear'] === 'yes') {
    // Clear the log file
    if (file_exists($log_path)) {
        file_put_contents($log_path, '');
        $_SESSION['admin_success'] = "Log file $log_file has been cleared.";
        header('Location: view_logs.php?file=' . $log_file);
        exit;
    }
}

// Handle log download
if (isset($_GET['download']) && $_GET['download'] === 'yes') {
    if (file_exists($log_path)) {
        // Set appropriate headers for file download
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="' . $log_file . '"');
        header('Content-Length: ' . filesize($log_path));

        // Output the file content
        readfile($log_path);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Log: <?php echo htmlspecialchars($log_file); ?> - Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@xz/fonts@1/serve/jetbrains-mono.min.css">
    <style>
        body {
            font-family: 'JetBrains Mono', monospace;
        }
        pre {
            white-space: pre-wrap;
            word-wrap: break-word;
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
            <a href="logout.php" class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded text-sm">Logout</a>
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
                    <a href="dashboard.php" class="text-gray-700 hover:text-blue-600">
                        Dashboard
                    </a>
                </li>
                <li>
                    <div class="flex items-center">
                        <span class="mx-2 text-gray-400">/</span>
                        <a href="email_test.php" class="text-gray-700 hover:text-blue-600">
                            Email Settings
                        </a>
                    </div>
                </li>
                <li aria-current="page">
                    <div class="flex items-center">
                        <span class="mx-2 text-gray-400">/</span>
                        <span class="text-gray-500">View Log: <?php echo htmlspecialchars($log_file); ?></span>
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

    <!-- Log view panel -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="bg-gray-50 px-6 py-4 border-b flex justify-between items-center">
            <h2 class="text-xl font-bold">Log File: <?php echo htmlspecialchars($log_file); ?></h2>
            <div class="flex space-x-2">
                <a href="view_logs.php?file=<?php echo urlencode($log_file); ?>&download=yes"
                   class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm">
                    Download
                </a>
                <a href="view_logs.php?file=<?php echo urlencode($log_file); ?>&clear=yes"
                   class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-sm"
                   onclick="return confirm('Are you sure you want to clear this log file?');">
                    Clear Log
                </a>
            </div>
        </div>

        <div class="p-6">
            <?php if (file_exists($log_path) && filesize($log_path) > 0): ?>
                <div class="bg-gray-100 p-4 rounded overflow-auto max-h-screen">
                    <pre class="text-sm text-gray-800"><?php echo htmlspecialchars($log_content); ?></pre>
                </div>
            <?php else: ?>
                <p class="text-gray-500 text-center py-4">The log file is empty or does not exist yet.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="mt-6">
        <a href="email_test.php" class="inline-block bg-gray-300 hover:bg-gray-400 text-gray-800 font-medium py-2 px-4 rounded">
            Back to Email Settings
        </a>
    </div>
</div>
</body>
</html>