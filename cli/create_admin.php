<?php
// Include configuration
require_once '../config.php';

// Check if the script is being run from command line
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from the command line for security.');
}

// Prompt for admin details
echo "Create New Admin User\n";
echo "=====================\n\n";

$username = readline("Enter username: ");
$email = readline("Enter email: ");
$password = readline("Enter password: ");
$role = readline("Enter role (admin/moderator): ");

// Validate input
if (empty($username) || empty($email) || empty($password)) {
    die("Error: Username, email and password are required\n");
}

if ($role !== 'admin' && $role !== 'moderator') {
    $role = 'moderator'; // Default to moderator if input is invalid
}

try {
    // Connect to database
    $pdo = getDatabaseConnection();

    // Check if username already exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetchColumn() > 0) {
        die("Error: Username already exists\n");
    }

    // Check if email already exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetchColumn() > 0) {
        die("Error: Email already exists\n");
    }

    // Hash password
    $password_hash = password_hash($password, PASSWORD_BCRYPT);

    // Insert new admin
    $stmt = $pdo->prepare("INSERT INTO admins (username, password_hash, email, role, created_at) VALUES (?, ?, ?, ?, NOW())");
    $result = $stmt->execute([$username, $password_hash, $email, $role]);

    if ($result) {
        echo "\nSuccess! Admin user '{$username}' created with {$role} privileges.\n";
    } else {
        echo "\nError: Failed to create admin user.\n";
    }

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage() . "\n");
}
?>