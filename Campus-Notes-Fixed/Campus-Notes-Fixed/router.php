<?php
// Router for PHP's built-in dev server: only index.php and api.php are public.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
if ($path === '/' || $path === '/index.php') {
    require __DIR__ . '/index.php';
    return true;
}
if ($path === '/api.php') {
    require __DIR__ . '/api.php';
    return true;
}
http_response_code(404);
echo 'Not found';
return true;
