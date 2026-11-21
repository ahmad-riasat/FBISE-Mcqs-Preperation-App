<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/response.php';


// 1. START SESSION
function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}


// 2. GENERATE CSRF TOKEN
function csrf_token(): string
{
    start_session();

    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf'];
}


// 3. VERIFY CSRF TOKEN
function require_csrf(): void
{
    start_session();

    $sent   = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $stored = $_SESSION['csrf'] ?? '';

    if ($stored === '' || !hash_equals($stored, $sent)) {
        respond(403, [
            'ok'    => false,
            'error' => 'Invalid security token. Reload the page.'
        ]);
    }
}


// 4. LOGIN USER
function login_user(int $userId): void
{
    start_session();

    session_regenerate_id(true);

    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf']    = bin2hex(random_bytes(32));
}


// 5. LOGOUT USER
function logout_user(): void
{
    start_session();

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();

        setcookie(session_name(), '', [
            'expires'  => time() - 3600,
            'path'     => $p['path'],
            'secure'   => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'] ?: 'Lax',
        ]);
    }

    session_destroy();
}


// 6. GET CURRENT LOGGED-IN USER
function current_user(): ?array
{
    start_session();

    $id = $_SESSION['user_id'] ?? null;

    if (!is_int($id)) {
        return null;
    }

    $stmt = db()->prepare(
        'SELECT id, full_name, roll_number, role
         FROM users
         WHERE id = :id'
    );

    $stmt->execute([':id' => $id]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    return $user ?: null;
}


// 7. REQUIRE LOGIN
function require_login(): array
{
    $user = current_user();

    if ($user === null) {
        respond(401, [
            'ok'    => false,
            'error' => 'Please sign in first.'
        ]);
    }

    return $user;
}


// 8. REQUIRE SPECIFIC ROLE
function require_role(string $role): array
{
    $user = require_login();

    if ($user['role'] !== $role) {
        respond(403, [
            'ok'    => false,
            'error' => 'You are not allowed to do that.'
        ]);
    }

    return $user;
}
