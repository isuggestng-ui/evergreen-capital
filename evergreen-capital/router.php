<?php
/**
 * Router for PHP's built-in web server (used as the Railway start command).
 *
 *   php -S 0.0.0.0:$PORT -t . router.php
 *
 * The built-in server has no .htaccess support, so this script reproduces the
 * essentials: serve real static files as-is, hand .php requests to PHP, and
 * route unknown paths to the app's 404 page instead of returning a bare 500.
 */

$uri  = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$root = __DIR__;
$path = realpath($root . $uri);

// Security: never allow path traversal outside the project root.
if ($path !== false && strpos($path, $root) !== 0) {
    http_response_code(403);
    echo 'Forbidden';
    return true;
}

// 1. Existing static file (css/js/img/fonts/uploads) -> let the server stream it.
if ($path !== false && is_file($path)) {
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if ($ext !== 'php') {
        return false; // false = "serve this file as-is"
    }
    // A real .php file was requested directly -> execute it.
    require $path;
    return true;
}

// 2. Directory request -> look for its index.php.
if ($path !== false && is_dir($path)) {
    $index = $path . '/index.php';
    if (is_file($index)) {
        require $index;
        return true;
    }
}

// 3. Root request.
if ($uri === '/' || $uri === '') {
    require $root . '/index.php';
    return true;
}

// 4. Anything else -> the app's own 404 page (clean, themed, not a 500).
http_response_code(404);
$notFound = $root . '/404.php';
if (is_file($notFound)) {
    require $notFound;
} else {
    echo 'Not found';
}
return true;
