<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join the Dodgeball Whitelist</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@xz/fonts@1/serve/jetbrains-mono.min.css">
    <style>
        body {
            font-family: 'JetBrains Mono', monospace;
            background-color: #000;
            color: #333;
        }
        .step {
            display: none;
        }
        .step.active {
            display: block;
        }
        .auth-button {
            transition: transform 0.2s ease;
        }
        .auth-button:hover {
            transform: translateY(-2px);
        }
        .verification-input {
            width: 4rem;
            height: 4rem;
            font-size: 2rem;
            text-align: center;
        }
        /* Add this to your existing style section */
        /* Required field indicator */
        .required-field {
            background-color: rgba(254, 226, 226, 1); /* bg-red-100 */
            color: rgba(153, 27, 27, 1); /* text-red-800 */
            font-size: 0.75rem; /* text-xs */
            font-weight: 700; /* font-bold */
            padding-left: 0.5rem; /* px-2 */
            padding-right: 0.5rem; /* px-2 */
            padding-top: 0.25rem; /* py-1 */
            padding-bottom: 0.25rem; /* py-1 */
            border-radius: 0.25rem; /* rounded */
            margin-left: 0.5rem; /* ml-2 */
        }

        /* Label styling for required fields */
        .required-label {
            display: flex;
            align-items: center;
        }

        /* Highlight required input fields with a red border when empty and focused */
        .required-input:invalid:focus {
            border-color: rgba(252, 165, 165, 1); /* border-red-300 */
            box-shadow: 0 0 0 2px rgba(254, 202, 202, 0.5); /* ring-red-200 */
        }
    </style>
    <?php
    require_once 'config.php';

    // Start session if not already started
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    // Check for verification success messages
    $discordVerified = isset($_SESSION['discord_verified']) && $_SESSION['discord_verified'] === true;
    $steamVerified = isset($_SESSION['steam_verified']) && $_SESSION['steam_verified'] === true;
    $emailVerified = isset($_SESSION['email_verified']) && $_SESSION['email_verified'] === true;


    // Check if user already has a pending or banned application
    $hasPendingApplication = false;
    $isApproved = false;
    $isBanned = false;
    $applicationData = [];


    if ((isset($_SESSION['steam_verified']) && $_SESSION['steam_verified'] === true) ||
        (isset($_SESSION['discord_verified']) && $_SESSION['discord_verified'] === true) ||
        (isset($_SESSION['email_verified']) && $_SESSION['email_verified'] === true)) {

        try {
            $pdo = getDatabaseConnection();

            if ($pdo) {
                // Determine which authentication method to check
                if (isset($_SESSION['steam_verified']) && $_SESSION['steam_verified'] === true) {
                    // Check Steam authentication
                    // First check for banned status
                    $stmt = $pdo->prepare("SELECT * FROM whitelist_applications 
                               WHERE steam_id = :id AND status = 'banned'
                               ORDER BY submission_date DESC LIMIT 1");
                    $stmt->execute([':id' => $_SESSION['steam_id']]);
                    $bannedApp = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($bannedApp) {
                        $isBanned = true;
                        $applicationData = $bannedApp;
                    } else {
                        // If not banned, check for approved
                        $stmt = $pdo->prepare("SELECT * FROM whitelist_applications 
                                  WHERE steam_id = :id AND status = 'approved'
                                  ORDER BY submission_date DESC LIMIT 1");
                        $stmt->execute([':id' => $_SESSION['steam_id']]);
                        $approvedApp = $stmt->fetch(PDO::FETCH_ASSOC);

                        if ($approvedApp) {
                            $isApproved = true;
                            $applicationData = $approvedApp;
                        } else {
                            // If not approved, check for pending
                            $stmt = $pdo->prepare("SELECT * FROM whitelist_applications 
                                      WHERE steam_id = :id AND status = 'pending'
                                      ORDER BY submission_date DESC LIMIT 1");
                            $stmt->execute([':id' => $_SESSION['steam_id']]);
                            $pendingApp = $stmt->fetch(PDO::FETCH_ASSOC);

                            if ($pendingApp) {
                                $hasPendingApplication = true;
                                $applicationData = $pendingApp;
                            }
                        }
                    }
                } elseif (isset($_SESSION['discord_verified']) && $_SESSION['discord_verified'] === true) {
                    // Check Discord authentication
                    // First check for banned status
                    $stmt = $pdo->prepare("SELECT * FROM whitelist_applications 
                               WHERE discord_id = :id AND status = 'banned'
                               ORDER BY submission_date DESC LIMIT 1");
                    $stmt->execute([':id' => $_SESSION['discord_id']]);
                    $bannedApp = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($bannedApp) {
                        $isBanned = true;
                        $applicationData = $bannedApp;
                    } else {
                        // If not banned, check for approved
                        $stmt = $pdo->prepare("SELECT * FROM whitelist_applications 
                                  WHERE discord_id = :id AND status = 'approved'
                                  ORDER BY submission_date DESC LIMIT 1");
                        $stmt->execute([':id' => $_SESSION['discord_id']]);
                        $approvedApp = $stmt->fetch(PDO::FETCH_ASSOC);

                        if ($approvedApp) {
                            $isApproved = true;
                            $applicationData = $approvedApp;
                        } else {
                            // If not approved, check for pending
                            $stmt = $pdo->prepare("SELECT * FROM whitelist_applications 
                                      WHERE discord_id = :id AND status = 'pending'
                                      ORDER BY submission_date DESC LIMIT 1");
                            $stmt->execute([':id' => $_SESSION['discord_id']]);
                            $pendingApp = $stmt->fetch(PDO::FETCH_ASSOC);

                            if ($pendingApp) {
                                $hasPendingApplication = true;
                                $applicationData = $pendingApp;
                            }
                        }
                    }
                } elseif (isset($_SESSION['email_verified']) && $_SESSION['email_verified'] === true) {
                    // Check Email authentication - This is the new section to update
                    $email = $_SESSION['verified_email'];

                    // First check for banned status
                    $stmt = $pdo->prepare("SELECT * FROM whitelist_applications 
                               WHERE email = :email AND status = 'banned'
                               ORDER BY submission_date DESC LIMIT 1");
                    $stmt->execute([':email' => $email]);
                    $bannedApp = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($bannedApp) {
                        $isBanned = true;
                        $applicationData = $bannedApp;
                    } else {
                        // If not banned, check for approved
                        $stmt = $pdo->prepare("SELECT * FROM whitelist_applications 
                                  WHERE email = :email AND status = 'approved'
                                  ORDER BY submission_date DESC LIMIT 1");
                        $stmt->execute([':email' => $email]);
                        $approvedApp = $stmt->fetch(PDO::FETCH_ASSOC);

                        if ($approvedApp) {
                            $isApproved = true;
                            $applicationData = $approvedApp;
                        } else {
                            // If not approved, check for pending
                            $stmt = $pdo->prepare("SELECT * FROM whitelist_applications 
                                      WHERE email = :email AND status = 'pending'
                                      ORDER BY submission_date DESC LIMIT 1");
                            $stmt->execute([':email' => $email]);
                            $pendingApp = $stmt->fetch(PDO::FETCH_ASSOC);

                            if ($pendingApp) {
                                $hasPendingApplication = true;
                                $applicationData = $pendingApp;
                            }
                        }
                    }
                }
            }
        } catch (PDOException $e) {
            logMessage('application_error.log', "Error checking applications: " . $e->getMessage());
        }
    }



    // Check for error messages
    $errorMessage = $_SESSION['error_message'] ?? null;
    unset($_SESSION['error_message']);
    ?>
</head>
<body class="min-h-screen flex flex-col items-center justify-start pt-10 px-4">
<!-- Header Container -->
<div class="bg-white w-full max-w-5xl p-8 mb-10 text-center">
    <h1 class="text-5xl font-bold">Join the saka Dodgeball Whitelist</h1>
</div>

<!-- BANNED APPLICATION NOTICE -->
<?php if ($isBanned): ?>
    <!-- Display banned application notice instead of the form -->
    <div class="bg-red-100 max-w-5xl text-red-700 p-6 mb-6">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-8 w-8 text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                </svg>
            </div>
            <div class="ml-3">
                <h3 class="text-xl font-bold">Account Banned</h3>
                <div class="mt-3">
                    <!-- Show which account is logged in -->
                    <div class="p-3 bg-red-50 border border-red-200 rounded mb-4">
                        <p class="font-semibold mb-1">Currently logged in as:</p>
                        <?php if (isset($_SESSION['steam_verified']) && $_SESSION['steam_verified'] === true): ?>
                            <p><strong>Steam:</strong> <?php echo htmlspecialchars($_SESSION['steam_username']); ?> (<?php echo htmlspecialchars($_SESSION['steam_id3']); ?>)</p>
                        <?php endif; ?>
                        <?php if (isset($_SESSION['discord_verified']) && $_SESSION['discord_verified'] === true): ?>
                            <p><strong>Discord:</strong> <?php echo htmlspecialchars($_SESSION['discord_username']); ?></p>
                        <?php endif; ?>
                        <?php if (isset($_SESSION['email_verified']) && $_SESSION['email_verified'] === true): ?>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($_SESSION['verified_email']); ?></p>
                        <?php endif; ?>
                        <div class="mt-2">
                            <?php if (isset($_SESSION['steam_verified']) && $_SESSION['steam_verified'] === true): ?>
                                <a href="logout.php?service=steam" class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded text-sm">
                                    Logout Steam
                                </a>
                            <?php endif; ?>
                            <?php if (isset($_SESSION['discord_verified']) && $_SESSION['discord_verified'] === true): ?>
                                <a href="logout.php?service=discord" class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded text-sm ml-2">
                                    Logout Discord
                                </a>
                            <?php endif; ?>
                            <?php if (isset($_SESSION['email_verified']) && $_SESSION['email_verified'] === true): ?>
                                <a href="logout.php?service=email" class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded text-sm ml-2">
                                    Logout Email
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <p class="mb-3">Your account has been banned from applying to the whitelist. This decision was made on
                        <strong><?php echo date('F j, Y g:i A', strtotime($applicationData['updated_at'])); ?></strong>.
                    </p>
                    <?php if (!empty($applicationData['admin_notes'])): ?>
                        <div class="p-3 bg-red-50 border border-red-200 rounded mb-4">
                            <p class="font-semibold mb-1">Admin Notes:</p>
                            <p><?php echo nl2br(htmlspecialchars($applicationData['admin_notes'])); ?></p>
                        </div>
                    <?php endif; ?>
                    <p class="mb-4">If you believe this is in error, please contact a server administrator.</p>
                    <a href="status.php" class="inline-block bg-black text-white font-bold py-2 px-6 rounded">
                        View Ban Details
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- PENDING APPLICATION NOTICE -->
<?php elseif ($hasPendingApplication): ?>
    <!-- Display pending application notice instead of the form -->
    <div class="bg-yellow-100 max-w-5xl text-yellow-700 p-6 mb-6">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-8 w-8 text-yellow-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </div>
            <div class="ml-3">
                <h3 class="text-xl font-bold">Application Already Submitted</h3>
                <div class="mt-3">
                    <!-- Show which account is logged in -->
                    <div class="p-3 bg-yellow-50 border border-yellow-200 rounded mb-4">
                        <p class="font-semibold mb-1">Currently logged in as:</p>
                        <?php if (isset($_SESSION['steam_verified']) && $_SESSION['steam_verified'] === true): ?>
                            <p><strong>Steam:</strong> <?php echo htmlspecialchars($_SESSION['steam_username']); ?> (<?php echo htmlspecialchars($_SESSION['steam_id3']); ?>)</p>
                        <?php endif; ?>
                        <?php if (isset($_SESSION['discord_verified']) && $_SESSION['discord_verified'] === true): ?>
                            <p><strong>Discord:</strong> <?php echo htmlspecialchars($_SESSION['discord_username']); ?></p>
                        <?php endif; ?>
                        <?php if (isset($_SESSION['email_verified']) && $_SESSION['email_verified'] === true): ?>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($_SESSION['verified_email']); ?></p>
                        <?php endif; ?>
                        <div class="mt-2">
                            <?php if (isset($_SESSION['steam_verified']) && $_SESSION['steam_verified'] === true): ?>
                                <a href="logout.php?service=steam" class="bg-yellow-600 hover:bg-yellow-700 text-white px-3 py-1 rounded text-sm">
                                    Logout Steam
                                </a>
                            <?php endif; ?>
                            <?php if (isset($_SESSION['discord_verified']) && $_SESSION['discord_verified'] === true): ?>
                                <a href="logout.php?service=discord" class="bg-yellow-600 hover:bg-yellow-700 text-white px-3 py-1 rounded text-sm ml-2">
                                    Logout Discord
                                </a>
                            <?php endif; ?>
                            <?php if (isset($_SESSION['email_verified']) && $_SESSION['email_verified'] === true): ?>
                                <a href="logout.php?service=email" class="bg-yellow-600 hover:bg-yellow-700 text-white px-3 py-1 rounded text-sm ml-2">
                                    Logout Email
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <p class="mb-3">You already have a pending application that was submitted on
                        <strong><?php echo date('F j, Y g:i A', strtotime($applicationData['submission_date'])); ?></strong>.
                    </p>
                    <p class="mb-4">Please wait while your application is being reviewed. You can check the status of your application by clicking the button below.</p>
                    <a href="status.php" class="inline-block bg-black text-white font-bold py-2 px-6 rounded">
                        Check Application Status
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- APPROVED APPLICATION NOTICE -->
<?php elseif ($isApproved): ?>
    <!-- Display approved application notice instead of the form -->
    <div class="bg-green-100 max-w-5xl w-full text-green-700 p-6 mb-6">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-8 w-8 text-green-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div class="ml-3">
                <h3 class="text-xl font-bold">Application Approved!</h3>
                <div class="mt-3">
                    <!-- Show which account is logged in -->
                    <div class="p-3 bg-green-50 border border-green-200 rounded mb-4">
                        <p class="font-semibold mb-1">Currently logged in as:</p>
                        <?php if (isset($_SESSION['steam_verified']) && $_SESSION['steam_verified'] === true): ?>
                            <p><strong>Steam:</strong> <?php echo htmlspecialchars($_SESSION['steam_username']); ?> (<?php echo htmlspecialchars($_SESSION['steam_id3']); ?>)</p>
                        <?php endif; ?>
                        <?php if (isset($_SESSION['discord_verified']) && $_SESSION['discord_verified'] === true): ?>
                            <p><strong>Discord:</strong> <?php echo htmlspecialchars($_SESSION['discord_username']); ?></p>
                        <?php endif; ?>
                        <?php if (isset($_SESSION['email_verified']) && $_SESSION['email_verified'] === true): ?>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($_SESSION['verified_email']); ?></p>
                        <?php endif; ?>
                        <div class="mt-2">
                            <?php if (isset($_SESSION['steam_verified']) && $_SESSION['steam_verified'] === true): ?>
                                <a href="logout.php?service=steam" class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-sm">
                                    Logout Steam
                                </a>
                            <?php endif; ?>
                            <?php if (isset($_SESSION['discord_verified']) && $_SESSION['discord_verified'] === true): ?>
                                <a href="logout.php?service=discord" class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-sm ml-2">
                                    Logout Discord
                                </a>
                            <?php endif; ?>
                            <?php if (isset($_SESSION['email_verified']) && $_SESSION['email_verified'] === true): ?>
                                <a href="logout.php?service=email" class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-sm ml-2">
                                    Logout Email
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <p class="mb-3">Congratulations! Your application was approved on
                        <strong><?php echo date('F j, Y g:i A', strtotime($applicationData['updated_at'])); ?></strong>.
                    </p>
                    <?php if (!empty($applicationData['admin_notes'])): ?>
                        <div class="p-3 bg-green-50 border border-green-200 rounded mb-4">
                            <p class="font-semibold mb-1">Admin Notes:</p>
                            <p><?php echo nl2br(htmlspecialchars($applicationData['admin_notes'])); ?></p>
                        </div>
                    <?php endif; ?>
                    <p class="mb-4">You are now whitelisted and can join the Dodgeball server.</p>
                    <a href="status.php" class="inline-block bg-black text-white font-bold py-2 px-6 rounded">
                        View Application Details
                    </a>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
<!-- Steps Container -->
<div class="bg-white w-full max-w-5xl p-10 mb-10">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-8 mb-10">
        <!-- Step 1 -->
        <div class="flex flex-col items-center">
            <div class="bg-black p-4 mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
            </div>
            <h2 class="text-xl font-bold">Authentication</h2>
            <p class="text-center mt-4">Connect your accounts to verify your identity.</p>
        </div>

        <!-- Step 2 -->
        <div class="flex flex-col items-center">
            <div class="bg-black p-4 mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
            </div>
            <h2 class="text-xl font-bold">Account History</h2>
            <p class="text-center mt-4">Provide information about your account(s).</p>
        </div>

        <!-- Step 3 -->
        <div class="flex flex-col items-center">
            <div class="bg-black p-4 mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <h2 class="text-xl font-bold">Additional Info</h2>
            <p class="text-center mt-4">(Optional) Tell us about your experience and other details.</p>
        </div>

        <!-- Step 4 (New) -->
        <div class="flex flex-col items-center">
            <div class="bg-black p-4 mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
            </div>
            <h2 class="text-xl font-bold">Agreement</h2>
            <p class="text-center mt-4">Review and accept the whitelist terms.</p>
        </div>
    </div>

    <!-- Form Container -->
    <form id="whitelist-form" action="process.php" method="POST">
        <!-- Progress Bar -->
        <div class="w-full bg-gray-200 h-2 mb-8">
            <div id="progress-bar" class="bg-black h-2" style="width: 25%"></div>
        </div>
        <!-- Step 1 - Authentication Methods -->
        <div id="step1" class="step active">
            <h3 class="text-xl font-bold mb-6">Authentication</h3>

            <?php if ($errorMessage): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                    <p><?php echo $errorMessage; ?></p>
                </div>
            <?php endif; ?>

            <!-- Discord Authentication Section with Improved Display and Logout -->
            <div class="mb-8 border rounded-lg p-6 bg-gray-50">
                <h4 class="text-lg font-bold mb-4">Discord Authentication</h4>

                <?php if ($discordVerified): ?>
                    <div class="flex items-center mb-4">
                        <div class="bg-green-100 text-green-800 rounded-full p-1 mr-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <div>
                            <p class="font-medium">Discord account verified!</p>
                            <?php if (!empty($_SESSION['discord_username'])): ?>
                                <p class="text-sm text-gray-600">Username: <?php echo htmlspecialchars($_SESSION['discord_username']); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($_SESSION['discord_email'])): ?>
                                <!--<p class="text-sm text-gray-600">Email: <?php echo htmlspecialchars($_SESSION['discord_email']); ?></p>-->
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($_SESSION['discord_avatar'])): ?>
                            <div class="ml-auto mr-4">
                                <img src="https://cdn.discordapp.com/avatars/<?php echo $_SESSION['discord_id']; ?>/<?php echo $_SESSION['discord_avatar']; ?>.png"
                                     alt="Discord Avatar" class="w-20 h-20 rounded-full">
                            </div>
                        <?php endif; ?>
                        <a href="logout.php?service=discord" class="bg-red-600 hover:bg-red-700 text-white py-1 px-3 rounded text-sm flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                            Logout
                        </a>
                    </div>
                    <input type="hidden" name="discord_verified" value="1">
                <?php else: ?>
                    <div class="mb-6">
                        <p class="mb-4">Connect your Discord account to verify your identity.</p>
                        <a href="discord_auth.php" class="inline-flex items-center bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-6 rounded auth-button">
                            <svg class="w-6 h-6 mr-2" viewBox="0 0 71 55" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M60.1045 4.8978C55.5792 2.8214 50.7265 1.2916 45.6527 0.41542C45.5603 0.39851 45.468 0.440769 45.4204 0.525289C44.7963 1.6353 44.105 3.0834 43.6209 4.2216C38.1637 3.4046 32.7345 3.4046 27.3892 4.2216C26.905 3.0581 26.1886 1.6353 25.5617 0.525289C25.5141 0.443589 25.4218 0.40133 25.3294 0.41542C20.2584 1.2888 15.4057 2.8186 10.8776 4.8978C10.8384 4.9147 10.8048 4.9429 10.7825 4.9795C1.57795 18.7309 -0.943561 32.1443 0.293408 45.3914C0.299005 45.4562 0.335386 45.5182 0.385761 45.5576C6.45866 50.0174 12.3413 52.7249 18.1147 54.5195C18.2071 54.5477 18.305 54.5139 18.3638 54.4378C19.7295 52.5728 20.9469 50.6063 21.9907 48.5383C22.0523 48.4172 21.9935 48.2735 21.8676 48.2256C19.9366 47.4931 18.0979 46.6 16.3292 45.5858C16.1893 45.5041 16.1781 45.304 16.3068 45.2082C16.679 44.9293 17.0513 44.6391 17.4067 44.3461C17.471 44.2926 17.5606 44.2813 17.6362 44.3151C29.2558 49.6202 41.8354 49.6202 53.3179 44.3151C53.3935 44.2785 53.4831 44.2898 53.5502 44.3433C53.9057 44.6363 54.2779 44.9293 54.6529 45.2082C54.7816 45.304 54.7732 45.5041 54.6333 45.5858C52.8646 46.6197 51.0259 47.4931 49.0921 48.2228C48.9662 48.2707 48.9102 48.4172 48.9718 48.5383C50.038 50.6034 51.2554 52.5699 52.5959 54.435C52.6519 54.5139 52.7526 54.5477 52.845 54.5195C58.6464 52.7249 64.529 50.0174 70.6019 45.5576C70.6551 45.5182 70.6887 45.459 70.6943 45.3942C72.1747 30.0791 68.2147 16.7757 60.1968 4.9823C60.1772 4.9429 60.1437 4.9147 60.1045 4.8978ZM23.7259 37.3253C20.2276 37.3253 17.3451 34.1136 17.3451 30.1693C17.3451 26.225 20.1717 23.0133 23.7259 23.0133C27.308 23.0133 30.1626 26.2532 30.1066 30.1693C30.1066 34.1136 27.28 37.3253 23.7259 37.3253ZM47.3178 37.3253C43.8196 37.3253 40.9371 34.1136 40.9371 30.1693C40.9371 26.225 43.7636 23.0133 47.3178 23.0133C50.9 23.0133 53.7545 26.2532 53.6986 30.1693C53.6986 34.1136 50.9 37.3253 47.3178 37.3253Z" fill="#ffffff"/>
                            </svg>
                            Connect with Discord
                        </a>
                    </div>

                    <div class="mb-4">
                        <div class="flex items-center mb-2">
                            <div class="border-t border-gray-300 flex-grow mr-3"></div>
                            <span class="text-gray-500">OR</span>
                            <div class="border-t border-gray-300 flex-grow ml-3"></div>
                        </div>
                    </div>

                    <!-- Just show the email verification form directly -->
                    <?php if (isset($_SESSION['email_verified']) && $_SESSION['email_verified'] === true): ?>
                        <!-- Already verified email display -->
                        <div id="email_verification">
                            <div class="flex items-center mb-4">
                                <div class="bg-green-100 text-green-800 rounded-full p-1 mr-3">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                                <p class="font-medium">Email verified: <?php echo htmlspecialchars($_SESSION['verified_email']); ?></p>
                                <input type="hidden" name="email_verified" value="1">
                                <input type="hidden" name="verified_email" value="<?php echo htmlspecialchars($_SESSION['verified_email']); ?>">
                                <a href="logout.php?service=email" class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-sm ml-3">
                                    Logout Email
                                </a>
                            </div>
                        </div>
                    <?php elseif (isset($_SESSION['code_sent']) && $_SESSION['code_sent'] === true): ?>
                        <!-- Code sent but not yet verified -->
                        <div id="email_verification">
                            <div class="mb-4">
                                <label class="block mb-2 font-bold" for="email">
                                    Your Email Address
                                </label>
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_SESSION['code_sent_email']); ?>"
                                       class="w-full p-3 border border-gray-300 rounded">
                            </div>

                            <div class="mb-4 flex">
                                <input type="text" id="email_verification_code" name="email_verification_code" maxlength="6" placeholder="Enter 6-digit code"
                                       class="w-full p-3 border border-gray-300 rounded mr-2">
                                <button type="button" id="send_code_btn" onclick="sendVerificationCode()"
                                        class="bg-blue-500 text-white py-2 px-4 rounded hover:bg-blue-600">
                                    Resend Code
                                </button>
                                <button type="button" id="verify_code_btn" onclick="verifyEmailCode()"
                                        class="bg-green-500 text-white py-2 px-4 rounded hover:bg-green-600 ml-2">
                                    Verify Code
                                </button>
                            </div>
                            <p class="text-sm text-yellow-600 mb-4">We sent a verification code to your email. Please check your inbox and spam folder.</p>
                        </div>
                    <?php else: ?>
                        <!-- Initial email input state -->
                        <div id="email_verification">
                            <div class="mb-4">
                                <label class="block mb-2 font-bold" for="email">
                                    Your Email Address
                                </label>
                                <input type="email" id="email" name="email"
                                       class="w-full p-3 border border-gray-300 rounded">
                            </div>

                            <div class="mb-4 flex">
                                <input type="text" id="email_verification_code" name="email_verification_code" maxlength="6" placeholder="------"
                                       class="hidden w-full p-3 border border-gray-300 rounded mr-2">
                                <button type="button" id="send_code_btn" onclick="sendVerificationCode()"
                                        class="bg-blue-500 text-white py-2 px-4 rounded hover:bg-blue-600">
                                    Send Verification Code
                                </button>
                                <button type="button" id="verify_code_btn" onclick="verifyEmailCode()"
                                        class="hidden bg-green-500 text-white py-2 px-4 rounded hover:bg-green-600 ml-2">
                                    Verify Code
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Steam Authentication Section with Improved Display and Logout -->
            <div class="mb-8 border rounded-lg p-6 bg-gray-50">
                <h4 class="text-lg font-bold mb-4">Steam Authentication</h4>

                <?php if ($steamVerified): ?>
                    <div class="flex items-center mb-4">
                        <div class="bg-green-100 text-green-800 rounded-full p-1 mr-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <div>
                            <p class="font-medium">Steam account verified!</p>
                            <?php if (!empty($_SESSION['steam_username'])): ?>
                                <p class="text-sm text-gray-600">Username: <?php echo htmlspecialchars($_SESSION['steam_username']); ?></p>
                            <?php endif; ?>
                            <p class="text-sm text-gray-600">SteamID3: <?php echo htmlspecialchars($_SESSION['steam_id3'] ?? ''); ?></p>
                            <?php if (!empty($_SESSION['steam_profile'])): ?>
                                <p class="text-sm text-gray-600">
                                    <a href="<?php echo htmlspecialchars($_SESSION['steam_profile']); ?>" target="_blank" class="text-blue-600 hover:underline">View Profile</a>
                                </p>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($_SESSION['steam_avatar'])): ?>
                            <div class="ml-auto mr-4">
                                <img src="<?php echo htmlspecialchars($_SESSION['steam_avatar']); ?>" alt="Steam Avatar" class="w-10 h-10 rounded-full">
                            </div>
                        <?php endif; ?>
                        <a href="logout.php?service=steam" class="bg-red-600 hover:bg-red-700 text-white py-1 px-3 rounded text-sm flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                            Logout
                        </a>
                    </div>
                    <input type="hidden" name="steam_url" value="<?php echo htmlspecialchars($_SESSION['steam_profile'] ?? ''); ?>">
                    <input type="hidden" name="steam_id3" value="<?php echo htmlspecialchars($_SESSION['steam_id3'] ?? ''); ?>">
                <?php else: ?>
                    <div class="mb-6">
                        <p class="mb-4">Connect your Steam account to verify your identity.</p>
                        <a href="steam_auth.php" class="inline-flex items-center bg-gray-800 hover:bg-gray-900 text-white font-bold py-3 px-6 rounded auth-button">
                            <svg class="w-6 h-6 mr-2" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M11.979 0C5.678 0 0.486 4.95 0 11.121L6.436 12.868C6.9 12.343 7.556 12.015 8.277 12.015H8.382L11.229 8.015V8.009C11.229 5.983 12.862 4.347 14.888 4.347C16.913 4.347 18.547 5.983 18.547 8.009C18.547 10.035 16.913 11.665 14.888 11.665H14.821L10.864 14.563V14.651C10.864 16.325 9.503 17.686 7.83 17.686C6.352 17.686 5.117 16.673 4.851 15.289L0.232 14.029C1.681 19.825 6.366 24 11.979 24C18.624 24 24 18.624 24 12C24 5.376 18.624 0 11.979 0ZM7.543 16.088L6.16 15.673C6.392 16.116 6.833 16.445 7.385 16.445C8.159 16.445 8.787 15.817 8.787 15.044C8.787 14.271 8.159 13.643 7.385 13.643C7.007 13.643 6.67 13.787 6.421 14.022L7.831 14.449C8.384 14.617 8.696 15.2 8.529 15.748C8.361 16.301 7.784 16.612 7.237 16.445C7.013 16.373 6.823 16.247 6.678 16.088H7.543ZM17.181 8.003C17.181 6.76 16.166 5.745 14.923 5.745C13.68 5.745 12.664 6.76 12.664 8.003C12.664 9.246 13.68 10.267 14.923 10.267C16.166 10.267 17.181 9.246 17.181 8.003ZM14.923 6.685C14.203 6.685 13.61 7.278 13.61 7.997C13.61 8.717 14.203 9.31 14.923 9.31C15.642 9.31 16.24 8.717 16.24 7.997C16.24 7.278 15.642 6.685 14.923 6.685Z" fill="white"/>
                            </svg>
                            Connect with Steam
                        </a>
                    </div>

                    <?php if (isset($_SESSION['error_message']) && strpos($_SESSION['error_message'], 'Steam') !== false): ?>
                        <div class="mt-4 p-3 bg-red-100 border border-red-300 rounded text-red-800">
                            <p class="font-bold">Error with Steam authentication:</p>
                            <p><?php echo htmlspecialchars($_SESSION['error_message']); ?></p>
                            <p class="mt-2 text-sm">If you continue to experience issues, please try clearing your browser cookies or using a different browser.</p>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <div class="flex justify-end">
                <button type="button" onclick="nextStep(1)" class="bg-black text-white py-2 px-6 rounded hover:bg-gray-800">
                    Next Step
                </button>
            </div>
        </div>

        <!-- Step 2 - Account History & Bans with Required Field Indicators -->
        <div id="step2" class="step">
            <h3 class="text-xl font-bold mb-6">Account History</h3>

            <div class="mb-6">
                <label class="block mb-2 font-bold required-label" for="main_account_yes">
                    Is this your Main Account?
                    <span class="required-field">Required</span>
                </label>
                <div class="flex items-center mb-2">
                    <input type="radio" id="main_account_yes" name="main_account" value="yes" required
                           class="mr-2 required-input">
                    <label for="main_account_yes">Yes</label>
                </div>
                <div class="flex items-center">
                    <input type="radio" id="main_account_no" name="main_account" value="no" required
                           class="mr-2 required-input">
                    <label for="main_account_no">No</label>
                </div>
            </div>

            <div id="other_accounts_container" class="mb-6 hidden">
                <label class="block mb-2 font-bold required-label" for="other_accounts">
                    List your other accounts, including  Main Account.
                    <span class="required-field">Required</span>
                </label>
                <textarea id="other_accounts" name="other_accounts" rows="4"
                          class="w-full p-3 border border-gray-300 rounded required-input"></textarea>
                <p class="text-sm mt-1 text-gray-600">
                    Please fill in the SteamID3's, Steam Profile Link for the other accounts you have played on the server
                    e.g. [U:1:22202] - https://steamcommunity.com/id/gabelogannewell - Yes / No / Waiting
                </p>
            </div>

            <div class="mb-6">
                <label class="block mb-2 font-bold required-label" for="vac_ban_yes">
                    Do you have a VAC Game Ban on your Steam Account?
                    <span class="required-field">Required</span>
                </label>
                <div class="flex items-center mb-2">
                    <input type="radio" id="vac_ban_yes" name="vac_ban" value="yes" required
                           class="mr-2 required-input">
                    <label for="vac_ban_yes">Yes</label>
                </div>
                <div class="flex items-center">
                    <input type="radio" id="vac_ban_no" name="vac_ban" value="no" required
                           class="mr-2 required-input">
                    <label for="vac_ban_no">No</label>
                </div>
            </div>

            <div id="vac_ban_reason_container" class="mb-6 hidden">
                <label class="block mb-2 font-bold required-label" for="vac_ban_reason">
                    What is the reason for the VAC Game Ban and how long has it been since the last Ban?
                    <span class="required-field">Required</span>
                </label>
                <textarea id="vac_ban_reason" name="vac_ban_reason" rows="4"
                          class="w-full p-3 border border-gray-300 rounded required-input"></textarea>
            </div>

            <div class="flex justify-between">
                <button type="button" onclick="prevStep(2)" class="bg-gray-300 text-black py-2 px-6 rounded hover:bg-gray-400">
                    Previous Step
                </button>
                <button type="button" onclick="nextStep(2)" class="bg-black text-white py-2 px-6 rounded hover:bg-gray-800">
                    Next Step
                </button>
            </div>
        </div>

        <!-- Step 3 - Additional Information (Without Agreement) -->
        <div id="step3" class="step">
            <h3 class="text-xl font-bold mb-6">Additional Information (Optional)</h3>

            <div class="mb-6">
                <label class="block mb-2 font-bold" for="referral">
                    Have you been referred by another User?
                </label>
                <input type="text" id="referral" name="referral"
                       class="w-full p-3 border border-gray-300 rounded">
                <p class="text-sm mt-1 text-gray-600">
                    If true, put in the Steam Profile Link from the User
                </p>
            </div>

            <div class="mb-6">
                <label class="block mb-2 font-bold" for="experience_1">
                    How would you rate your experience playing Dodgeball?
                </label>
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <input type="radio" id="experience_1" name="experience" value="1" class="mr-1">
                        <label for="experience_1">Noob</label>
                    </div>
                    <div class="flex items-center">
                        <input type="radio" id="experience_2" name="experience" value="2" class="mr-1">
                        <label for="experience_2">Casual</label>
                    </div>
                    <div class="flex items-center">
                        <input type="radio" id="experience_3" name="experience" value="3" class="mr-1">
                        <label for="experience_3">Decent</label>
                    </div>
                    <div class="flex items-center">
                        <input type="radio" id="experience_4" name="experience" value="4" class="mr-1">
                        <label for="experience_4">Elite</label>
                    </div>
                    <div class="flex items-center">
                        <input type="radio" id="experience_5" name="experience" value="5" class="mr-1">
                        <label for="experience_5">Godlike</label>
                    </div>
                </div>
            </div>

            <div class="mb-6">
                <label class="block mb-2 font-bold" for="comments">
                    If you have any other comments or suggestions, put it in here
                </label>
                <textarea id="comments" name="comments" rows="4"
                          class="w-full p-3 border border-gray-300 rounded"></textarea>
            </div>

            <div class="flex justify-between">
                <button type="button" onclick="prevStep(3)" class="bg-gray-300 text-black py-2 px-6 rounded hover:bg-gray-400">
                    Previous Step
                </button>
                <button type="button" onclick="nextStep(3)" class="bg-black text-white py-2 px-6 rounded hover:bg-gray-800">
                    Next Step
                </button>
            </div>
        </div>

        <!-- Step 4 - Whitelist Agreement -->
        <div id="step4" class="step">
            <h3 class="text-xl font-bold mb-6">Whitelist Agreement</h3>
            <?php
            if (isset($_SESSION['form_errors']) && !empty($_SESSION['form_errors'])) {
                echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">';
                echo '<ul class="list-disc list-inside">';
                foreach ($_SESSION['form_errors'] as $error) {
                    echo '<li>' . htmlspecialchars($error) . '</li>';
                }
                echo '</ul></div>';
                unset($_SESSION['form_errors']);
            }
            ?>
            <div class="mb-8 p-4 bg-gray-50 border rounded">
                <p class="mb-4">
                    Before submitting your application, please carefully read and accept the following terms:
                </p>

                <ul class="list-disc list-inside mb-4 space-y-2">
                    <li>Your provided information will be used solely for the purpose of the whitelist application process.</li>
                    <li>Dishonesty in your application may result in immediate removal from the whitelist or a permanent ban.</li>
                    <li>Submitting an application does not guarantee acceptance to the whitelist.</li>
                    <li>The server administration holds the final decision regarding all whitelist applications.</li>
                </ul>
            </div>

            <div class="mb-6">
                <label class="block mb-2 font-bold required-label">
                    Whitelist Application Agreement
                    <span class="required-field">Required</span>
                </label>
                <div class="flex items-start mb-2">
                    <input type="checkbox" id="agreement_privacy" name="agreements[]" value="privacy" required
                           class="mr-2 mt-1 required-input">
                    <label for="agreement_privacy">I accept the privacy policy.</label>
                </div>
                <div class="flex items-start mb-2">
                    <input type="checkbox" id="agreement_truthful" name="agreements[]" value="truthful" required
                           class="mr-2 mt-1 required-input">
                    <label for="agreement_truthful">I confirm that the information I provided is truthful.</label>
                </div>
                <div class="flex items-start mb-2">
                    <input type="checkbox" id="agreement_guarantee" name="agreements[]" value="guarantee" required
                           class="mr-2 mt-1 required-input">
                    <label for="agreement_guarantee">I acknowledge that submitting this form does not guarantee being added to the whitelist.</label>
                </div>
            </div>

            <div class="flex justify-between">
                <button type="button" onclick="prevStep(4)" class="bg-gray-300 text-black py-2 px-6 rounded hover:bg-gray-400">
                    Previous Step
                </button>
                <button type="submit" class="bg-black text-white py-2 px-6 rounded hover:bg-gray-800">
                    Submit Application
                </button>
            </div>
        </div>
    </form>

    <!-- Debug Panel - Only visible in debug mode -->
    <?php
    // Define a simple function to check if we're in debug mode
    function isDebugMode() {
        return defined('DEBUG_MODE') && DEBUG_MODE === true;
    }

    // Only show this panel if in debug mode
    if (isDebugMode()):
        ?>
        <div class="fixed bottom-0 right-0 bg-gray-900 text-white p-4 rounded-tl-lg shadow-lg max-w-lg overflow-auto max-h-96 z-50 text-xs">
            <h4 class="font-bold mb-2 flex justify-between items-center">
                <span>Debug Panel</span>
                <button onclick="this.parentElement.parentElement.classList.toggle('max-h-96')" class="text-gray-400 hover:text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
            </h4>

            <div class="space-y-3">
                <!-- Session Data -->
                <div>
                    <h5 class="font-bold text-gray-400">Session Data:</h5>
                    <pre class="text-green-400 overflow-auto max-h-32"><?php print_r($_SESSION); ?></pre>
                </div>

                <!-- Authentication Status -->
                <div>
                    <h5 class="font-bold text-gray-400">Authentication Status:</h5>
                    <ul class="list-disc list-inside">
                        <li>Steam: <span class="<?php echo isset($_SESSION['steam_verified']) && $_SESSION['steam_verified'] ? 'text-green-400' : 'text-red-400'; ?>">
                    <?php echo isset($_SESSION['steam_verified']) && $_SESSION['steam_verified'] ? 'Verified' : 'Not Verified'; ?>
                </span></li>
                        <li>Discord: <span class="<?php echo isset($_SESSION['discord_verified']) && $_SESSION['discord_verified'] ? 'text-green-400' : 'text-red-400'; ?>">
                    <?php echo isset($_SESSION['discord_verified']) && $_SESSION['discord_verified'] ? 'Verified' : 'Not Verified'; ?>
                </span></li>
                        <li>Email: <span class="<?php echo isset($_SESSION['email_verified']) && $_SESSION['email_verified'] ? 'text-green-400' : 'text-red-400'; ?>">
                    <?php echo isset($_SESSION['email_verified']) && $_SESSION['email_verified'] ? 'Verified' : 'Not Verified'; ?>
                </span></li>
                    </ul>
                </div>

                <!-- Error Message -->
                <?php if (isset($_SESSION['error_message'])): ?>
                    <div>
                        <h5 class="font-bold text-gray-400">Last Error:</h5>
                        <p class="text-red-400"><?php echo htmlspecialchars($_SESSION['error_message']); ?></p>
                    </div>
                <?php endif; ?>

                <!-- Log File Status -->
                <div>
                    <h5 class="font-bold text-gray-400">Log Files:</h5>
                    <ul class="list-disc list-inside">
                        <?php
                        $logDir = defined('LOG_DIRECTORY') ? LOG_DIRECTORY : __DIR__ . '/logs/';
                        $logFiles = glob($logDir . '*.log');
                        foreach ($logFiles as $logFile):
                            $fileName = basename($logFile);
                            $fileSize = filesize($logFile);
                            $fileTime = date("Y-m-d H:i:s", filemtime($logFile));
                            ?>
                            <li>
                                <span class="text-blue-400"><?php echo htmlspecialchars($fileName); ?></span>
                                <span class="text-gray-500">(<?php echo round($fileSize/1024, 2); ?> KB - <?php echo $fileTime; ?>)</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <!-- API Keys Status (masked) -->
                <div>
                    <h5 class="font-bold text-gray-400">API Configuration:</h5>
                    <ul class="list-disc list-inside">
                        <li>Discord Client ID: <span class="text-yellow-400">
                    <?php echo defined('DISCORD_CLIENT_ID') ? substr(DISCORD_CLIENT_ID, 0, 5) . '...' : 'Not set'; ?>
                </span></li>
                        <li>Steam API Key: <span class="text-yellow-400">
                    <?php echo defined('STEAM_API_KEY') ? substr(STEAM_API_KEY, 0, 5) . '...' : 'Not set'; ?>
                </span></li>
                    </ul>
                </div>

                <!-- Debug Actions -->
                <div>
                    <h5 class="font-bold text-gray-400">Debug Actions:</h5>
                    <div class="flex space-x-2 mt-1">
                        <a href="?debug_action=clear_session" class="bg-red-700 hover:bg-red-600 text-white px-2 py-1 rounded text-xs">Clear Session</a>
                        <a href="?debug_action=view_logs" class="bg-blue-700 hover:bg-blue-600 text-white px-2 py-1 rounded text-xs">View Logs</a>
                        <a href="?debug_action=test_steam" class="bg-green-700 hover:bg-green-600 text-white px-2 py-1 rounded text-xs">Test Steam Auth</a>
                    </div>
                </div>
            </div>
        </div>

        <?php
// Handle debug actions
        if (isset($_GET['debug_action'])) {
            switch ($_GET['debug_action']) {
                case 'clear_session':
                    session_unset();
                    echo '<script>alert("Session cleared"); window.location.href = "index.php";</script>';
                    break;

                case 'view_logs':
                    // This would be better in a separate page, but for simple debugging:
                    echo '<div class="fixed inset-0 bg-black bg-opacity-90 z-50 p-4 overflow-auto">';
                    echo '<div class="bg-gray-800 text-white p-4 rounded max-w-4xl mx-auto">';
                    echo '<div class="flex justify-between items-center mb-4">';
                    echo '<h2 class="text-xl font-bold">Log Files</h2>';
                    echo '<a href="index.php" class="bg-gray-700 hover:bg-gray-600 px-3 py-1 rounded">Close</a>';
                    echo '</div>';

                    $logFiles = glob($logDir . '*.log');
                    foreach ($logFiles as $logFile) {
                        $fileName = basename($logFile);
                        echo '<div class="mb-4">';
                        echo '<h3 class="font-bold text-lg border-b border-gray-600 mb-2">' . htmlspecialchars($fileName) . '</h3>';
                        echo '<pre class="bg-gray-900 p-2 rounded overflow-auto max-h-64 text-xs">' . htmlspecialchars(file_get_contents($logFile)) . '</pre>';
                        echo '</div>';
                    }

                    echo '</div></div>';
                    break;

                case 'test_steam':
                    // Simulate Steam auth with test data
                    $_SESSION['steam_verified'] = true;
                    $_SESSION['steam_id'] = '76561198123456789';
                    $_SESSION['steam_id3'] = '[U:1:123456789]';
                    $_SESSION['steam_username'] = 'Test_User';
                    $_SESSION['steam_profile'] = 'https://steamcommunity.com/id/test_user';
                    echo '<script>alert("Steam test data injected"); window.location.href = "index.php";</script>';
                    break;
            }
        }
        ?>

    <?php endif; // End of debug mode check ?>
</div>
<?php endif; ?>
<script>
    // Simple form persistence script

    // DOM Ready
    document.addEventListener('DOMContentLoaded', function() {
        // Setup conditional fields
        if (document.getElementById('main_account_no')) {
            document.getElementById('main_account_no').addEventListener('change', function() {
                document.getElementById('other_accounts_container').classList.remove('hidden');
            });
        }

        if (document.getElementById('main_account_yes')) {
            document.getElementById('main_account_yes').addEventListener('change', function() {
                document.getElementById('other_accounts_container').classList.add('hidden');
            });
        }

        if (document.getElementById('vac_ban_yes')) {
            document.getElementById('vac_ban_yes').addEventListener('change', function() {
                document.getElementById('vac_ban_reason_container').classList.remove('hidden');
            });
        }

        if (document.getElementById('vac_ban_no')) {
            document.getElementById('vac_ban_no').addEventListener('change', function() {
                document.getElementById('vac_ban_reason_container').classList.add('hidden');
            });
        }

        // Setup email verification option
        if (document.getElementById('use_email')) {
            document.getElementById('use_email').addEventListener('change', function() {
                document.getElementById('email_verification').classList.remove('hidden');
            });
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('whitelist-form');

        // Save form data on any input change
        form.addEventListener('input', function() {
            const formData = new FormData(form);
            const data = {};

            // Convert FormData to a simple object
            for (let [key, value] of formData.entries()) {
                if (key.endsWith('[]')) {
                    // Handle arrays (like checkboxes with same name)
                    const arrayKey = key.slice(0, -2);
                    if (!data[arrayKey]) {
                        data[arrayKey] = [];
                    }
                    data[arrayKey].push(value);
                } else {
                    data[key] = value;
                }
            }

            // Also add radio buttons and checkboxes
            const radios = form.querySelectorAll('input[type="radio"]:checked');
            radios.forEach(radio => {
                data[radio.name] = radio.value;
            });

            const checkboxes = form.querySelectorAll('input[type="checkbox"]');
            checkboxes.forEach(checkbox => {
                if (checkbox.name.endsWith('[]')) {
                    // Already handled above
                    return;
                }
                data[checkbox.name] = checkbox.checked;
            });

            // Save current step
            const activeStep = document.querySelector('.step.active');
            if (activeStep) {
                data['currentStep'] = activeStep.id;
            }

            // Save to localStorage
            localStorage.setItem('formData', JSON.stringify(data));
        });

        // Restore saved form data if available
        const savedData = localStorage.getItem('formData');
        if (savedData) {
            const data = JSON.parse(savedData);

            // Restore text inputs and textareas
            for (const [name, value] of Object.entries(data)) {
                if (name === 'currentStep') continue;

                const elements = form.querySelectorAll(`[name="${name}"]`);
                elements.forEach(element => {
                    if (element.type === 'radio') {
                        element.checked = (element.value === value);
                    } else if (element.type === 'checkbox') {
                        if (Array.isArray(value)) {
                            element.checked = value.includes(element.value);
                        } else {
                            element.checked = value;
                        }
                    } else {
                        element.value = value;
                    }
                });
            }

            // Restore checkbox arrays
            for (const [name, valueArray] of Object.entries(data)) {
                if (!Array.isArray(valueArray)) continue;

                valueArray.forEach(value => {
                    const checkbox = form.querySelector(`input[name="${name}[]"][value="${value}"]`);
                    if (checkbox) {
                        checkbox.checked = true;
                    }
                });
            }

            // Trigger display of conditional fields
            if (data.main_account === 'no') {
                document.getElementById('other_accounts_container').classList.remove('hidden');
            }

            if (data.vac_ban === 'yes') {
                document.getElementById('vac_ban_reason_container').classList.remove('hidden');
            }

            // Restore current step
            if (data.currentStep) {
                document.querySelectorAll('.step').forEach(step => {
                    step.classList.remove('active');
                });
                const currentStep = document.getElementById(data.currentStep);
                if (currentStep) {
                    currentStep.classList.add('active');

                    // Update progress bar
                    const stepNumber = data.currentStep.replace('step', '');
                    const progressBar = document.getElementById('progress-bar');
                    if (progressBar) {
                        progressBar.style.width = `${stepNumber * 25}%`;
                    }
                }
            }
        }

        // Clear saved data on successful submission
        form.addEventListener('submit', function() {
            localStorage.removeItem('formData');
        });
    });


    // Add this function to your existing <script> section
    function displayErrorMessage(message) {
        // Create error message container if it doesn't exist
        let errorContainer = document.getElementById('form-error-container');
        if (!errorContainer) {
            errorContainer = document.createElement('div');
            errorContainer.id = 'form-error-container';
            errorContainer.className = 'bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6';

            const formContainer = document.querySelector('.step.active');
            if (formContainer) {
                formContainer.insertBefore(errorContainer, formContainer.firstChild);
            }
        }

        // Set the message
        errorContainer.innerHTML = `<p>${message}</p>`;

        // Scroll to the message
        errorContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    // Add this function to clear error messages
    function clearErrorMessage() {
        const errorContainer = document.getElementById('form-error-container');
        if (errorContainer) {
            errorContainer.remove();
        }
    }

    // Email verification functions
    // Email verification functions with improved error handling and persistence
    function sendVerificationCode() {
        clearErrorMessage();

        const email = document.getElementById('email').value;
        if (!email) {
            displayErrorMessage('Please enter your email address.');
            return;
        }

        // Show loading state
        const sendButton = document.getElementById('send_code_btn');
        const originalText = sendButton.textContent;
        sendButton.textContent = 'Sending...';
        sendButton.disabled = true;

        // AJAX to send verification code
        fetch('send_verification.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `email=${encodeURIComponent(email)}`
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                // Reset button state
                sendButton.disabled = false;

                if (data.success) {
                    // Save verification code sent state
                    saveCodeSentState(email);

                    // Show verification code input and verify button
                    const codeInput = document.getElementById('email_verification_code');
                    codeInput.classList.remove('hidden');
                    codeInput.placeholder = 'Enter 6-digit code';

                    document.getElementById('verify_code_btn').classList.remove('hidden');
                    sendButton.textContent = 'Resend Code';

                    displayErrorMessage('Verification code sent to your email. Please check your inbox and spam folder.');
                } else {
                    sendButton.textContent = originalText;
                    displayErrorMessage('Error sending verification code: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                displayErrorMessage('An error occurred while sending the verification code. Please try again.');

                // Reset button state
                sendButton.disabled = false;
                sendButton.textContent = originalText;
            });
    }

    function verifyEmailCode() {
        clearErrorMessage();

        const email = document.getElementById('email').value;
        const code = document.getElementById('email_verification_code').value;

        if (!email || !code) {
            displayErrorMessage('Please enter both email and verification code.');
            return;
        }

        // Show loading state
        const verifyButton = document.getElementById('verify_code_btn');
        const originalText = verifyButton.textContent;
        verifyButton.textContent = 'Verifying...';
        verifyButton.disabled = true;

        // Log the data being sent for verification
        console.log('Verifying email:', email, 'with code:', code);

        // AJAX to verify code
        fetch('verify_code.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `email=${encodeURIComponent(email)}&code=${encodeURIComponent(code)}`
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Verification response:', data);

                // Reset button state
                verifyButton.disabled = false;
                verifyButton.textContent = originalText;

                if (data.success) {
                    // Show verification success
                    const emailVerification = document.getElementById('email_verification');
                    emailVerification.innerHTML = `
                <div class="flex items-center mb-4">
                    <div class="bg-green-100 text-green-800 rounded-full p-1 mr-3">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <p class="font-medium">Email verified: ${email}</p>
                    <input type="hidden" name="email_verified" value="1">
                    <input type="hidden" name="verified_email" value="${email}">
                    <a href="logout.php?service=email" class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-sm ml-3">
                        Logout Email
                    </a>
                </div>
            `;

                    // Refresh the page to update the session state
                    window.location.reload();
                } else {
                    displayErrorMessage('Verification failed: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                displayErrorMessage('An error occurred during verification. Please try again.');

                // Reset button state
                verifyButton.disabled = false;
                verifyButton.textContent = originalText;
            });
    }

    // Function to save the code sent state
    function saveCodeSentState(email) {
        fetch('save_code_state.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `email=${encodeURIComponent(email)}`
        })
            .then(response => response.json())
            .then(data => {
                console.log('Code sent state saved:', data);
            })
            .catch(error => {
                console.error('Error saving code sent state:', error);
            });
    }

    function validateStep2() {
        clearErrorMessage()

        // Check if main account selection is made
        const mainAccountYes = document.getElementById('main_account_yes').checked;
        const mainAccountNo = document.getElementById('main_account_no').checked;

        if (!mainAccountYes && !mainAccountNo) {
            displayErrorMessage("Please select whether this is your main account.");
            return false;
        }

        // If "No" is selected, validate that other accounts are listed
        if (mainAccountNo) {
            const otherAccounts = document.getElementById('other_accounts').value.trim();
            if (!otherAccounts) {
                displayErrorMessage("Please list your other accounts.");
                return false;
            }
        }

        // Check if VAC ban selection is made
        const vacBanYes = document.getElementById('vac_ban_yes').checked;
        const vacBanNo = document.getElementById('vac_ban_no').checked;

        if (!vacBanYes && !vacBanNo) {
            displayErrorMessage("Please select whether you have a VAC game ban.");
            return false;
        }

        // If "Yes" is selected, validate that reason is provided
        if (vacBanYes) {
            const vacBanReason = document.getElementById('vac_ban_reason').value.trim();
            if (!vacBanReason) {
                displayErrorMessage("Please provide the reason for your VAC ban.");
                return false;
            }
        }

        return true;
    }

    function validateAgreements() {
        clearErrorMessage();

        const requiredAgreements = ['privacy', 'truthful', 'guarantee'];
        const checkboxes = document.querySelectorAll('input[name="agreements[]"]');

        let allChecked = true;
        requiredAgreements.forEach(function(agreement) {
            const checkbox = document.querySelector(`input[value="${agreement}"]`);
            if (!checkbox || !checkbox.checked) {
                allChecked = false;
            }
        });

        if (!allChecked) {
            displayErrorMessage("You must agree to all terms to submit the application.");
            return false;
        }

        return true;
    }

    // Navigation between steps
    function nextStep(currentStep) {
        clearErrorMessage();
        // Basic validation
        if (currentStep === 1) {
            const steamVerified = document.querySelector('input[name="steam_url"]') !== null;
            const discordVerified = document.querySelector('input[name="discord_verified"]') !== null;
            const emailVerified = document.querySelector('input[name="email_verified"]') !== null;

            if (!steamVerified) {
                displayErrorMessage('Please authenticate with Steam before proceeding.');
                return;
            }

            if (!discordVerified && !emailVerified) {
                displayErrorMessage('Please authenticate with Discord or verify your email before proceeding.');
                return;
            }
        }

        if (currentStep === 2) {
            if (!validateStep2()) {
                return;
            }
        }

        document.getElementById(`step${currentStep}`).classList.remove('active');
        document.getElementById(`step${currentStep + 1}`).classList.add('active');

        // Update progress bar - adjusted for 4 steps (25% per step)
        const progressBar = document.getElementById('progress-bar');
        progressBar.style.width = `${(currentStep + 1) * 25}%`;
    }

    function prevStep(currentStep) {
        document.getElementById(`step${currentStep}`).classList.remove('active');
        document.getElementById(`step${currentStep - 1}`).classList.add('active');

        // Update progress bar - adjusted for 4 steps (25% per step)
        const progressBar = document.getElementById('progress-bar');
        progressBar.style.width = `${(currentStep - 1) * 25}%`;
    }

    // Form submission with agreement validation
    document.getElementById('whitelist-form').addEventListener('submit', function(event) {
        clearErrorMessage();

        if (!validateAgreements()) {
            event.preventDefault();
            return false;
        }
    });
</script>
</body>
</html>