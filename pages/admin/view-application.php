<?php
// Start session
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

// Process action if submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $admin_notes = isset($_POST['admin_notes']) ? trim($_POST['admin_notes']) : '';
    $admin_id = $_SESSION['admin_id'];

    try {
        $pdo = getDatabaseConnection();

        // Determine identifier type for this application
        $identifierType = null;
        $userIdentifier = null;
        if (!empty($app['steam_id'])) {
            $identifierType = 'steam_id';
            $userIdentifier = $app['steam_id'];
        } elseif (!empty($app['discord_id'])) {
            $identifierType = 'discord_id';
            $userIdentifier = $app['discord_id'];
        } elseif (!empty($app['email'])) {
            $identifierType = 'email';
            $userIdentifier = $app['email'];
        }

        // Check if there's a newer submission
        $hasNewerSubmission = false;
        if ($userIdentifier && $identifierType) {
            $stmt = $pdo->prepare("SELECT id, submission_date FROM whitelist_applications 
                                  WHERE {$identifierType} = :identifier 
                                  AND submission_date > :curr_date 
                                  LIMIT 1");
            $stmt->execute([
                ':identifier' => $userIdentifier,
                ':curr_date' => $app['submission_date']
            ]);
            $hasNewerSubmission = ($stmt->fetch() !== false);
        }

        // Prevent modifying rejected/banned applications when newer ones exist
        if ($hasNewerSubmission && ($app['status'] === 'rejected' || $app['status'] === 'banned')) {
            $_SESSION['admin_error'] = 'Cannot modify this application as a newer submission exists';
            header("Location: /admin/view-application/$application_id");
            exit;
        }


        if ($action === 'save_notes') {
            // Update only the admin notes without changing status
            $stmt = $pdo->prepare("UPDATE whitelist_applications SET admin_notes = :notes, updated_at = NOW() WHERE id = :id");
            $stmt->execute([
                ':notes' => $admin_notes,
                ':id' => $application_id
            ]);

            // Log the action
            $stmt = $pdo->prepare("INSERT INTO activity_log (admin_id, application_id, action, details, ip_address, timestamp) 
                                  VALUES (:admin_id, :app_id, 'update_notes', :details, :ip, NOW())");
            $stmt->execute([
                ':admin_id' => $admin_id,
                ':app_id' => $application_id,
                ':details' => "Admin notes updated",
                ':ip' => $_SERVER['REMOTE_ADDR']
            ]);

            $_SESSION['admin_success'] = "Notes updated successfully";

            // Reload the current page to show the updated notes
            header("Location: /admin/view-application/$application_id?updated=1");
            exit;
        } else {
            // Update application status
            $stmt = $pdo->prepare("UPDATE whitelist_applications SET status = :status, admin_notes = :notes, updated_at = NOW() WHERE id = :id");
            $stmt->execute([
                ':status' => $action,
                ':notes' => $admin_notes,
                ':id' => $application_id
            ]);

            // Log the action
            $details = "Status changed to: $action";
            if (!empty($admin_notes)) {
                $details .= " | Notes: $admin_notes";
            }

            $stmt = $pdo->prepare("INSERT INTO activity_log (admin_id, application_id, action, details, ip_address, timestamp) 
                              VALUES (:admin_id, :app_id, :action, :details, :ip, NOW())");
            $stmt->execute([
                ':admin_id' => $admin_id,
                ':app_id' => $application_id,
                ':action' => $action,
                ':details' => $details,
                ':ip' => $_SERVER['REMOTE_ADDR']
            ]);

            $_SESSION['admin_success'] = "Application $action successfully";

            // Redirect back to application list or stay on the same page
            if (isset($_POST['redirect']) && $_POST['redirect'] === 'list') {
                header('Location: /admin/applications');
                exit;
            }

            // Reload the current page to show the updated status
            header("Location: /admin/view-application/$application_id?updated=1");
            exit;
        }
    } catch (PDOException $e) {
        $_SESSION['admin_error'] = "Error updating application: " . $e->getMessage();
        logMessage('admin_error.log', "Error updating application $application_id: " . $e->getMessage());
    }
}

// Fetch application data
$application = null;
$activityLogs = [];

try {
    $pdo = getDatabaseConnection();

    // Get application details
    $stmt = $pdo->prepare("SELECT * FROM whitelist_applications WHERE id = :id");
    $stmt->execute([':id' => $application_id]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$application) {
        $_SESSION['admin_error'] = 'Application not found';
        header('Location: /admin/applications');
        exit;
    }

    // Get activity logs for this application
    $stmt = $pdo->prepare("
    SELECT al.*, a.username as admin_name
    FROM activity_log al
    LEFT JOIN admins a ON al.admin_id = a.id
    WHERE al.application_id = :app_id
    ORDER BY al.timestamp DESC
");
    $stmt->execute([':app_id' => $application_id]);
    $activityLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $_SESSION['admin_error'] = "Database error: " . $e->getMessage();
    logMessage('admin_error.log', "Database error fetching application $application_id: " . $e->getMessage());
    header('Location: /admin/applications');
    exit;
}

// THIS IS WHERE YOU SHOULD ADD THE USER SUBMISSION HISTORY CODE
// Insert the code from the view-application-enhancement artifact here
// Get user identifier from current application to find related submissions
$userIdentifier = null;
$identifierType = null;
$submissionsCount = 0;
$allUserSubmissions = [];
$hasNewerSubmission = false;

// Determine which identifier to use (steam_id, discord_id, or email)
if (!empty($application['steam_id'])) {
    $userIdentifier = $application['steam_id'];
    $identifierType = 'steam_id';
} elseif (!empty($application['discord_id'])) {
    $userIdentifier = $application['discord_id'];
    $identifierType = 'discord_id';
} elseif (!empty($application['email'])) {
    $userIdentifier = $application['email'];
    $identifierType = 'email';
}

// If we have a valid identifier, get all submissions from this user
if ($userIdentifier && $identifierType) {
    try {
        // Get all submissions from this user
        $stmt = $pdo->prepare("SELECT id, submission_date, status, updated_at 
                              FROM whitelist_applications 
                              WHERE {$identifierType} = :identifier
                              ORDER BY submission_date DESC");
        $stmt->execute([':identifier' => $userIdentifier]);
        $allUserSubmissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Count total submissions
        $submissionsCount = count($allUserSubmissions);

        // Check if there's a newer submission after this one
        if ($submissionsCount > 1) {
            foreach ($allUserSubmissions as $submission) {
                if ($submission['id'] != $application_id &&
                    strtotime($submission['submission_date']) > strtotime($application['submission_date'])) {
                    $hasNewerSubmission = true;
                    break;
                }
            }
        }
    } catch (PDOException $e) {
        logMessage('admin_error.log', "Error fetching user submission history: " . $e->getMessage());
    }
}

// Helper function to get status badge class


// Determine if controls should be disabled
$disableControls = $hasNewerSubmission && ($application['status'] === 'rejected' || $application['status'] === 'banned');


// Helper function to format dates
function formatDate($dateString) {
    return date('M j, Y g:i A', strtotime($dateString));
}

// Status badge CSS classes
$statusBadges = [
    'pending' => 'bg-yellow-100 text-yellow-800',
    'approved' => 'bg-green-100 text-green-800',
    'rejected' => 'bg-red-100 text-red-800',
    'banned' => 'bg-black text-white'
];
function getStatusBadgeClass($status) {
    return $statusBadges[$status] ?? 'bg-gray-100 text-gray-800';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Application - Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@xz/fonts@1/serve/jetbrains-mono.min.css">
    <style>
        body {
            font-family: 'JetBrains Mono', monospace;
        }
        /* SQL Script Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }
        .modal-content {
            position: relative;
            background-color: #fefefe;
            margin: 10% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 800px;
            border-radius: 5px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .close-modal {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
        }
        .close-modal:hover,
        .close-modal:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }
        .sql-container {
            background-color: #f0f0f0;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 10px;
            margin: 15px 0;
            overflow-x: auto;
            white-space: pre;
            font-size: 14px;
        }
        .copy-message {
            background-color: #e6ffe6;
            color: #006600;
            padding: 5px 10px;
            border-radius: 4px;
            margin-bottom: 10px;
            display: none;
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
                <li>
                    <div class="flex items-center">
                        <span class="mx-2 text-gray-400">/</span>
                        <a href="/admin/applications" class="text-gray-700 hover:text-blue-600">
                            Applications
                        </a>
                    </div>
                </li>
                <li aria-current="page">
                    <div class="flex items-center">
                        <span class="mx-2 text-gray-400">/</span>
                        <span class="text-gray-500">Application #<?php echo $application_id; ?></span>
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

    <!-- Application data -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <!-- Left column: Application details -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="flex justify-between items-center border-b px-6 py-4">
                    <h2 class="text-xl font-bold">Application Details</h2>
                    <span class="px-3 py-1 rounded-full text-sm font-semibold <?php echo $statusBadges[$application['status']]; ?>">
                            <?php echo ucfirst($application['status']); ?>
                        </span>
                </div>

                <div class="p-6">
                    <!-- Steam information -->
                    <div class="mb-6">
                        <h3 class="text-lg font-bold mb-2 border-b pb-2">Steam Account</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-gray-600">Username</p>
                                <p class="font-medium"><?php echo htmlspecialchars($application['steam_username']); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Steam ID</p>
                                <p class="font-medium"><?php echo htmlspecialchars($application['steam_id']); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Steam ID3</p>
                                <p class="font-medium"><?php echo htmlspecialchars($application['steam_id3']); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Profile</p>
                                <p class="font-medium">
                                    <a href="<?php echo htmlspecialchars($application['steam_profile']); ?>"
                                       target="_blank"
                                       class="text-blue-600 hover:underline">
                                        View Profile
                                    </a>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Contact information -->
                    <div class="mb-6">
                        <h3 class="text-lg font-bold mb-2 border-b pb-2">Contact Information</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <?php if (!empty($application['discord_id'])): ?>
                                <div>
                                    <p class="text-sm text-gray-600">Discord Username</p>
                                    <p class="font-medium"><?php echo htmlspecialchars($application['discord_username'] ?? 'N/A'); ?></p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600">Discord ID</p>
                                    <p class="font-medium"><?php echo htmlspecialchars($application['discord_id']); ?></p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600">Discord Email</p>
                                    <p class="font-medium"><?php echo htmlspecialchars($application['discord_email'] ?? 'N/A'); ?></p>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($application['email'])): ?>
                                <div>
                                    <p class="text-sm text-gray-600">Email Address</p>
                                    <p class="font-medium"><?php echo htmlspecialchars($application['email']); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Account history -->
                    <div class="mb-6">
                        <h3 class="text-lg font-bold mb-2 border-b pb-2">Account History</h3>
                        <div class="space-y-4">
                            <div>
                                <p class="text-sm text-gray-600">Is this the main account?</p>
                                <p class="font-medium"><?php echo ucfirst($application['main_account']); ?></p>
                            </div>

                            <?php if ($application['main_account'] === 'no' && !empty($application['other_accounts'])): ?>
                                <div>
                                    <p class="text-sm text-gray-600">Other accounts</p>
                                    <p class="font-medium whitespace-pre-line"><?php echo htmlspecialchars($application['other_accounts']); ?></p>
                                </div>
                            <?php endif; ?>

                            <div>
                                <p class="text-sm text-gray-600">Has VAC ban?</p>
                                <p class="font-medium"><?php echo ucfirst($application['vac_ban']); ?></p>
                            </div>

                            <?php if ($application['vac_ban'] === 'yes' && !empty($application['vac_ban_reason'])): ?>
                                <div>
                                    <p class="text-sm text-gray-600">VAC ban reason</p>
                                    <p class="font-medium whitespace-pre-line"><?php echo htmlspecialchars($application['vac_ban_reason']); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Additional information -->
                    <div class="mb-6">
                        <h3 class="text-lg font-bold mb-2 border-b pb-2">Additional Information</h3>
                        <div class="space-y-4">
                            <?php if (!empty($application['referral'])): ?>
                                <div>
                                    <p class="text-sm text-gray-600">Referred by</p>
                                    <p class="font-medium"><?php echo htmlspecialchars($application['referral']); ?></p>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($application['experience'])): ?>
                                <div>
                                    <p class="text-sm text-gray-600">Dodgeball experience</p>
                                    <p class="font-medium">
                                        <?php
                                        $experience_levels = [
                                            '1' => 'Noob',
                                            '2' => 'Casual',
                                            '3' => 'Decent',
                                            '4' => 'Elite',
                                            '5' => 'Godlike'
                                        ];
                                        echo $experience_levels[$application['experience']] ?? 'Not specified';
                                        ?>
                                    </p>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($application['comments'])): ?>
                                <div>
                                    <p class="text-sm text-gray-600">Comments</p>
                                    <p class="font-medium whitespace-pre-line"><?php echo htmlspecialchars($application['comments']); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Meta information -->
                    <div>
                        <h3 class="text-lg font-bold mb-2 border-b pb-2">Submission Information</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-gray-600">Submission Date</p>
                                <p class="font-medium"><?php echo formatDate($application['submission_date']); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">IP Address</p>
                                <p class="font-medium"><?php echo htmlspecialchars($application['ip_address']); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Last Updated</p>
                                <p class="font-medium"><?php echo formatDate($application['updated_at']); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- User Submission History - Moved below application details -->
            <div class="bg-white rounded-lg shadow overflow-hidden mt-6">
                <div class="bg-gray-50 px-6 py-4 border-b flex justify-between items-center">
                    <h2 class="text-lg font-bold">User Submission History</h2>
                    <span class="bg-blue-100 text-blue-800 text-xs font-semibold px-2.5 py-0.5 rounded">
                    Total: <?php echo $submissionsCount; ?> submissions
                </span>
                </div>

                <div class="p-4">
                    <?php if (empty($allUserSubmissions)): ?>
                        <p class="text-gray-500 text-center py-4">No submission history found</p>
                    <?php else: ?>
                        <!-- Always use a fixed height container with scrolling -->
                        <div class="max-h-60 overflow-y-auto pr-2">
                            <!-- Timeline line -->
                            <div class="relative">
                                <div class="absolute left-4 h-full w-0.5 bg-gray-200"></div>

                                <?php foreach ($allUserSubmissions as $index => $submission): ?>
                                    <?php
                                    $isCurrent = ($submission['id'] == $application_id);
                                    $badgeClass = $statusBadges[$submission['status']];
                                    ?>
                                    <div class="relative pl-10 pb-5">
                                        <!-- Timeline dot -->
                                        <div class="absolute left-2 -translate-x-1/2 mt-1.5 w-5 h-5 rounded-full border-4 border-white
                                        <?php echo $isCurrent ? 'bg-blue-500' : 'bg-gray-300'; ?> z-10"></div>

                                        <!-- Submission card -->
                                        <div class="p-3 bg-white border rounded-lg shadow-sm
                                        <?php echo $isCurrent ? 'border-blue-300 ring-2 ring-blue-100' : ''; ?>">
                                            <div class="flex flex-wrap justify-between items-center mb-1">
                                                <div class="flex items-center">
                                                    <h4 class="font-medium">
                                                        <a href="/admin/view-application/<?php echo $submission['id']; ?>"
                                                           class="text-blue-600 hover:underline">
                                                            Application #<?php echo $submission['id']; ?>
                                                        </a>
                                                    </h4>
                                                    <?php if ($isCurrent): ?>
                                                        <span class="ml-2 text-xs bg-blue-100 text-blue-800 px-2 py-0.5 rounded">Current</span>
                                                    <?php endif; ?>
                                                    <span class="ml-2 text-xs <?php echo $badgeClass; ?> px-2 py-0.5 rounded">
                                                    <?php echo ucfirst($submission['status']); ?>
                                                </span>
                                                </div>
                                                <span class="text-xs text-gray-500">
                                                <?php echo date('M j, Y g:i A', strtotime($submission['submission_date'])); ?>
                                            </span>
                                            </div>
                                            <?php if (!empty($submission['updated_at']) && $submission['status'] !== 'pending'): ?>
                                                <p class="text-xs text-gray-500">
                                                    Updated: <?php echo date('M j, Y g:i A', strtotime($submission['updated_at'])); ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right column: Admin actions and notes -->
        <div>
            <!-- Admin actions -->
            <div class="bg-white rounded-lg shadow overflow-hidden mb-6">
                <div class="bg-gray-50 px-6 py-4 border-b">
                    <h2 class="text-lg font-bold">Admin Actions</h2>
                </div>

                <div class="p-6">
                    <form method="POST" action="/admin/view-application/<?php echo $application_id; ?>">

                        <?php if ($hasNewerSubmission && ($application['status'] === 'rejected' || $application['status'] === 'banned')): ?>
                            <div class="mb-4 p-3 bg-yellow-50 border border-yellow-300 rounded text-yellow-800">
                                <div class="flex items-start">
                                    <svg class="h-5 w-5 text-yellow-400 mr-2 mt-0.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                    <div>
                                        <p class="font-semibold">This is an older submission that has already been <?php echo $application['status']; ?>.</p>
                                        <p class="mt-1">The user has submitted a newer application. Actions on this submission have been disabled.</p>
                                        <p class="mt-1 text-sm">Please review the most recent submission instead.</p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Admin notes -->
                        <div class="mb-4">
                            <label for="admin_notes" class="block font-medium mb-2">Notes (visible to applicant)</label>
                            <textarea id="admin_notes" name="admin_notes" rows="5"
                                      class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500
                              <?php echo $disableControls ? 'bg-gray-100 cursor-not-allowed' : ''; ?>"
                              <?php echo $disableControls ? 'disabled' : ''; ?>
                    ><?php echo htmlspecialchars($application['admin_notes'] ?? ''); ?></textarea>
                        </div>

                        <!-- Save Notes button -->
                        <?php if (!$disableControls): ?>
                            <div class="mb-6">
                                <button type="submit" name="action" value="save_notes"
                                        class="w-full bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">
                                    Save Notes
                                </button>
                            </div>
                        <?php endif; ?>

                        <!-- Add these buttons to the Action buttons section in view_application.php -->
                        <!-- Replace the action buttons in view_application.php with this updated code -->

                        <!-- Action buttons -->
                        <?php if (!$disableControls): ?>
                            <div class="space-y-3">
                                <?php if ($application['status'] === 'pending'): ?>
                                    <button type="submit" name="action" value="approved"
                                            class="w-full bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded">
                                        Approve Application
                                    </button>

                                    <button type="submit" name="action" value="rejected"
                                            class="w-full bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded">
                                        Reject Application
                                    </button>

                                    <button type="submit" name="action" value="banned"
                                            class="w-full bg-black hover:bg-gray-800 text-white font-bold py-2 px-4 rounded">
                                        Ban User
                                    </button>
                                <?php elseif ($application['status'] === 'approved'): ?>
                                    <button type="submit" name="action" value="pending"
                                            class="w-full bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-2 px-4 rounded">
                                        Change to Pending
                                    </button>

                                    <button type="submit" name="action" value="rejected"
                                            class="w-full bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded">
                                        Change to Rejected
                                    </button>

                                    <button type="submit" name="action" value="banned"
                                            class="w-full bg-black hover:bg-gray-800 text-white font-bold py-2 px-4 rounded">
                                        Ban User
                                    </button>
                                <?php elseif ($application['status'] === 'rejected'): ?>
                                    <button type="submit" name="action" value="pending"
                                            class="w-full bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-2 px-4 rounded">
                                        Change to Pending
                                    </button>

                                    <button type="submit" name="action" value="approved"
                                            class="w-full bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded">
                                        Change to Approved
                                    </button>

                                    <button type="submit" name="action" value="banned"
                                            class="w-full bg-black hover:bg-gray-800 text-white font-bold py-2 px-4 rounded">
                                        Ban User
                                    </button>
                                <?php elseif ($application['status'] === 'banned'): ?>
                                    <button type="submit" name="action" value="pending"
                                            class="w-full bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-2 px-4 rounded">
                                        Unban and set Pending
                                    </button>

                                    <button type="submit" name="action" value="approved"
                                            class="w-full bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded">
                                        Unban and Approve
                                    </button>

                                    <button type="submit" name="action" value="rejected"
                                            class="w-full bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded">
                                        Unban and Reject
                                    </button>
                                <?php endif; ?>
                                <button type="button" id="generate-sql-btn"
                                        class="w-full bg-purple-500 hover:bg-purple-600 text-white font-bold py-2 px-4 rounded">
                                    Generate sakaStats SQL Script
                                </button>
                                <div id="sql-modal" class="modal">
                                    <div class="modal-content">
                                        <span class="close-modal">&times;</span>
                                        <h2 class="text-xl font-bold mb-3">sakaStats SQL Insert Script</h2>
                                        <div id="copy-message" class="copy-message">SQL script copied to clipboard!</div>
                                        <p class="mb-3">Copy and paste this SQL script to add the player data to the sakaStats database:</p>
                                        <div id="sql-script" class="sql-container"></div>
                                        <div class="flex justify-end mt-4">
                                            <button id="copy-sql-btn" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded mr-2">
                                                Copy to Clipboard
                                            </button>
                                            <button id="close-modal-btn" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded">
                                                Close
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>





                        <!-- Back to List button - always visible -->
                        <div class="<?php echo $disableControls ? '' : 'mt-3'; ?>">
                            <a href="/admin/applications" class="block text-center w-full bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded">
                                Back to List
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Activity logs -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="bg-gray-50 px-6 py-4 border-b">
                    <h2 class="text-lg font-bold">Activity Log</h2>
                    <?php if (!empty($activityLogs)): ?>
                        <a href="/admin/clear-logs?id=<?php echo $application_id; ?>"
                           class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-sm">
                            Clear Log
                        </a>
                    <?php endif; ?>
                </div>

                <div class="p-4">
                    <?php if (empty($activityLogs)): ?>
                        <p class="text-gray-500 text-center py-4">No activity recorded yet</p>
                    <?php else: ?>
                        <div class="space-y-4 max-h-96 overflow-y-auto">
                            <?php foreach ($activityLogs as $log): ?>
                                <div class="border-b pb-3 <?php echo ($log['action'] === 'clear-logs') ? 'bg-gray-50 italic' : ''; ?>">
                                    <div class="flex justify-between items-start mb-1">
                                        <span class="font-medium capitalize"><?php echo str_replace('_', ' ', $log['action']); ?></span>
                                        <span class="text-xs text-gray-500"><?php echo formatDate($log['timestamp']); ?></span>
                                    </div>
                                    <?php if (!empty($log['details'])): ?>
                                        <p class="text-sm text-gray-600"><?php echo htmlspecialchars($log['details']); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($log['admin_name'])): ?>
                                        <p class="text-xs text-gray-500 mt-1">By: <?php echo htmlspecialchars($log['admin_name']); ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
<script>
    // SQL Script Generation
    document.addEventListener('DOMContentLoaded', function() {
        console.log('SQL Script Generator initialized');

        // Get modal elements
        const modal = document.getElementById('sql-modal');
        const sqlScript = document.getElementById('sql-script');
        const closeSpan = document.getElementsByClassName('close-modal')[0];
        const copyBtn = document.getElementById('copy-sql-btn');
        const closeBtn = document.getElementById('close-modal-btn');
        const copyMessage = document.getElementById('copy-message');
        const generateBtn = document.getElementById('generate-sql-btn');

        if (!generateBtn) {
            console.error('Generate SQL button not found');
            return;
        }

        console.log('Generate SQL button found');

        // Application data from PHP - ensure proper escaping for JavaScript strings
        const appData = {
            id: <?php echo $application['id']; ?>,
            steamId: <?php echo json_encode($application['steam_id3'] ?? '[U:1:0]'); ?>,
            username: <?php echo json_encode($application['steam_username'] ?? 'Unknown'); ?>,
            discordUsername: <?php echo json_encode($application['discord_username'] ?? ''); ?>,
            email: <?php echo json_encode($application['email'] ?? ''); ?>
        };

        console.log('Application data loaded:', appData);

        // Generate SQL script
        function generateSQLScript() {
            // Build comment with user details
            let commentParts = [];
            if (appData.discordUsername) {
                commentParts.push(`Discord: ${appData.discordUsername}`);
            }
            if (appData.email) {
                commentParts.push(`Email: ${appData.email}`);
            }
            commentParts.push(`Application-ID: ${appData.id}`);

            const comment = commentParts.join('\\r\\n');

            // Create SQL script with proper escaping for SQL strings
            return `INSERT INTO \`sakaStats\` (\`steamid\`, \`name\`, \`kills\`, \`deaths\`, \`playtime\`, \`points\`, \`topspeed\`, \`deflections\`, \`manual-insert\`, \`comment\`) VALUES ('${appData.steamId}', '${appData.username.replace(/'/g, "''")}', '0', '0', '0', '1000', '0', '0', '1', '${comment.replace(/'/g, "''")}');`;
        }

        // Copy to clipboard function with fallback for older browsers
        function copyToClipboard(text) {
            if (navigator.clipboard && window.isSecureContext) {
                // Navigator clipboard API method
                navigator.clipboard.writeText(text).then(function() {
                    showCopySuccess();
                }, function(err) {
                    console.error('Could not copy text: ', err);
                    fallbackCopyTextToClipboard(text);
                });
            } else {
                // Fallback for older browsers
                fallbackCopyTextToClipboard(text);
            }
        }

        function fallbackCopyTextToClipboard(text) {
            // Create text area
            const textArea = document.createElement("textarea");
            textArea.value = text;

            // Make the textarea out of viewport
            textArea.style.position = "fixed";
            textArea.style.left = "-999999px";
            textArea.style.top = "-999999px";
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();

            try {
                const successful = document.execCommand('copy');
                if (successful) {
                    showCopySuccess();
                } else {
                    console.error('Failed to copy text with execCommand');
                    alert('Failed to copy. Please select the text and copy manually (Ctrl+C).');
                }
            } catch (err) {
                console.error('Failed to copy: ', err);
                alert('Failed to copy. Please select the text and copy manually (Ctrl+C).');
            }

            document.body.removeChild(textArea);
        }

        function showCopySuccess() {
            copyMessage.style.display = 'block';
            setTimeout(function() {
                copyMessage.style.display = 'none';
            }, 3000);
        }

        // Show modal when generate button is clicked
        generateBtn.addEventListener('click', function(e) {
            console.log('Generate SQL button clicked');
            e.preventDefault(); // Prevent form submission if button is inside a form
            const sql = generateSQLScript();
            console.log('Generated SQL:', sql);
            sqlScript.textContent = sql;
            modal.style.display = 'block';
        });

        // Copy SQL script to clipboard
        copyBtn.addEventListener('click', function() {
            console.log('Copy button clicked');
            copyToClipboard(sqlScript.textContent);
        });

        // Close modal when clicking the X
        closeSpan.addEventListener('click', function() {
            console.log('Close X clicked');
            modal.style.display = 'none';
        });

        // Close modal when clicking the Close button
        closeBtn.addEventListener('click', function() {
            console.log('Close button clicked');
            modal.style.display = 'none';
        });

        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target === modal) {
                console.log('Clicked outside modal');
                modal.style.display = 'none';
            }
        });

        // Prevent form submission when pressing the Generate SQL button
        if (generateBtn.form) {
            generateBtn.form.addEventListener('submit', function(e) {
                if (e.submitter === generateBtn) {
                    e.preventDefault();
                }
            });
        }

        console.log('SQL Script Generator setup completed');
    });
</script>
</html>