<?php

function requireAuth(): int
{
    if (empty($_SESSION['user_id'])) {
        jsonError('Authentication required.', 401);
    }

    return (int) $_SESSION['user_id'];
}

function currentUserId(): ?int
{
    return isset($_SESSION['user_id'])
        ? (int) $_SESSION['user_id']
        : null;
}

function isAuthenticated(): bool
{
    return !empty($_SESSION['user_id']);
}

function logoutUser(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}