<?php
declare(strict_types=1);

/**
 * Development server router
 * Usage: php -S 127.0.0.1:8080 router.php
 *
 * Routes static asset requests directly to the file on disk.
 * All other requests are dispatched to src/index.php.
 */

if (php_sapi_name() !== 'cli-server') {
    http_response_code(403);
    exit('This router is for the PHP built-in development server only.');
}

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

// Dashboard (dev server only)
if ($uri === '/dashboard' || str_starts_with($uri, '/dashboard?')) {
    require __DIR__ . '/project_mgr/dashboard.php';
    return;
}

// Project manager web view (dev server only)
if ($uri === '/project-mgr' || str_starts_with($uri, '/project-mgr/')) {
    require __DIR__ . '/project_mgr/web.php';
    return;
}

// Form Builder
if ($uri === '/form-builder' || str_starts_with($uri, '/form-builder?') || str_starts_with($uri, '/form-builder/')) {
    require __DIR__ . '/src/builder/index.php';
    return;
}

// Serve static files (CSS, JS, images, fonts, ico) directly
if (
    $uri !== '/' &&
    preg_match('/\.(?:css|js|png|jpg|jpeg|gif|ico|svg|webp|woff|woff2|ttf|otf|map)$/i', $uri) &&
    file_exists(__DIR__ . $uri)
) {
    return false;
}

// Dispatch everything else to the application entry point
require __DIR__ . '/src/index.php';
