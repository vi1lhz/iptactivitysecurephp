<?php
// Shared start-up: safe errors, secure session, security headers.
declare(strict_types=1);

// Issue 8: never show errors to the browser; log them instead.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

set_exception_handler(function (Throwable $e): void {
    error_log('[campus-notes] ' . get_class($e) . ': ' . $e->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
    }
    if (defined('IS_API')) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(['error' => 'Server error']);
    } else {
        echo 'Something went wrong. Please try again later.';
    }
});

// Issue 6: hardened session cookie settings.
$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_name('CNSESSID');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => $https,      // true in production (HTTPS)
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// Basic security headers (extra defense against XSS / clickjacking).
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; frame-ancestors 'none'; form-action 'self'; base-uri 'none'");
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header_remove('X-Powered-By');

require __DIR__ . '/db.php';
require __DIR__ . '/security.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/notes.php';
