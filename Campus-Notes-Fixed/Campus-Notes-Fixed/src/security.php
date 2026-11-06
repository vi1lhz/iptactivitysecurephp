<?php
// Output escaping (issue 4) and CSRF / origin checks (issues 5 and 11).
declare(strict_types=1);

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_valid(?string $token): bool
{
    return is_string($token) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

/** True when the request's Origin/Referer is missing or matches this site. */
function same_origin(): bool
{
    $source = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? null;
    if ($source === null || $source === '') {
        return true; // some browsers omit it; the CSRF token still protects us
    }
    $host = parse_url($source, PHP_URL_HOST);
    $port = parse_url($source, PHP_URL_PORT);
    $origin = $host . ($port ? ':' . $port : '');
    return hash_equals((string) ($_SERVER['HTTP_HOST'] ?? ''), $origin);
}

function require_post_with_csrf(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !same_origin() || !csrf_valid($_POST['csrf'] ?? null)) {
        http_response_code(403);
        exit('Request rejected.');
    }
}
