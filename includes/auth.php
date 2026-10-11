
<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');

    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS'])
            && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
        'path' => '/',
    ]);

    session_start();
}

function requireLogin(): void
{
    if (empty($_SESSION['user_id']) || empty($_SESSION['role'])) {
        header('Location: login.php');
        exit;
    }
}

function requireRole(string $role): void
{
    requireLogin();

    if ($_SESSION['role'] !== $role) {
        http_response_code(403);
        exit('Access denied. You do not have permission to view this page.');
    }
}