<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function currentUser(): ?array
{
    return $_SESSION['user'] ?? null;
}

function isLoggedIn(): bool
{
    return currentUser() !== null;
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        redirect('index.php?page=login');
    }
}

function requireRole(array $roles): void
{
    requireLogin();

    $user = currentUser();
    if ($user === null || !in_array($user['role'], $roles, true)) {
        flash('error', 'You do not have permission to access that page.');
        redirect('index.php?page=dashboard');
    }
}

function loginUser(array $user): void
{
    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'],
    ];
}

function logoutUser(): void
{
    $_SESSION = [];
    session_destroy();
}
