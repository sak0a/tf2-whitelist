<?php
// Start session
session_start();

// Include configuration
require_once 'config.php';

// Check if user is authenticated
$authenticated = false;
$authMethod = '';

if (isset($_SESSION['steam_verified']) && $_SESSION['steam_verified'] === true) {
    $authenticated = true;
    $authMethod = 'steam';
    $userId = $_SESSION['steam_id'];
    $userIdentifier = $_SESSION['steam_username'];
} elseif (isset($_SESSION['discord_verified']) && $_SESSION['discord_verified'] === true) {
    $authenticated = true;
    $authMethod = 'discord';
    $userId = $_SESSION['discord_id'];
    $userIdentifier = $_SESSION['discord_username'];
} elseif (isset($_SESSION['email_verified']) && $_SESSION['email_verified'] === true) {
    $authenticated = true;
    $authMethod = 'email';
    $userId = $_SESSION['verified_email'];
    $userIdentifier = $_SESSION['verified_email'];
}

// If not authenticated, redirect to authentication page
if (!$authenticated) {
    $_SESSION['redirect_after_auth'] = '/status';
    $_SESSION['error_message'] = 'Please log in with Discord or Steam to view your application status.';
    header('Location: /');
    exit;
}

// Get application status from database
$applicationData = null;
$applicationStatus = 'not_found';
$applicationTimeline = [];
$steamInfo = [];
$rejectedCount = 0;
try {
    $pdo = getDatabaseConnection();

    if ($pdo) {
        // Query depends on authentication method
        if ($authMethod === 'steam') {
            $stmt = $pdo->prepare("SELECT * FROM whitelist_applications WHERE steam_id = :id ORDER BY submission_date DESC LIMIT 1");
            $stmt->execute([':id' => $userId]);
        } elseif ($authMethod === 'discord') {
            $stmt = $pdo->prepare("SELECT * FROM whitelist_applications WHERE discord_id = :id ORDER BY submission_date DESC LIMIT 1");
            $stmt->execute([':id' => $userId]);
        } elseif ($authMethod === 'email') {
            $stmt = $pdo->prepare("SELECT * FROM whitelist_applications WHERE email = :id ORDER BY submission_date DESC LIMIT 1");
            $stmt->execute([':id' => $userId]);
        }


        $applicationData = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($applicationData) {

            $applicationStatus = $applicationData['status'];
            $steamInfo = [
                'username' => $applicationData['steam_username'] ?? 'N/A',
                'steamid' => $applicationData['steam_id'] ?? 'N/A',
                'steamid3' => $applicationData['steam_id3'] ?? 'N/A',
                'profile' => $applicationData['steam_profile'] ?? '#'
            ];

            // Get count of rejected applications for this Steam ID
            if (!empty($applicationData['steam_id'])) {
                try {
                    $rejected_stmt = $pdo->prepare("
                SELECT COUNT(*) as rejected_count 
                FROM whitelist_applications 
                WHERE steam_id = :steam_id 
                AND status = 'rejected'
            ");
                    $rejected_stmt->execute([':steam_id' => $applicationData['steam_id']]);
                    $result = $rejected_stmt->fetch(PDO::FETCH_ASSOC);
                    $rejectedCount = $result['rejected_count'] ?? 0;
                } catch (PDOException $e) {
                    // Log error but continue
                    logMessage('database_error.log', "Error fetching rejected count: " . $e->getMessage());
                }
            }

            // Get activity log for this application
            $stmt = $pdo->prepare("
    SELECT al.*, a.username as admin_name
    FROM activity_log al
    LEFT JOIN admins a ON al.admin_id = a.id
    WHERE al.application_id = :app_id 
    AND al.action != 'clear_logs'  /* Hide log clearing entries from users */
    ORDER BY al.timestamp ASC
");
            $stmt->execute([':app_id' => $applicationData['id']]);
            $activityLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Build timeline
            $applicationTimeline[] = [
                'date' => $applicationData['submission_date'],
                'status' => 'submitted',
                'message' => 'Application submitted',
                'type' => 'info'
            ];

            foreach ($activityLogs as $log) {
                $timelineItem = [
                    'date' => $log['timestamp'],
                    'status' => $log['action'],
                    'message' => $log['details'],
                    'type' => 'info'
                ];

                // Set type based on action
                if (strpos($log['action'], 'approve') !== false) {
                    $timelineItem['type'] = 'success';
                } elseif (strpos($log['action'], 'reject') !== false || strpos($log['action'], 'ban') !== false) {
                    $timelineItem['type'] = 'danger';
                } elseif (strpos($log['action'], 'review') !== false || strpos($log['action'], 'pending') !== false) {
                    $timelineItem['type'] = 'warning';
                }

                $applicationTimeline[] = $timelineItem;
            }

            // Add status change as the last event if it's not pending
            if ($applicationStatus !== 'pending' && count($applicationTimeline) <= 1) {
                $statusDate = $applicationData['updated_at'];
                $statusMessage = 'Your application has been ' . $applicationStatus;
                $statusType = ($applicationStatus === 'approved') ? 'success' : 'danger';

                $applicationTimeline[] = [
                    'date' => $statusDate,
                    'status' => $applicationStatus,
                    'message' => $statusMessage,
                    'type' => $statusType
                ];
            }
            if (empty($activityLogs) && $applicationData['status'] !== 'pending') {
                // Add a virtual log entry based on the current status
                $statusDate = null;
                $statusAction = $applicationData['status'];

                // Get the timestamp from the appropriate field based on status
                if ($applicationData['status'] === 'approved' && isset($applicationData['approved_at'])) {
                    $statusDate = $applicationData['approved_at'];
                } elseif ($applicationData['status'] === 'rejected' && isset($applicationData['rejected_at'])) {
                    $statusDate = $applicationData['rejected_at'];
                } elseif ($applicationData['status'] === 'banned' && isset($applicationData['banned_at'])) {
                    $statusDate = $applicationData['banned_at'];
                } else {
                    // Fall back to updated_at if specific timestamp isn't available
                    $statusDate = $applicationData['updated_at'];
                }

                // Create virtual log entry
                $activityLogs[] = [
                    'timestamp' => $statusDate,
                    'application_id' => $applicationData['id'],
                    'action' => $statusAction,
                    'details' => 'Your application has been ' . $statusAction,
                    'admin_name' => null, // Hide admin name from users
                    'is_virtual' => true // Flag to identify this is a synthetic entry
                ];
            }
        }
    }
} catch (PDOException $e) {
    // Log the error and show a generic message
    logMessage('database_error.log', 'Error fetching application status: ' . $e->getMessage());
    $error = 'We encountered an error while retrieving your application status. Please try again later.';
}

// Status message and CSS class mappings
$statusMessages = [
    'pending' => 'Your application is pending review.',
    'approved' => 'Congratulations! Your application has been approved.',
    'rejected' => 'Your application has been rejected.',
    'banned' => 'Your account has been banned from applying.',
    'not_found' => 'No application found for your account.'
];

$statusClasses = [
    'pending' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
    'approved' => 'bg-green-100 text-green-800 border-green-200',
    'rejected' => 'bg-red-100 text-red-800 border-red-200',
    'banned' => 'bg-red-100 text-red-800 border-red-200',
    'not_found' => 'bg-gray-100 text-gray-800 border-gray-200'
];

$statusIcons = [
    'pending' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>',
    'approved' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>',
    'rejected' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>',
    'banned' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" /></svg>',
    'not_found' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>'
];

// Timeline type to CSS class mapping
$timelineTypeClasses = [
    'info' => 'bg-blue-100 text-blue-800 border-blue-200',
    'success' => 'bg-green-100 text-green-800 border-green-200',
    'warning' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
    'danger' => 'bg-red-100 text-red-800 border-red-200',
];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Status - Dodgeball Whitelist</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@xz/fonts@1/serve/jetbrains-mono.min.css">
    <style>
        body {
            font-family: 'JetBrains Mono', monospace;
            background-color: #000;
            color: #333;
        }
        .timeline-container {
            position: relative;
        }
        .timeline-line {
            position: absolute;
            left: 16px;
            top: 0;
            bottom: 0;
            width: 2px;
            background-color: #e5e7eb;
            transform: translateX(-50%);
        }
        .timeline-item {
            position: relative;
            z-index: 1;
        }
    </style>
</head>
<body class="min-h-screen flex flex-col items-center justify-start pt-10 px-4">
<!-- Header Container -->
<div class="bg-white w-full max-w-5xl p-8 mb-10 text-center">
    <h1 class="text-5xl font-bold">saka Dodgeball Whitelist Status</h1>
</div>

<!-- Status Container -->
<div class="bg-white w-full max-w-5xl p-10 mb-10">
    <!-- User Info -->
    <div class="mb-8 text-center">
        <?php if ($authMethod === 'steam'): ?>
        <h2 class="text-2xl font-bold mb-2">Hello, <?php echo htmlspecialchars($_SESSION['steam_username']); ?></h2>
        <?php else: ?>
        <h2 class="text-2xl font-bold mb-2">Hello, <?php echo htmlspecialchars($userIdentifier); ?></h2>
        <?php endif; ?>
        <p class="text-gray-600">
            You are logged in with your <?php echo ucfirst($authMethod); ?> account.
            <a href="/logout?service=<?php echo $authMethod; ?>" class="text-blue-600 hover:underline ml-2">Logout</a>
        </p>
    </div>

    <!-- Steam Account Information Card -->
    <?php if ($applicationData): ?>
    <div class="mb-8 border rounded-lg overflow-hidden shadow-sm">
        <div class="bg-gray-50 px-6 py-4 border-b">
            <h3 class="text-xl font-bold">Steam Account Information</h3>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <p class="text-sm text-gray-600">Username</p>
                    <p class="font-medium"><?php echo htmlspecialchars($steamInfo['username']); ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Steam ID</p>
                    <p class="font-medium"><?php echo htmlspecialchars($steamInfo['steamid']); ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Steam ID3</p>
                    <p class="font-medium"><?php echo htmlspecialchars($steamInfo['steamid3']); ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Steam Profile</p>
                    <p class="font-medium">
                        <?php if ($steamInfo['profile'] && $steamInfo['profile'] != '#'): ?>
                            <a href="<?php echo htmlspecialchars($steamInfo['profile']); ?>" target="_blank" class="text-blue-600 hover:underline">
                                View Profile
                            </a>
                        <?php else: ?>
                            Not available
                        <?php endif; ?>
                    </p>
                </div>
                <?php if ($rejectedCount > 0): ?>
                    <div class="md:col-span-2">
                        <p class="text-sm text-gray-600">Previous Rejections</p>
                        <p class="font-medium flex items-center">
                            <span class="px-2 py-1 bg-red-100 text-red-800 rounded-full text-[10px] mr-2"><?php echo $rejectedCount; ?></span>
                            This account has <?php echo $rejectedCount; ?> previously rejected application<?php echo $rejectedCount !== 1 ? 's' : ''; ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <!-- Status Card -->
    <div class="mb-8 border rounded-lg overflow-hidden shadow-sm">
        <div class="bg-gray-50 px-6 py-4 border-b">
            <h3 class="text-xl font-bold">Application Status</h3>
        </div>
        <div class="p-6">
            <div class="flex items-start">
                <!-- Status Icon -->
                <div class="flex-shrink-0 <?php echo $statusClasses[$applicationStatus]; ?> p-3 rounded-full mr-4">
                    <?php echo $statusIcons[$applicationStatus]; ?>
                </div>

                <!-- Status Details -->
                <div class="flex-1">
                    <h4 class="text-lg font-bold capitalize mb-1"><?php echo str_replace('_', ' ', $applicationStatus); ?></h4>
                    <p class="text-gray-600"><?php echo $statusMessages[$applicationStatus]; ?></p>

                    <?php if ($applicationData && !empty($applicationData['admin_notes'])): ?>
                        <div class="mt-4 p-4 bg-gray-50 border rounded">
                            <h5 class="font-bold mb-2">Admin Notes:</h5>
                            <p><?php echo nl2br(htmlspecialchars($applicationData['admin_notes'])); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if ($applicationStatus === 'not_found'): ?>
                        <div class="mt-4">
                            <a href="/" class="inline-block bg-black hover:bg-gray-800 text-white py-2 px-4 rounded">
                                Apply Now
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if ($applicationData && count($applicationTimeline) > 0): ?>
        <!-- Timeline -->
        <div class="border rounded-lg overflow-hidden shadow-sm">
            <div class="bg-gray-50 px-6 py-4 border-b">
                <h3 class="text-xl font-bold">Application Timeline</h3>
            </div>
            <div class="p-6">
                <div class="timeline-container">
                    <div class="timeline-line"></div>

                    <?php foreach ($applicationTimeline as $index => $item): ?>
                        <div class="timeline-item flex mb-6 <?php echo $index === count($applicationTimeline) - 1 ? '' : 'pb-6'; ?>">
                            <!-- Timeline point -->
                            <div class="flex-shrink-0 w-8 h-8 rounded-full <?php echo $timelineTypeClasses[$item['type']]; ?> flex items-center justify-center border-4 border-white z-10">
                                <?php if ($item['type'] === 'info'): ?>
                                    <div class="w-2 h-2 bg-blue-600 rounded-full"></div>
                                <?php elseif ($item['type'] === 'success'): ?>
                                    <div class="w-2 h-2 bg-green-600 rounded-full"></div>
                                <?php elseif ($item['type'] === 'warning'): ?>
                                    <div class="w-2 h-2 bg-yellow-600 rounded-full"></div>
                                <?php elseif ($item['type'] === 'danger'): ?>
                                    <div class="w-2 h-2 bg-red-600 rounded-full"></div>
                                <?php endif; ?>
                            </div>

                            <!-- Timeline content -->
                            <div class="ml-4 flex-1">
                                <div class="<?php echo $timelineTypeClasses[$item['type']]; ?> border px-4 py-3 rounded-lg">
                                    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center mb-1">
                                        <h4 class="font-bold capitalize"><?php echo str_replace('_', ' ', $item['status']); ?></h4>
                                        <span class="text-sm"><?php echo date('F j, Y g:i A', strtotime($item['date'])); ?></span>
                                    </div>
                                    <p><?php echo nl2br(htmlspecialchars($item['message'])); ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Actions -->
    <div class="mt-8 text-center">
        <a href="/" class="inline-block bg-black hover:bg-gray-800 text-white py-2 px-6 rounded mr-4">
            Return to Homepage
        </a>

        <?php if ($applicationStatus === 'rejected'): ?>
            <a href="/" class="inline-block bg-blue-600 hover:bg-blue-700 text-white py-2 px-6 rounded">
                Submit New Application
            </a>
        <?php endif; ?>
    </div>
</div>
<div class="text-center text-gray-400 mt-8 mb-4">
    <p class="text-sm">&copy; saka Dodgeball <?php echo date('Y'); ?>. All rights reserved.</p>
</div>
</body>
</html>