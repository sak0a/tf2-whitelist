<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thank You - Dodgeball Whitelist</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@xz/fonts@1/serve/jetbrains-mono.min.css">
    <style>
        body {
            font-family: 'JetBrains Mono', monospace;
            background-color: #000;
            color: #333;
        }
    </style>
</head>
<body class="min-h-screen flex flex-col items-center justify-start pt-10 px-4">
<!-- Header Container -->
<div class="bg-white w-full max-w-4xl p-8 mb-10 text-center">
    <h1 class="text-5xl font-bold">Join the Dodgeball Whitelist</h1>
</div>

<!-- Thank You Container -->
<div class="bg-white w-full max-w-4xl p-10 mb-10 text-center">
    <svg xmlns="http://www.w3.org/2000/svg" class="h-20 w-20 mx-auto mb-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>

    <h2 class="text-3xl font-bold mb-4">Application Submitted!</h2>

    <p class="text-xl mb-6">
        Thank you for your interest in joining our Dodgeball Server.
    </p>

    <p class="mb-8">
        Your application has been received and will be reviewed shortly. Please check your email for verification and further instructions.
    </p>

    <div class="border-t pt-8">
        <h3 class="text-xl font-bold mb-4">What happens next?</h3>

        <ol class="text-left mx-auto max-w-lg space-y-4">
            <li class="flex items-start">
                <span class="bg-black text-white rounded-full h-6 w-6 flex items-center justify-center mr-3 mt-1">1</span>
                <span>Check your email inbox for a verification message</span>
            </li>
            <li class="flex items-start">
                <span class="bg-black text-white rounded-full h-6 w-6 flex items-center justify-center mr-3 mt-1">2</span>
                <span>Click the verification link to confirm your email address</span>
            </li>
            <li class="flex items-start">
                <span class="bg-black text-white rounded-full h-6 w-6 flex items-center justify-center mr-3 mt-1">3</span>
                <span>Wait for approval notification (this may take 1-3 business days)</span>
            </li>
        </ol>
        <div class="border-t border-gray-200 pt-8 mt-8">
            <h3 class="text-xl font-bold mb-4">Track Your Application</h3>
            <p class="mb-4">
                You can check the status of your application at any time by visiting our Status Page.
            </p>
            <a href="status.php" class="inline-block bg-black text-white py-2 px-6 rounded hover:bg-gray-800">
                Check Application Status
            </a>
        </div>
    </div>

    <a href="index.php" class="inline-block mt-8 bg-black text-white py-2 px-6 rounded hover:bg-gray-800">
        Return to Homepage
    </a>
</div>
</body>
</html>