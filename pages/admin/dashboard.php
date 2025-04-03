<?php
// Start session
session_start();

// Include configuration and admin authentication check
require_once 'config.php';
require_once 'auth_check.php'; // This file checks if admin is logged in

// Get application statistics
$stats = [
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0,
    'banned' => 0,
    'total' => 0,
    'today' => 0,
    'week' => 0,
    'month' => 0
];

// Recent activity
$recent_activities = [];

try {
    $pdo = getDatabaseConnection();

    // Get status counts
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM whitelist_applications GROUP BY status");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $stats[$row['status']] = $row['count'];
        $stats['total'] += $row['count'];
    }

    // Get today's applications
    $stmt = $pdo->query("SELECT COUNT(*) FROM whitelist_applications WHERE DATE(submission_date) = CURDATE()");
    $stats['today'] = $stmt->fetchColumn();

    // Get this week's applications
    $stmt = $pdo->query("SELECT COUNT(*) FROM whitelist_applications WHERE submission_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
    $stats['week'] = $stmt->fetchColumn();

    // Get this month's applications
    $stmt = $pdo->query("SELECT COUNT(*) FROM whitelist_applications WHERE submission_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
    $stats['month'] = $stmt->fetchColumn();

    // Get recent activity
    $stmt = $pdo->query("
        SELECT al.*, a.username as admin_name, wa.steam_username, wa.status 
        FROM activity_log al
        LEFT JOIN admins a ON al.admin_id = a.id
        LEFT JOIN whitelist_applications wa ON al.application_id = wa.id
        ORDER BY al.timestamp DESC
        LIMIT 10
    ");
    $recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $_SESSION['admin_error'] = "Database error: " . $e->getMessage();
    logMessage('admin_error.log', "Database error in dashboard: " . $e->getMessage());
}

// Helper function to format dates
function formatDate($dateString) {
    return date('M j, Y g:i A', strtotime($dateString));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Dodgeball Whitelist</title>
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
            <a href="/admin/email-test" class="...">
                Email Settings
            </a>
            <span class="divider h-4 bg-white w-[2px]"></span>
            <span><?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
            <a href="/admin/logout" class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded text-sm">Logout</a>
        </div>
    </div>
</header>

<!-- Main content -->
<div class="container mx-auto p-4">
    <!-- Dashboard title -->
    <div class="mb-6">
        <h2 class="text-2xl font-bold">Dashboard</h2>
        <p class="text-gray-600">Welcome back, <?php echo htmlspecialchars($_SESSION['admin_username']); ?>!</p>
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

    <!-- Quick links -->
    <div class="mb-8 grid grid-cols-1 md:grid-cols-4 gap-4">
        <a href="/admin/applications?status=pending" class="bg-yellow-100 border border-yellow-200 p-4 rounded-lg hover:bg-yellow-200 flex flex-col items-center justify-center">
            <div class="text-3xl font-bold text-yellow-800 mb-2"><?php echo $stats['pending']; ?></div>
            <div class="text-yellow-800">Pending Applications</div>
        </a>

        <a href="/admin/applications?status=approved" class="bg-green-100 border border-green-200 p-4 rounded-lg hover:bg-green-200 flex flex-col items-center justify-center">
            <div class="text-3xl font-bold text-green-800 mb-2"><?php echo $stats['approved']; ?></div>
            <div class="text-green-800">Approved Applications</div>
        </a>

        <a href="/admin/applications?status=rejected" class="bg-red-100 border border-red-200 p-4 rounded-lg hover:bg-red-200 flex flex-col items-center justify-center">
            <div class="text-3xl font-bold text-red-800 mb-2"><?php echo $stats['rejected']; ?></div>
            <div class="text-red-800">Rejected Applications</div>
        </a>

        <a href="/admin/applications?status=banned" class="bg-gray-900 p-4 rounded-lg hover:bg-black flex flex-col items-center justify-center">
            <div class="text-3xl font-bold text-white mb-2"><?php echo $stats['banned']; ?></div>
            <div class="text-white">Banned Users</div>
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Statistics -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="bg-gray-50 px-6 py-4 border-b">
                    <h3 class="font-bold">Application Statistics</h3>
                </div>
                <div class="p-4">
                    <table class="w-full">
                        <tr class="border-b">
                            <td class="py-2">Total Applications</td>
                            <td class="py-2 text-right font-bold"><?php echo $stats['total']; ?></td>
                        </tr>
                        <tr class="border-b">
                            <td class="py-2">Today</td>
                            <td class="py-2 text-right font-bold"><?php echo $stats['today']; ?></td>
                        </tr>
                        <tr class="border-b">
                            <td class="py-2">This Week</td>
                            <td class="py-2 text-right font-bold"><?php echo $stats['week']; ?></td>
                        </tr>
                        <tr class="border-b">
                            <td class="py-2">This Month</td>
                            <td class="py-2 text-right font-bold"><?php echo $stats['month']; ?></td>
                        </tr>
                        <tr class="border-b">
                            <td class="py-2">Approval Rate</td>
                            <td class="py-2 text-right font-bold">
                                <?php
                                $processed = $stats['approved'] + $stats['rejected'] + $stats['banned'];
                                echo ($processed > 0) ? round(($stats['approved'] / $processed) * 100) . '%' : 'N/A';
                                ?>
                            </td>
                        </tr>
                    </table>

                    <div class="mt-4">
                        <a href="/admin/applications" class="block text-center bg-black text-white py-2 px-4 rounded hover:bg-gray-800">
                            View All Applications
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent activity -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="bg-gray-50 px-6 py-4 border-b">
                    <h3 class="font-bold">Recent Activity</h3>
                </div>
                <div class="p-4">
                    <?php if (empty($recent_activities)): ?>
                        <p class="text-gray-500 text-center py-4">No recent activity</p>
                    <?php else: ?>
                        <div class="space-y-4 max-h-96 overflow-y-auto">
                            <?php foreach ($recent_activities as $activity): ?>
                                <div class="border-b pb-3">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <span class="font-medium capitalize"><?php echo str_replace('_', ' ', $activity['action']); ?></span>

                                            <?php if (!empty($activity['application_id'])): ?>
                                                <a href="/admin/view-application/<?php echo $activity['application_id']; ?>" class="text-blue-600 hover:underline ml-1">
                                                    App #<?php echo $activity['application_id']; ?>
                                                </a>
                                                <?php if (!empty($activity['steam_username'])): ?>
                                                    <span class="text-gray-600">(<?php echo htmlspecialchars($activity['steam_username']); ?>)</span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                        <span class="text-xs text-gray-500"><?php echo formatDate($activity['timestamp']); ?></span>
                                    </div>
                                    <?php if (!empty($activity['details'])): ?>
                                        <p class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars($activity['details']); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($activity['admin_name'])): ?>
                                        <p class="text-xs text-gray-500 mt-1">By: <?php echo htmlspecialchars($activity['admin_name']); ?></p>
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
</html>