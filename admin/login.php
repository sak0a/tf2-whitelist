<?php
// Start session
session_start();

// Include configuration
require_once '../config.php';

// If already logged in, redirect to dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}

// Check for login attempt
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    // Validate credentials
    $error = '';

    if (empty($username) || empty($password)) {
        $error = 'Both username and password are required';
    } else {
        try {
            $pdo = getDatabaseConnection();

            // Get admin record
            $stmt = $pdo->prepare("SELECT id, username, password_hash, role, email FROM admins WHERE username = :username");
            $stmt->execute([':username' => $username]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$admin) {
                // Use intentionally vague error message to prevent user enumeration
                $error = 'Invalid username or password';
                logMessage('admin_auth.log', "Failed login attempt for unknown username: $username from IP: {$_SERVER['REMOTE_ADDR']}");
            } else {
                // Verify password (using password_verify if bcrypt is used)
                if (function_exists('password_verify') && password_verify($password, $admin['password_hash'])) {
                    // Password is correct, login successful
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_username'] = $admin['username'];
                    $_SESSION['admin_role'] = $admin['role'];
                    $_SESSION['admin_email'] = $admin['email'];
                    $_SESSION['admin_last_activity'] = time();

                    // Update last login time
                    $update_stmt = $pdo->prepare("UPDATE admins SET last_login = NOW() WHERE id = :id");
                    $update_stmt->execute([':id' => $admin['id']]);

                    // Log successful login
                    logMessage('admin_auth.log', "Admin login successful: {$admin['username']} (ID: {$admin['id']}, Role: {$admin['role']}) from IP: {$_SERVER['REMOTE_ADDR']}");

                    // Redirect to dashboard
                    header('Location: dashboard.php');
                    exit;
                } else {
                    // Legacy password verification (if not using bcrypt)
                    // This is a simplified example - in real life you would use secure password hashing
                    if ($admin['password_hash'] === $password) {
                        // Password is correct, login successful
                        $_SESSION['admin_logged_in'] = true;
                        $_SESSION['admin_id'] = $admin['id'];
                        $_SESSION['admin_username'] = $admin['username'];
                        $_SESSION['admin_role'] = $admin['role'];
                        $_SESSION['admin_email'] = $admin['email'];
                        $_SESSION['admin_last_activity'] = time();

                        // Update last login time
                        $update_stmt = $pdo->prepare("UPDATE admins SET last_login = NOW() WHERE id = :id");
                        $update_stmt->execute([':id' => $admin['id']]);

                        // Log successful login
                        logMessage('admin_auth.log', "Admin login successful: {$admin['username']} (ID: {$admin['id']}, Role: {$admin['role']}) from IP: {$_SERVER['REMOTE_ADDR']}");

                        // Redirect to dashboard
                        header('Location: dashboard.php');
                        exit;
                    } else {
                        $error = 'Invalid username or password';
                        logMessage('admin_auth.log', "Failed login attempt for username: $username (incorrect password) from IP: {$_SERVER['REMOTE_ADDR']}");
                    }
                }
            }
        } catch (PDOException $e) {
            $error = 'Database error occurred. Please try again.';
            logMessage('admin_error.log', "Database error during login: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Dodgeball Whitelist</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@xz/fonts@1/serve/jetbrains-mono.min.css">
    <style>
        body {
            font-family: 'JetBrains Mono', monospace;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
<div class="w-full max-w-md">
    <!-- Logo/Header -->
    <div class="text-center mb-6">
        <h1 class="text-4xl font-bold">Dodgeball Whitelist</h1>
        <p class="text-gray-600 mt-2">Admin Panel</p>
    </div>

    <!-- Login Form Card -->
    <div class="bg-white rounded-lg shadow-lg overflow-hidden">
        <div class="bg-black p-4 text-white text-center">
            <h2 class="text-xl font-semibold">Administrator Login</h2>
        </div>

        <div class="p-6">
            <?php if (isset($error) && !empty($error)): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
                    <p><?php echo htmlspecialchars($error); ?></p>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['admin_error'])): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
                    <p><?php echo htmlspecialchars($_SESSION['admin_error']); ?></p>
                    <?php unset($_SESSION['admin_error']); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <div class="mb-4">
                    <label for="username" class="block text-gray-700 font-bold mb-2">Username</label>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                        required
                        value="<?php echo isset($username) ? htmlspecialchars($username) : ''; ?>"
                    >
                </div>

                <div class="mb-6">
                    <label for="password" class="block text-gray-700 font-bold mb-2">Password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                        required
                    >
                </div>

                <div class="flex items-center justify-between">
                    <button
                        type="submit"
                        class="bg-black hover:bg-gray-800 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline w-full"
                    >
                        Sign In
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="text-center mt-6 text-gray-600 text-sm">
        <p>If you need help accessing your account, please contact the system administrator.</p>
        <p class="mt-2">© <?php echo date('Y'); ?> Dodgeball Whitelist System</p>
    </div>
</div>
</body>
</html>