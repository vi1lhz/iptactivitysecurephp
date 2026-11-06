<?php
// Accounts: register, login, logout (issues 1, 2, 6, 7).
declare(strict_types=1);

function current_user_id(): ?int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

function current_user(): ?array
{
    $id = current_user_id();
    if ($id === null) {
        return null;
    }
    // Role is read from the database, never from the request.
    $stmt = db()->prepare('SELECT id, email, role FROM users WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/** Returns an error message, or null on success. */
function register_user(string $email, string $password): ?string
{
    $email = trim($email);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
        return 'Please enter a valid email.';
    }
    if (strlen($password) < 12 || strlen($password) > 72) {
        return 'Password must be 12 to 72 characters.';
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    try {
        // Role is fixed by the server; users cannot choose "admin".
        $stmt = db()->prepare("INSERT INTO users (email, password_hash, role) VALUES (?, ?, 'student')");
        $stmt->execute([$email, $hash]);
    } catch (PDOException $e) {
        error_log('[campus-notes] register failed: ' . $e->getCode());
        return 'Could not create account.';
    }
    return null;
}

function login_user(string $email, string $password): bool
{
    $stmt = db()->prepare('SELECT id, password_hash FROM users WHERE email = ?');
    $stmt->execute([trim($email)]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        $up = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $up->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
    }
    session_regenerate_id(true);           // stop session fixation
    $_SESSION = ['user_id' => (int) $user['id']];
    csrf_token();                          // fresh token for the new session
    return true;
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'secure'   => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'],
        ]);
    }
    session_destroy();
}
