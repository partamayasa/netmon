<?php
/**
 * Router script for PHP built-in web server
 * Enables clean URL routing for local testing:
 * php -S 0.0.0.0:8000 app/router.php
 */

$rootDir = dirname(__DIR__);
$uriPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$route = trim($uriPath, '/');

// Serve real physical files (static assets like CSS, JS, fonts, images)
if ($route !== '' && file_exists($rootDir . '/' . $route) && !is_dir($rootDir . '/' . $route)) {
    return false;
}

// Block direct access to sensitive configuration files
if ($route === '.env' || str_starts_with($route, 'config/') || $route === 'app/router.php') {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

// Clean URL routing table
switch ($route) {
    case '':
    case 'index.php':
        require $rootDir . '/index.php';
        break;

    case 'login':
    case 'app/login.php':
        require __DIR__ . '/login.php';
        break;

    case 'logout':
    case 'app/logout.php':
        require __DIR__ . '/logout.php';
        break;

    case 'change-password':
    case 'app/change_password.php':
        require __DIR__ . '/change_password.php';
        break;

    default:
        http_response_code(404);
        echo '404 Not Found';
        break;
}
