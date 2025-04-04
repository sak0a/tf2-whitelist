<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 Page Not Found - Dodgeball Whitelist</title>
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
<div class="bg-white w-full max-w-5xl p-8 mb-10 text-center">
    <h1 class="text-5xl font-bold">saka Dodgeball Whitelist</h1>
</div>

<!-- 404 Container -->
<div class="bg-white w-full max-w-5xl p-10 mb-10 text-center">
    <svg xmlns="http://www.w3.org/2000/svg" class="h-24 w-24 mx-auto mb-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
    </svg>

    <h2 class="text-4xl font-bold mb-4">404</h2>
    <h3 class="text-2xl font-bold mb-6">Page Not Found</h3>

    <p class="text-xl mb-8">
        The page you're looking for doesn't exist or has been moved.
    </p>

    <a href="/" class="inline-block bg-black text-white py-2 px-6 rounded hover:bg-gray-800">
        Return to Homepage
    </a>
</div>

<div class="text-center text-gray-400 mt-8 mb-4">
    <p class="text-sm">&copy; saka Dodgeball <?php echo date('Y'); ?>. All rights reserved.</p>
</div>
</body>
</html>