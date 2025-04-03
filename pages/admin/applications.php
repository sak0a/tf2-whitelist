<?php
// Start session
session_start();

// Include configuration and admin authentication check
require_once '../config.php';
require_once 'auth_check.php'; // This file should check if admin is logged in

// Define filters and pagination
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 15; // Items per page
$offset = ($page - 1) * $limit;

// Prepare filter SQL
$status_where = '';
$params = [];

if ($status_filter !== 'all') {
    $status_where = " WHERE status = :status";
    $params[':status'] = $status_filter;
}

// Fetch applications from the database
try {
    $pdo = getDatabaseConnection();

    // Count total applications for pagination
    $count_sql = "SELECT COUNT(*) FROM whitelist_applications" . $status_where;
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_applications = $count_stmt->fetchColumn();
    $total_pages = ceil($total_applications / $limit);

    // Get applications with pagination
    $sql = "SELECT * FROM whitelist_applications" . $status_where .
        " ORDER BY submission_date DESC LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $_SESSION['admin_error'] = "Database error: " . $e->getMessage();
    logMessage('admin_error.log', "Database error fetching applications: " . $e->getMessage());
    $applications = [];
    $total_pages = 0;
}

// Status badge CSS classes
$statusBadges = [
    'pending' => 'bg-yellow-100 text-yellow-800',
    'approved' => 'bg-green-100 text-green-800',
    'rejected' => 'bg-red-100 text-red-800',
    'banned' => 'bg-black text-white'
];

// Helper function to format dates
function formatDate($dateString) {
    return date('M j, Y', strtotime($dateString));
}



?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applications - Admin Panel</title>
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
            <a href="/logout" class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded text-sm">Logout</a>
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
                    <a href="/dashboard" class="text-gray-700 hover:text-blue-600">
                        Dashboard
                    </a>
                </li>
                <li aria-current="page">
                    <div class="flex items-center">
                        <span class="mx-2 text-gray-400">/</span>
                        <span class="text-gray-500">Applications</span>
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

    <!-- Applications panel -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="flex flex-col md:flex-row justify-between items-center border-b px-6 py-4">
            <h2 class="text-xl font-bold mb-4 md:mb-0">Whitelist Applications</h2>

            <!-- Filter controls -->
            <div class="flex flex-wrap gap-2">
                <a href="?status=all" class="px-4 py-2 rounded text-sm font-medium <?php echo $status_filter === 'all' ? 'bg-black text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?>">
                    All
                </a>
                <a href="?status=pending" class="px-4 py-2 rounded text-sm font-medium <?php echo $status_filter === 'pending' ? 'bg-yellow-400 text-yellow-900' : 'bg-yellow-100 text-yellow-800 hover:bg-yellow-200'; ?>">
                    Pending
                </a>
                <a href="?status=approved" class="px-4 py-2 rounded text-sm font-medium <?php echo $status_filter === 'approved' ? 'bg-green-400 text-green-900' : 'bg-green-100 text-green-800 hover:bg-green-200'; ?>">
                    Approved
                </a>
                <a href="?status=rejected" class="px-4 py-2 rounded text-sm font-medium <?php echo $status_filter === 'rejected' ? 'bg-red-400 text-red-900' : 'bg-red-100 text-red-800 hover:bg-red-200'; ?>">
                    Rejected
                </a>
                <a href="?status=banned" class="px-4 py-2 rounded text-sm font-medium <?php echo $status_filter === 'banned' ? 'bg-gray-700 text-white' : 'bg-gray-800 text-white hover:bg-gray-700'; ?>">
                    Banned
                </a>
            </div>
        </div>

        <!-- Applications table -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Applicant
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Contact
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Submission Date
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Status
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Actions
                    </th>
                </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($applications)): ?>
                    <tr>
                        <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                            No applications found
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($applications as $app): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($app['steam_username']); ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            <?php echo htmlspecialchars($app['steam_id3']); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">
                                    <?php
                                    if (!empty($app['discord_username'])) {
                                        echo htmlspecialchars($app['discord_username']);
                                    } elseif (!empty($app['email'])) {
                                        echo htmlspecialchars($app['email']);
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">
                                    <?php echo formatDate($app['submission_date']); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusBadges[$app['status']]; ?>">
                                            <?php echo ucfirst($app['status']); ?>
                                        </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <a href="/view_application?id=<?php echo $app['id']; ?>" class="text-blue-600 hover:text-blue-900 mr-3">
                                    View
                                </a>

                                <?php if ($app['status'] === 'pending'): ?>
                                    <a href="/quick_approve?id=<?php echo $app['id']; ?>" class="text-green-600 hover:text-green-900 mr-3">
                                        Approve
                                    </a>
                                    <a href="/quick_reject?id=<?php echo $app['id']; ?>" class="text-red-600 hover:text-red-900">
                                        Reject
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="px-6 py-4 bg-gray-50 border-t flex justify-between items-center">
                <div>
                    <p class="text-sm text-gray-700">
                        Showing <span class="font-medium"><?php echo $offset + 1; ?></span> to
                        <span class="font-medium"><?php echo min($offset + $limit, $total_applications); ?></span> of
                        <span class="font-medium"><?php echo $total_applications; ?></span> results
                    </p>
                </div>
                <div class="flex space-x-2">
                    <?php if ($page > 1): ?>
                        <a href="?status=<?php echo $status_filter; ?>&page=<?php echo $page - 1; ?>"
                           class="px-3 py-1 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">
                            Previous
                        </a>
                    <?php endif; ?>

                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <a href="?status=<?php echo $status_filter; ?>&page=<?php echo $i; ?>"
                           class="px-3 py-1 rounded <?php echo $i === $page ? 'bg-black text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?status=<?php echo $status_filter; ?>&page=<?php echo $page + 1; ?>"
                           class="px-3 py-1 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">
                            Next
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>