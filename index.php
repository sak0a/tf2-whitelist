<?php
// Front controller for your application
require 'vendor/autoload.php';
require_once 'config.php';

// Create the router dispatcher
$dispatcher = FastRoute\simpleDispatcher(function(FastRoute\RouteCollector $r) {
    // Define static routes first, then dynamic routes to avoid shadowing

    // Static route for homepage
    $r->addRoute('GET', '/', function() { require 'pages/index.php'; });

    // Admin routes - static routes should go before the variable ones
    $r->addRoute('GET', '/admin', function() {
        session_start();
        if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
            header('Location: /admin/dashboard');
        } else {
            header('Location: /admin/login');
        }
        exit;
    });

    // Handle specific admin routes with parameters - support both underscore and hyphen formats

    // View Application
    $r->addRoute(['GET', 'POST'], '/admin/view_application/{id:\d+}', function($vars) {
        $_GET['id'] = $vars['id'];
        require 'pages/admin/view-application.php';
    });

    $r->addRoute(['GET', 'POST'], '/admin/view-application/{id:\d+}', function($vars) {
        $_GET['id'] = $vars['id'];
        require 'pages/admin/view-application.php';
    });

    // Quick Approve
    $r->addRoute('GET', '/admin/quick_approve/{id:\d+}', function($vars) {
        $_GET['id'] = $vars['id'];
        require 'pages/admin/quick-approve.php';
    });

    $r->addRoute('GET', '/admin/quick-approve/{id:\d+}', function($vars) {
        $_GET['id'] = $vars['id'];
        require 'pages/admin/quick-approve.php';
    });

    // Quick Reject
    $r->addRoute('GET', '/admin/quick-reject/{id:\d+}', function($vars) {
        $_GET['id'] = $vars['id'];
        require 'pages/admin/quick-reject.php';
    });

    $r->addRoute('GET', '/admin/quick_reject/{id:\d+}', function($vars) {
        $_GET['id'] = $vars['id'];
        require 'pages/admin/quick-reject.php';
    });

    // General admin pages
    $r->addRoute(['GET', 'POST'], '/admin/{page}', function($vars) {
        $page = $vars['page'];

        // Try both underscore and hyphen versions of the file
        $file = 'pages/admin/' . $page . '.php';
        $file_alt = 'pages/admin/' . str_replace('-', '_', $page) . '.php';

        if (file_exists($file) && is_readable($file)) {
            require $file;
        } elseif (file_exists($file_alt) && is_readable($file_alt)) {
            require $file_alt;
        } else {
            // Check if admin is logged in
            session_start();
            if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
                header('Location: /admin/login');
                exit;
            }

            // Redirect to dashboard if page doesn't exist
            header('Location: /admin/dashboard');
            exit;
        }
    });

    // General page route - MUST come after more specific routes to avoid shadowing
    $r->addRoute(['GET', 'POST'], '/{page}', function($vars) {
        $page = $vars['page'];
        $file = 'pages/' . $page . '.php';

        // Also try with underscores instead of hyphens
        $file_alt = 'pages/' . str_replace('-', '_', $page) . '.php';

        if (file_exists($file) && is_readable($file)) {
            require $file;
        } elseif (file_exists($file_alt) && is_readable($file_alt)) {
            require $file_alt;
        } else {
            // Serve custom 404 page
            header("HTTP/1.0 404 Not Found");
            require 'pages/404.php';
            exit;
        }
    });
});

// Get the request method and URI
$httpMethod = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

// Strip query string and decode URI
if (false !== $pos = strpos($uri, '?')) {
    $uri = substr($uri, 0, $pos);
}
$uri = rawurldecode($uri);

// Normalize URI to remove trailing slash except for root path
if ($uri !== '/' && substr($uri, -1) === '/') {
    $uri = rtrim($uri, '/');
}

// Dispatch the router
$routeInfo = $dispatcher->dispatch($httpMethod, $uri);
switch ($routeInfo[0]) {
    case FastRoute\Dispatcher::NOT_FOUND:
        // Handle 404 Not Found with custom page
        header("HTTP/1.0 404 Not Found");
        require 'pages/404.php';
        break;

    case FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
        // Handle 405 Method Not Allowed
        header("HTTP/1.0 405 Method Not Allowed");
        echo "405 Method Not Allowed";
        break;

    case FastRoute\Dispatcher::FOUND:
        // Call the handler and pass variables
        $handler = $routeInfo[1];
        $vars = $routeInfo[2];
        call_user_func($handler, $vars);
        break;
}